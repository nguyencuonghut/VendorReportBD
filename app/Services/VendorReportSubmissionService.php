<?php

namespace App\Services;

use App\Models\VendorReport;
use App\Notifications\VendorReportSubmitted;
use Illuminate\Support\Facades\DB;

class VendorReportSubmissionService
{
    public function __construct(
        private VendorReportCodeGenerator $codeGenerator,
        private VendorReportWorkflowBuilder $workflowBuilder,
        private VendorReportActivityService $activityService
    ) {}

    /**
     * Submit phiếu: Generate code + Build workflow steps
     *
     * @param VendorReport $report
     * @return VendorReport
     * @throws \Exception
     */
    public function submit(VendorReport $report): VendorReport
    {
        if ($report->status !== 'DRAFT') {
            throw new \Exception('Chỉ có thể gửi phiếu ở trạng thái Nháp');
        }

        // Validate: Người tạo phải có department
        $creator = $report->creator()->with('department')->first();

        if (!$creator || !$creator->department_id) {
            throw new \Exception('Người tạo phiếu phải thuộc một phòng ban');
        }

        $department = $creator->department;

        if (!$department->is_active) {
            throw new \Exception("Phòng {$department->name} đang không hoạt động");
        }

        // Chỉ validate head_user_id nếu workflow có bước DEPT_HEAD
        $workflowConfig = config("vendor_report_workflows.{$report->workflow_type}");
        $hasDeptHeadStep = collect($workflowConfig)->contains('step_key', 'DEPT_HEAD');

        if ($hasDeptHeadStep && !$department->head_user_id) {
            throw new \Exception("Phòng {$department->name} chưa có Trưởng phòng");
        }

        return DB::transaction(function () use ($report, $creator) {
            // 1. Generate code nếu chưa có
            if (!$report->code) {
                $code = $this->codeGenerator->generate($creator->department_id);
                $report->code = $code;
            }

            // 2. Update status
            $report->status = 'IN_APPROVAL';
            $report->submitted_at = now();
            $report->save();

            // 3. Build workflow steps
            $this->workflowBuilder->buildSteps($report);

            // 4. Auto-approve các steps mà creator có quyền duyệt
            $this->autoApproveCreatorSteps($report, $creator);

            // 5. Reload để lấy current_step_id đã được update
            $report->refresh();

            // 6. Log activity
            $this->activityService->logSubmitted($report);

            // 7. Send notification to first step assignee(s)
            if ($report->currentStep) {
                if ($report->currentStep->assigneeUser) {
                    // Đã có assignee cụ thể → gửi cho người đó
                    $report->currentStep->assigneeUser->notify(new VendorReportSubmitted($report));
                } elseif ($report->currentStep->assignee_role) {
                    // Chưa có assignee cụ thể, chỉ có role → gửi cho TẤT CẢ users có role đó
                    $usersWithRole = \App\Models\User::role($report->currentStep->assignee_role)->get();
                    foreach ($usersWithRole as $user) {
                        $user->notify(new VendorReportSubmitted($report));
                    }
                }
            }

            return $report;
        });
    }

    /**
     * Auto-approve các steps mà creator có quyền duyệt
     * Ví dụ: Trưởng phòng tạo phiếu → bước DEPT_HEAD tự động approved
     */
    private function autoApproveCreatorSteps($report, $creator): void
    {
        $steps = $report->approvalSteps()->orderBy('step_order')->get();
        $creatorRoles = $creator->roles->pluck('name')->toArray();

        foreach ($steps as $step) {
            // Check xem creator có quyền duyệt step này không
            $canApprove = false;

            // Trường hợp 1: Assignee cụ thể là creator
            if ($step->assignee_user_id === $creator->id) {
                $canApprove = true;
            }

            // Trường hợp 2: Assignee role khớp với role của creator
            if (!$step->assignee_user_id && $step->assignee_role && in_array($step->assignee_role, $creatorRoles)) {
                $canApprove = true;
            }

            if ($canApprove) {
                // Tự động approve step này
                $step->update([
                    'status' => 'APPROVED',
                    'assignee_user_id' => $creator->id, // Gán assignee nếu chưa có
                    'acted_by' => $creator->id,
                    'acted_at' => now(),
                    'note' => 'Tự động phê duyệt (người tạo phiếu có quyền duyệt bước này)',
                ]);

                // Log activity
                $this->activityService->logApproved($report, $creator, 'Tự động phê duyệt', $step->step_order);
            } else {
                // Gặp step không thể auto-approve → dừng lại
                // Update current_step_id
                $report->update(['current_step_id' => $step->id]);
                break;
            }
        }

        // Nếu tất cả steps đều auto-approved → phiếu APPROVED luôn
        $allApproved = $report->approvalSteps()->where('status', '!=', 'APPROVED')->count() === 0;
        if ($allApproved) {
            $report->update([
                'status' => 'APPROVED',
                'approved_at' => now(),
                'current_step_id' => null,
            ]);
            $this->activityService->logCompleted($report);
        }
    }
}
