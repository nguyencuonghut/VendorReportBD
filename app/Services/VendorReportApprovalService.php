<?php

namespace App\Services;

use App\Models\VendorReport;
use App\Models\VendorReportApprovalStep;
use App\Models\User;
use App\Notifications\VendorReportApprovalRequired;
use App\Notifications\VendorReportApproved;
use App\Notifications\VendorReportRejected;
use Illuminate\Support\Facades\DB;

class VendorReportApprovalService
{
    public function __construct(
        private VendorReportActivityService $activityService
    ) {}

    /**
     * Approve step hiện tại
     *
     * @param VendorReport $report
     * @param User $approver
     * @param string|null $note
     * @param int|null $selectedNextApproverId - User được chọn cho step tiếp theo (nếu requires_selection = true)
     * @return VendorReport
     * @throws \Exception
     */
    public function approve(
        VendorReport $report,
        User $approver,
        ?string $note = null,
        ?int $selectedNextApproverId = null
    ): VendorReport {
        if ($report->status !== 'IN_APPROVAL') {
            throw new \Exception('Phiếu không ở trạng thái Đang duyệt');
        }

        $currentStep = $report->currentStep;

        if (!$currentStep) {
            throw new \Exception('Không tìm thấy bước duyệt hiện tại');
        }

        if (!$currentStep->canActOn($approver)) {
            throw new \Exception('Bạn không có quyền duyệt bước này');
        }

        // Kiểm tra requires_selection
        if ($currentStep->requires_selection && !$selectedNextApproverId) {
            throw new \Exception('Bước này yêu cầu chọn người duyệt tiếp theo');
        }

        return DB::transaction(function () use ($report, $currentStep, $approver, $note, $selectedNextApproverId) {
            // 1. Update current step
            $currentStep->update([
                'status' => 'APPROVED',
                'acted_by' => $approver->id,
                'acted_at' => now(),
                'note' => $note,
                'selected_next_approver_id' => $selectedNextApproverId,
            ]);

            // 2. Tìm step tiếp theo
            $nextStep = $report->approvalSteps()
                ->where('step_order', '>', $currentStep->step_order)
                ->orderBy('step_order')
                ->first();

            if ($nextStep) {
                // Assign người duyệt cho step tiếp theo
                if ($selectedNextApproverId) {
                    $nextStep->update(['assignee_user_id' => $selectedNextApproverId]);
                }

                // Update current_step_id
                $report->update(['current_step_id' => $nextStep->id]);

                // Send notification to next step assignee
                if ($nextStep->assigneeUser) {
                    $nextStep->assigneeUser->notify(new VendorReportApprovalRequired($report, $nextStep));
                }
            } else {
                // Không còn step nào → APPROVED
                $report->update([
                    'status' => 'APPROVED',
                    'approved_at' => now(),
                    'current_step_id' => null,
                ]);
                $this->activityService->logCompleted($report);

                // Send completion notification to creator and purchasing admin
                $report->creator->notify(new VendorReportApproved($report));
                if ($report->purchasingAdmin && $report->purchasingAdmin->id !== $report->creator->id) {
                    $report->purchasingAdmin->notify(new VendorReportApproved($report));
                }
            }

            // 3. Log activity
            $this->activityService->logApproved($report, $approver, $note, $currentStep->step_order);

            return $report->refresh();
        });
    }

    /**
     * Reject phiếu tại step hiện tại
     *
     * @param VendorReport $report
     * @param User $approver
     * @param string $rejectionNote
     * @return VendorReport
     * @throws \Exception
     */
    public function reject(
        VendorReport $report,
        User $approver,
        string $rejectionNote
    ): VendorReport {
        if ($report->status !== 'IN_APPROVAL') {
            throw new \Exception('Phiếu không ở trạng thái Đang duyệt');
        }

        $currentStep = $report->currentStep;

        if (!$currentStep) {
            throw new \Exception('Không tìm thấy bước duyệt hiện tại');
        }

        if (!$currentStep->canActOn($approver)) {
            throw new \Exception('Bạn không có quyền từ chối bước này');
        }

        return DB::transaction(function () use ($report, $currentStep, $approver, $rejectionNote) {
            // 1. Update current step
            $currentStep->update([
                'status' => 'REJECTED',
                'acted_by' => $approver->id,
                'acted_at' => now(),
                'note' => $rejectionNote,
            ]);

            // 2. Update report → REJECTED (terminal status)
            $report->update([
                'status' => 'REJECTED',
                'rejected_at' => now(),
                'rejected_note' => $rejectionNote,
                'current_step_id' => null,
            ]);

            // 3. Log activity
            $this->activityService->logRejected($report, $approver, $rejectionNote, $currentStep->step_order);

            // 4. Send rejection notification to creator and purchasing admin
            $report->creator->notify(new VendorReportRejected($report, $currentStep, $rejectionNote));
            if ($report->purchasingAdmin && $report->purchasingAdmin->id !== $report->creator->id) {
                $report->purchasingAdmin->notify(new VendorReportRejected($report, $currentStep, $rejectionNote));
            }

            return $report->refresh();
        });
    }
}
