<?php

namespace App\Services;

use App\Models\VendorReport;
use App\Models\VendorReportApprovalStep;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VendorReportApprovalService
{
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
            } else {
                // Không còn step nào → APPROVED
                $report->update([
                    'status' => 'APPROVED',
                    'approved_at' => now(),
                    'current_step_id' => null,
                ]);
            }

            // 3. Log activity
            activity()
                ->performedOn($report)
                ->causedBy($approver)
                ->withProperties([
                    'step_key' => $currentStep->step_key,
                    'step_order' => $currentStep->step_order,
                    'note' => $note,
                ])
                ->log('step_approved');

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
            activity()
                ->performedOn($report)
                ->causedBy($approver)
                ->withProperties([
                    'step_key' => $currentStep->step_key,
                    'step_order' => $currentStep->step_order,
                    'rejection_note' => $rejectionNote,
                ])
                ->log('step_rejected');

            return $report->refresh();
        });
    }
}
