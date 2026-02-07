<?php

namespace App\Http\Controllers;

use App\Models\VendorReport;
use App\Http\Requests\StoreVendorReportRequest;
use App\Http\Requests\UpdateVendorReportRequest;
use App\Http\Resources\VendorReportResource;
use App\Services\VendorReportSubmissionService;
use App\Services\VendorReportApprovalService;
use App\Services\VendorReportActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class VendorReportController extends Controller
{
    public function __construct(
        private VendorReportSubmissionService $submissionService,
        private VendorReportApprovalService $approvalService,
        private VendorReportActivityService $activityService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', VendorReport::class);

        $user = auth()->user();

        \Log::info('VendorReport Index - User Info', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_roles' => $user->roles->pluck('name')->toArray(),
            'department_id' => $user->department_id,
            'department' => $user->department ? [
                'id' => $user->department->id,
                'name' => $user->department->name,
                'head_user_id' => $user->department->head_user_id,
            ] : null,
        ]);

        $reports = VendorReport::query()
            ->with([
                'creator:id,name,department_id',
                'creator.department:id,name',
                'currentStep:id,report_id,step_key,status',
                'approvalSteps:id,report_id,step_key,status,step_order' // Load để hiển thị rejected_step_label
            ])
            ->withCount('children') // Chỉ đếm số lượng children thay vì load hết
            // Áp dụng logic phân quyền theo role
            ->when(true, function($q) use ($user) {
                // Admin system: xem tất cả
                if ($user->hasRole('admin_system')) {
                    return;
                }

                // Trưởng phòng: xem phiếu của phòng mình hoặc phiếu cần duyệt hoặc đã duyệt
                // CHECK TRƯỚC requester vì Trưởng phòng có thể có role requester nhưng quyền cao hơn
                if ($user->department_id && $user->department->head_user_id === $user->id) {
                    \Log::info('Department Head Query', [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'department_id' => $user->department_id,
                        'head_user_id' => $user->department->head_user_id,
                    ]);

                    $q->where(function($query) use ($user) {
                        // Phiếu của nhân viên cùng phòng (trừ DRAFT của người khác)
                        $query->where(function($q) use ($user) {
                            $q->whereHas('creator', fn($q2) => $q2->where('department_id', $user->department_id))
                              ->where(function($q2) use ($user) {
                                  // Xem DRAFT của chính mình, hoặc phiếu khác DRAFT của người khác
                                  $q2->where('created_by', $user->id)
                                     ->orWhere('status', '!=', 'DRAFT');
                              });
                        })
                        // Hoặc phiếu cần mình duyệt
                        ->orWhereHas('currentStep', function($q) use ($user) {
                            $q->where(function($q2) use ($user) {
                                // Trường hợp 1: Đã được assign cụ thể cho user
                                $q2->where('assignee_user_id', $user->id)
                                   // Trường hợp 2: Chưa assign user cụ thể, nhưng role khớp
                                   ->orWhere(function($q3) use ($user) {
                                       $q3->whereNull('assignee_user_id');

                                       // Check role của user và match với assignee_role
                                       $userRoles = $user->roles->pluck('name')->toArray();
                                       $q3->whereIn('assignee_role', $userRoles);
                                   });
                            });
                        })
                        // Hoặc phiếu mà mình đã duyệt/từ chối
                        ->orWhereHas('approvalSteps', function($q) use ($user) {
                            $q->where('acted_by', $user->id)
                              ->whereIn('status', ['APPROVED', 'REJECTED']);
                        });
                    });
                    return;
                }

                // Requester: chỉ xem phiếu của mình
                if ($user->hasRole('requester')) {
                    $q->where('created_by', $user->id);
                    return;
                }

                // Purchasing Admin: xem phiếu được gán (khác DRAFT) HOẶC tất cả phiếu APPROVED
                if ($user->hasRole('purchasing_admin')) {
                    $q->where(function($query) use ($user) {
                        // Phiếu được gán cho mình (khác DRAFT)
                        $query->where(function($q) use ($user) {
                            $q->where('purchasing_admin_id', $user->id)
                              ->where('status', 'APPROVED');
                        });
                    });
                    return;
                }

                // Accountant: xem TẤT CẢ phiếu APPROVED (để làm kế toán)
                if ($user->hasRole('accountant')) {
                    $q->where('status', 'APPROVED');
                    return;
                }

                // Các role duyệt khác: xem phiếu cần duyệt hoặc đã duyệt
                $approverRoles = ['internal_control', 'national_purchasing', 'tech_board', 'bod'];
                if ($user->hasAnyRole($approverRoles)) {
                    $q->where(function($query) use ($user) {
                        // Phiếu cần mình duyệt
                        $query->whereHas('currentStep', function($q) use ($user) {
                            $q->where(function($q2) use ($user) {
                                // Trường hợp 1: Đã được assign cụ thể cho user
                                $q2->where('assignee_user_id', $user->id)
                                   // Trường hợp 2: Chưa assign user cụ thể, nhưng role khớp
                                   ->orWhere(function($q3) use ($user) {
                                       $q3->whereNull('assignee_user_id');

                                       // Check role của user và match với assignee_role
                                       $userRoles = $user->roles->pluck('name')->toArray();
                                       $q3->whereIn('assignee_role', $userRoles);
                                   });
                            });
                        })
                        // Hoặc phiếu mà mình đã duyệt/từ chối
                        ->orWhereHas('approvalSteps', function($q) use ($user) {
                            $q->where('acted_by', $user->id)
                              ->whereIn('status', ['APPROVED', 'REJECTED']);
                        });
                    });
                    return;
                }

                // Mặc định: không xem gì
                $q->whereRaw('1 = 0');
            })
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->workflow_type, fn($q, $type) => $q->where('workflow_type', $type))
            ->when($request->department_id, function($q, $deptId) {
                // Optimize: filter by creator's department_id directly instead of nested whereHas
                $q->whereHas('creator', fn($query) => $query->where('department_id', $deptId));
            })
            ->when($request->created_by, fn($q, $creatorId) => $q->where('created_by', $creatorId))
            ->when($request->search, function($q, $search) {
                $q->where(function($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                          ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10); // Server-side pagination - 10 phiếu/trang

        $departments = \App\Models\Department::active()
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        // Lấy danh sách người tạo phiếu (optimize với join)
        $creators = \App\Models\User::select('users.id', 'users.name')
            ->join('vendor_reports', 'users.id', '=', 'vendor_reports.created_by')
            ->distinct()
            ->orderBy('users.name')
            ->get();

        return Inertia::render('VendorReports/Index', [
            'reports' => [
                'data' => VendorReportResource::collection($reports->items())->resolve(),
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
                'from' => $reports->firstItem(),
                'to' => $reports->lastItem(),
            ],
            'departments' => $departments,
            'creators' => $creators,
            'workflows' => VendorReport::getWorkflowTypesWithLabels(),
            'statuses' => VendorReport::getStatusLabels(),
            'filters' => $request->only(['status', 'workflow_type', 'department_id', 'created_by', 'search']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', VendorReport::class);

        // Get users with purchasing_admin role
        $purchasingAdmins = \App\Models\User::role('purchasing_admin')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Add current user if they have requester role
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->hasRole('requester')) {
            // Check if current user is not already in the list
            if (!$purchasingAdmins->contains('id', $currentUser->id)) {
                $purchasingAdmins->push([
                    'id' => $currentUser->id,
                    'name' => $currentUser->name,
                    'email' => $currentUser->email,
                ]);
                // Re-sort by name
                $purchasingAdmins = $purchasingAdmins->sortBy('name')->values();
            }
        }

        return Inertia::render('VendorReports/Create', [
            'workflows' => VendorReport::getWorkflowTypesWithLabels(),
            'purchasingAdmins' => $purchasingAdmins,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVendorReportRequest $request)
    {
        $this->authorize('create', VendorReport::class);

        $validated = $request->validated();
        $validated['created_by'] = auth()->id();
        $validated['status'] = 'DRAFT';

        // Create report first (code will be generated on submit)
        $report = VendorReport::create($validated);

        // Collect uploaded files info
        $uploadedFiles = [];

        // Handle file uploads
        if ($request->hasFile('report_image')) {
            $file = $request->file('report_image');
            $this->uploadFile($report, $file, 'REPORT_IMAGE');
            $uploadedFiles[] = $this->activityService->formatFileInfo('REPORT_IMAGE', $file->getClientOriginalName(), $file->getSize());
        }

        if ($request->hasFile('quotation_files')) {
            foreach ($request->file('quotation_files') as $file) {
                $this->uploadFile($report, $file, 'QUOTATION');
                $uploadedFiles[] = $this->activityService->formatFileInfo('QUOTATION', $file->getClientOriginalName(), $file->getSize());
            }
        }

        if ($request->hasFile('boq_files')) {
            foreach ($request->file('boq_files') as $file) {
                $this->uploadFile($report, $file, 'BOQ');
                $uploadedFiles[] = $this->activityService->formatFileInfo('BOQ', $file->getClientOriginalName(), $file->getSize());
            }
        }

        // Log creation with all uploaded files
        $this->activityService->logCreated($report, $uploadedFiles);

        return redirect()->route('vendor-reports.index')
            ->with('success', 'Tạo phiếu thành công');
    }

    /**
     * Upload file for vendor report
     */
    private function uploadFile(VendorReport $report, $file, string $type): void
    {
        $path = $file->store('vendor-reports', 'private');

        $report->files()->create([
            'type' => $type,
            'disk' => 'private',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(VendorReport $vendorReport)
    {
        $this->authorize('view', $vendorReport);

        $vendorReport->load([
            'creator.department',
            'purchasingAdmin',
            'approvalSteps.assigneeUser',
            'approvalSteps.actedByUser',
            'files.uploader',
            'parent',
            'children', // Cần load để Policy kiểm tra canBeCloned()
        ]);

        // Get approval steps as resource
        $approvalSteps = \App\Http\Resources\VendorReportApprovalStepResource::collection(
            $vendorReport->approvalSteps->sortBy('step_order')
        );

        // Get current step if in approval
        $currentStep = $vendorReport->currentStep;

        // Check permissions
        $canEdit = auth()->user()->can('update', $vendorReport);
        $canSubmit = auth()->user()->can('submit', $vendorReport);
        $canApprove = auth()->user()->can('approve', $vendorReport);
        $canClone = auth()->user()->can('clone', $vendorReport);
        $canCancel = auth()->user()->can('cancel', $vendorReport);

        // Get selectable approvers if current step requires selection
        $selectableApprovers = [];
        if ($currentStep && $currentStep->requires_selection) {
            $roleKey = $currentStep->selection_role;

            // Get users by role using Spatie role() query
            $selectableApprovers = \App\Models\User::role($roleKey)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        // Get files
        $files = $vendorReport->files->map(function ($file) {
            return [
                'id' => $file->id,
                'type' => $file->type,
                'original_filename' => $file->original_name,
                'file_size' => $file->size,
                'created_at' => $file->created_at->toISOString(),
            ];
        });

        // Get activities with detailed formatting
        $activities = \Spatie\Activitylog\Models\Activity::where('subject_type', VendorReport::class)
            ->where('subject_id', $vendorReport->id)
            ->with('causer')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'description_label' => VendorReportActivityService::getActivityLabels()[$activity->description] ?? $activity->description,
                    'description_formatted' => VendorReportActivityService::formatActivityDescription($activity),
                    'created_at' => $activity->created_at->toISOString(),
                    'causer' => $activity->causer ? [
                        'id' => $activity->causer->id,
                        'name' => $activity->causer->name,
                    ] : null,
                    'properties' => $activity->properties ?? [],
                ];
            });

        return Inertia::render('VendorReports/Show', [
            'report' => new VendorReportResource($vendorReport),
            'approvalSteps' => $approvalSteps->resolve(),
            'currentStep' => $currentStep ? new \App\Http\Resources\VendorReportApprovalStepResource($currentStep) : null,
            'selectableApprovers' => $selectableApprovers,
            'files' => $files,
            'activities' => $activities,
            'canEdit' => $canEdit,
            'canSubmit' => $canSubmit,
            'canApprove' => $canApprove,
            'canClone' => $canClone,
            'canCancel' => $canCancel,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(VendorReport $vendorReport)
    {
        $this->authorize('update', $vendorReport);

        // Load files relationship
        $vendorReport->load('files');

        // Get users with purchasing_admin role
        $purchasingAdmins = \App\Models\User::role('purchasing_admin')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Add current user if they have requester role
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->hasRole('requester')) {
            // Check if current user is not already in the list
            if (!$purchasingAdmins->contains('id', $currentUser->id)) {
                $purchasingAdmins->push([
                    'id' => $currentUser->id,
                    'name' => $currentUser->name,
                    'email' => $currentUser->email,
                ]);
                // Re-sort by name
                $purchasingAdmins = $purchasingAdmins->sortBy('name')->values();
            }
        }

        return Inertia::render('VendorReports/Edit', [
            'report' => new VendorReportResource($vendorReport),
            'workflows' => VendorReport::getWorkflowTypesWithLabels(),
            'purchasingAdmins' => $purchasingAdmins,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVendorReportRequest $request, VendorReport $vendorReport)
    {
        $this->authorize('update', $vendorReport);

        $validated = $request->validated();

        // Track changes for detailed logging
        $changes = [];
        $original = $vendorReport->getOriginal();

        if (isset($validated['title']) && $original['title'] !== $validated['title']) {
            $changes['title'] = ['old' => $original['title'], 'new' => $validated['title']];
        }
        if (isset($validated['workflow_type']) && $original['workflow_type'] !== $validated['workflow_type']) {
            $changes['workflow_type'] = ['old' => $original['workflow_type'], 'new' => $validated['workflow_type']];
        }
        if (isset($validated['purchasing_admin_id']) && $original['purchasing_admin_id'] != $validated['purchasing_admin_id']) {
            $changes['purchasing_admin_id'] = ['old' => $original['purchasing_admin_id'], 'new' => $validated['purchasing_admin_id']];
        }

        // Update basic fields
        $vendorReport->update($validated);

        // Collect deleted files info
        $deletedFiles = [];

        // Handle file deletions
        if ($request->has('delete_files') && is_array($request->delete_files)) {
            foreach ($request->delete_files as $fileId) {
                $file = $vendorReport->files()->find($fileId);
                if ($file) {
                    $deletedFiles[] = $this->activityService->formatFileInfo($file->type, $file->original_name);
                    Storage::disk($file->disk)->delete($file->path);
                    $file->delete();
                }
            }
        }

        // Collect uploaded files info
        $uploadedFiles = [];

        // Handle file uploads
        if ($request->hasFile('report_image')) {
            // Delete old report image
            $oldImage = $vendorReport->files()->where('type', 'REPORT_IMAGE')->first();
            if ($oldImage) {
                $deletedFiles[] = $this->activityService->formatFileInfo($oldImage->type, $oldImage->original_name);
                Storage::disk($oldImage->disk)->delete($oldImage->path);
                $oldImage->delete();
            }
            // Upload new image
            $file = $request->file('report_image');
            $this->uploadFile($vendorReport, $file, 'REPORT_IMAGE');
            $uploadedFiles[] = $this->activityService->formatFileInfo('REPORT_IMAGE', $file->getClientOriginalName(), $file->getSize());
        }

        if ($request->hasFile('quotation_files')) {
            foreach ($request->file('quotation_files') as $file) {
                $this->uploadFile($vendorReport, $file, 'QUOTATION');
                $uploadedFiles[] = $this->activityService->formatFileInfo('QUOTATION', $file->getClientOriginalName(), $file->getSize());
            }
        }

        if ($request->hasFile('boq_files')) {
            foreach ($request->file('boq_files') as $file) {
                $this->uploadFile($vendorReport, $file, 'BOQ');
                $uploadedFiles[] = $this->activityService->formatFileInfo('BOQ', $file->getClientOriginalName(), $file->getSize());
            }
        }

        // Log update if there are any changes
        if (count($changes) > 0 || count($deletedFiles) > 0 || count($uploadedFiles) > 0) {
            $this->activityService->logUpdated($vendorReport, $changes, $deletedFiles, $uploadedFiles);
        }

        return redirect()->route('vendor-reports.show', $vendorReport)
            ->with('success', 'Cập nhật phiếu thành công');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VendorReport $vendorReport)
    {
        $this->authorize('delete', $vendorReport);

        $this->activityService->logDeleted($vendorReport);

        $vendorReport->delete();

        return redirect()->route('vendor-reports.index')
            ->with('success', 'Xóa phiếu thành công');
    }

    /**
     * Submit phiếu để bắt đầu quy trình duyệt
     */
    public function submit(VendorReport $vendorReport)
    {
        $this->authorize('submit', $vendorReport);

        try {
            $this->submissionService->submit($vendorReport);

            return redirect()->route('vendor-reports.show', $vendorReport)
                ->with('success', 'Gửi phiếu thành công');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Approve step hiện tại
     */
    public function approve(Request $request, VendorReport $vendorReport)
    {
        $this->authorize('approve', $vendorReport);

        $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'selected_next_approver_id' => ['nullable', 'exists:users,id'],
        ], [
            'selected_next_approver_id.exists' => 'Người duyệt được chọn không tồn tại',
        ]);

        try {
            $this->approvalService->approve(
                $vendorReport,
                auth()->user(),
                $request->note,
                $request->selected_next_approver_id
            );

            return redirect()->route('vendor-reports.show', $vendorReport)
                ->with('success', 'Duyệt phiếu thành công');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reject phiếu
     */
    public function reject(Request $request, VendorReport $vendorReport)
    {
        $this->authorize('reject', $vendorReport);

        $request->validate([
            'rejection_note' => ['required', 'string', 'max:1000'],
        ], [
            'rejection_note.required' => 'Lý do từ chối là bắt buộc',
        ]);

        try {
            $this->approvalService->reject(
                $vendorReport,
                auth()->user(),
                $request->rejection_note
            );

            return redirect()->route('vendor-reports.show', $vendorReport)
                ->with('success', 'Từ chối phiếu thành công');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Cancel phiếu (admin only)
     */
    public function cancel(Request $request, VendorReport $vendorReport)
    {
        $this->authorize('cancel', $vendorReport);

        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Lý do hủy là bắt buộc',
        ]);

        try {
            $this->approvalService->cancel(
                $vendorReport,
                auth()->user(),
                $request->reason
            );

            return redirect()->route('vendor-reports.show', $vendorReport)
                ->with('success', 'Hủy phiếu thành công');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Clone phiếu từ phiếu bị từ chối
     */
    public function clone(VendorReport $vendorReport)
    {
        $this->authorize('clone', $vendorReport);

        if (!$vendorReport->canBeCloned()) {
            return back()->with('error', 'Phiếu này không thể clone (đã bị từ chối và đã có phiếu clone hoặc chưa bị từ chối)');
        }

        // Tính revision_number: parent revision + 1
        $newRevisionNumber = $vendorReport->revision_number + 1;
        $rootId = $vendorReport->root_id ?? $vendorReport->id;

        // Lấy title gốc (bỏ prefix [Lần X] nếu có)
        $originalTitle = preg_replace('/^\[Lần \d+\]\s*/', '', $vendorReport->title);
        $newTitle = "[Lần {$newRevisionNumber}] {$originalTitle}";

        $newReport = VendorReport::create([
            'title' => $newTitle,
            'workflow_type' => $vendorReport->workflow_type,
            'purchasing_admin_id' => $vendorReport->purchasing_admin_id,
            'created_by' => auth()->id(),
            'status' => 'DRAFT',
            'parent_id' => $vendorReport->id,
            'root_id' => $rootId,
            'revision_number' => $newRevisionNumber,
        ]);

        // Copy activity logs từ tất cả các phiếu cha (root -> parent)
        $ancestors = collect([$vendorReport]);
        if ($vendorReport->parent_id) {
            $ancestors = $ancestors->merge($vendorReport->getAllAncestors());
        }

        // Reverse để log từ cũ đến mới (root -> parent)
        foreach ($ancestors->reverse() as $ancestor) {
            $activities = \Spatie\Activitylog\Models\Activity::where('subject_type', VendorReport::class)
                ->where('subject_id', $ancestor->id)
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($activities as $activity) {
                activity()
                    ->performedOn($newReport)
                    ->causedBy($activity->causer_id)
                    ->withProperties(array_merge(
                        $activity->properties->toArray(),
                        ['copied_from_revision' => $ancestor->revision_number]
                    ))
                    ->createdAt($activity->created_at)
                    ->log($activity->description);
            }
        }

        $this->activityService->logClonedFromRejected($newReport, $vendorReport);

        return redirect()->route('vendor-reports.edit', $newReport)
            ->with('success', "Tạo phiếu lần {$newRevisionNumber} thành công");
    }

    /**
     * View file (inline in browser)
     */
    public function viewFile($fileId)
    {
        $file = \App\Models\VendorReportFile::findOrFail($fileId);

        // Check if user can view the related report
        $this->authorize('view', $file->report);

        // Check if file exists
        if (!\Storage::disk('private')->exists($file->path)) {
            abort(404, 'File không tồn tại');
        }

        $filePath = \Storage::disk('private')->path($file->path);
        $mimeType = \Storage::disk('private')->mimeType($file->path);

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $file->original_name . '"'
        ]);
    }

    /**
     * Download file
     */
    public function downloadFile($fileId)
    {
        $file = \App\Models\VendorReportFile::findOrFail($fileId);

        // Check if user can view the related report
        $this->authorize('view', $file->report);

        // Check if file exists
        if (!\Storage::disk($file->disk)->exists($file->path)) {
            abort(404, 'File không tồn tại');
        }

        return \Storage::disk($file->disk)->download(
            $file->path,
            $file->original_name
        );
    }
}
