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
     * @throws \Exception
     */
    public function submit(VendorReport $report): VendorReport
    {
        return DB::transaction(function () use ($report) {
            // Read the current state only after acquiring the report lock.
            $report = VendorReport::query()->lockForUpdate()->findOrFail($report->id);

            if ($report->status !== 'DRAFT') {
                throw new \Exception('Chỉ có thể gửi phiếu ở trạng thái Nháp');
            }

            // Builder owns workflow/department validation; every write rolls back together.
            $this->workflowBuilder->buildSteps($report);
            $creator = $report->creator;

            // Generate code nếu chưa có
            if (! $report->code) {
                $code = $this->codeGenerator->generate($creator->department_id);
                $report->code = $code;
            }

            // Update status
            $report->status = 'IN_APPROVAL';
            $report->submitted_at = now();
            $report->save();

            // Workflow mới luôn yêu cầu hai hành động duyệt thủ công.
            if ($report->workflow_type !== 'INTERNAL_CONTROL_BOD') {
                $this->autoApproveCreatorSteps($report, $creator);
            }

            // Reload để lấy current_step_id đã được update
            $report->refresh();

            // Log activity
            $this->activityService->logSubmitted($report);

            // Dispatch only after the outermost transaction commits successfully.
            $recipients = $report->currentStep?->pendingApprovers()->get();
            DB::afterCommit(function () use ($report, $recipients) {
                foreach ($recipients ?? [] as $user) {
                    $user->notify(new VendorReportSubmitted($report));
                }
            });

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
        foreach ($steps as $step) {
            if ($step->canActOn($creator)) {
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
