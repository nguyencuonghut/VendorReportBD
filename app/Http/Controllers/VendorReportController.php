<?php

namespace App\Http\Controllers;

use App\Models\VendorReport;
use App\Http\Requests\StoreVendorReportRequest;
use App\Http\Requests\UpdateVendorReportRequest;
use App\Http\Resources\VendorReportResource;
use App\Services\VendorReportSubmissionService;
use App\Services\VendorReportApprovalService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VendorReportController extends Controller
{
    public function __construct(
        private VendorReportSubmissionService $submissionService,
        private VendorReportApprovalService $approvalService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', VendorReport::class);

        $reports = VendorReport::query()
            ->with(['creator', 'purchasingAdmin', 'currentStep', 'department'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->workflow_type, fn($q, $type) => $q->where('workflow_type', $type))
            ->when($request->department_id, fn($q, $deptId) => $q->where('department_id', $deptId))
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
            'filters' => $request->only(['status', 'workflow_type', 'department_id', 'search']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', VendorReport::class);

        $purchasingAdmins = \App\Models\User::role('purchasing_admin')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('VendorReports/Create', [
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

        $report = VendorReport::create($validated);

        activity()
            ->performedOn($report)
            ->causedBy(auth()->user())
            ->log('report_created');

        return redirect()->route('vendor-reports.index')
            ->with('success', 'Tạo phiếu thành công');
    }

    /**
     * Display the specified resource.
     */
    public function show(VendorReport $vendorReport)
    {
        $this->authorize('view', $vendorReport);

        $vendorReport->load([
            'creator',
            'purchasingAdmin',
            'department',
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
            $role = $currentStep->selection_role;
            $selectableApprovers = \App\Models\User::role($role)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        return Inertia::render('VendorReports/Show', [
            'report' => new VendorReportResource($vendorReport),
            'approvalSteps' => $approvalSteps->resolve(),
            'currentStep' => $currentStep ? new \App\Http\Resources\VendorReportApprovalStepResource($currentStep) : null,
            'selectableApprovers' => $selectableApprovers,
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

        $purchasingAdmins = \App\Models\User::role('purchasing_admin')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('VendorReports/Edit', [
            'report' => new VendorReportResource($vendorReport),
            'purchasingAdmins' => $purchasingAdmins,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVendorReportRequest $request, VendorReport $vendorReport)
    {
        $this->authorize('update', $vendorReport);

        $vendorReport->update($request->validated());

        activity()
            ->performedOn($vendorReport)
            ->causedBy(auth()->user())
            ->log('report_updated');

        return redirect()->route('vendor-reports.index')
            ->with('success', 'Cập nhật phiếu thành công');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VendorReport $vendorReport)
    {
        $this->authorize('delete', $vendorReport);

        $vendorReport->delete();

        activity()
            ->performedOn($vendorReport)
            ->causedBy(auth()->user())
            ->log('report_deleted');

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

        activity()
            ->performedOn($newReport)
            ->causedBy(auth()->user())
            ->withProperties(['cloned_from' => $vendorReport->id])
            ->log('report_cloned_from_rejected');

        return redirect()->route('vendor-reports.edit', $newReport)
            ->with('success', 'Tạo phiếu mới từ phiếu bị từ chối thành công');
    }
}
