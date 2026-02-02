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

        $reports = VendorReport::query()
            ->with(['creator.department', 'purchasingAdmin', 'currentStep'])
            ->when($request->filter === 'my-reports', function($q) use ($user) {
                // Filter: Phiếu của tôi - chỉ xem phiếu mình tạo
                $q->where('created_by', $user->id);
            })
            ->when($request->filter === 'pending-approval', function($q) use ($user) {
                // Filter: Chờ phê duyệt - phiếu đang chờ mình duyệt
                $q->where('status', 'IN_APPROVAL')
                  ->whereHas('currentStep', function($query) use ($user) {
                      $query->where(function($q) use ($user) {
                          // Trường hợp 1: Đã được assign cụ thể cho user
                          $q->where('assignee_user_id', $user->id)
                            // Trường hợp 2: Chưa assign user cụ thể, nhưng role khớp
                            ->orWhere(function($q2) use ($user) {
                                $q2->whereNull('assignee_user_id');

                                // Check role của user và match với assignee_role
                                $userRoles = $user->roles->pluck('name')->toArray();
                                $q2->whereIn('assignee_role', $userRoles);
                            });
                      });
                  });
            })
            ->when(!$request->filter, function($q) use ($user) {
                // Không có filter: áp dụng logic phân quyền mặc định

                // Admin system: xem tất cả
                if ($user->hasRole('admin_system')) {
                    return;
                }

                // Requester: chỉ xem phiếu của mình
                if ($user->hasRole('requester')) {
                    $q->where('created_by', $user->id);
                    return;
                }

                // Purchasing Admin: xem phiếu được gán (trừ DRAFT)
                if ($user->hasRole('purchasing_admin')) {
                    $q->where('purchasing_admin_id', $user->id)
                      ->where('status', '!=', 'DRAFT');
                    return;
                }

                // Trưởng phòng: xem phiếu của phòng mình hoặc phiếu cần duyệt
                if ($user->department_id && $user->department->head_user_id === $user->id) {
                    $q->where(function($query) use ($user) {
                        // Phiếu của nhân viên cùng phòng
                        $query->whereHas('creator', fn($q) => $q->where('department_id', $user->department_id))
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
                              });
                    });
                    return;
                }

                // Approver roles: xem phiếu cần mình duyệt hoặc đã duyệt
                if ($user->hasAnyRole(['internal_control', 'national_purchasing', 'tech_board', 'bod'])) {
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
                $q->whereHas('creator.department', fn($query) => $query->where('id', $deptId));
            })
            ->when($request->search, function($q, $search) {
                $q->where(function($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                          ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get();

        $departments = \App\Models\Department::active()
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('VendorReports/Index', [
            'reports' => VendorReportResource::collection($reports)->resolve(),
            'departments' => $departments,
            'workflows' => VendorReport::getWorkflowTypesWithLabels(),
            'statuses' => VendorReport::getStatusLabels(),
            'filters' => $request->only(['filter', 'status', 'workflow_type', 'department_id', 'search']),
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
     * Clone phiếu từ phiếu bị từ chối
     */
    public function clone(VendorReport $vendorReport)
    {
        $this->authorize('clone', $vendorReport);

        if (!$vendorReport->canBeCloned()) {
            return back()->with('error', 'Chỉ có thể tạo phiếu mới từ phiếu bị từ chối');
        }

        $newReport = VendorReport::create([
            'title' => $vendorReport->title . ' (Copy)',
            'workflow_type' => $vendorReport->workflow_type,
            'purchasing_admin_id' => $vendorReport->purchasing_admin_id,
            'created_by' => auth()->id(),
            'status' => 'DRAFT',
            'parent_id' => $vendorReport->id,
            'root_id' => $vendorReport->root_id ?? $vendorReport->id,
        ]);

        $this->activityService->logClonedFromRejected($newReport, $vendorReport);

        return redirect()->route('vendor-reports.edit', $newReport)
            ->with('success', 'Tạo phiếu mới từ phiếu bị từ chối thành công');
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
