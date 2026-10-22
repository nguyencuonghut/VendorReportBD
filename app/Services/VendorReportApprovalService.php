<?php

namespace App\Services;

use App\Models\User;
use App\Models\VendorReport;
use App\Models\VendorReportApprovalStep;
use App\Notifications\VendorReportApprovalRequired;
use App\Notifications\VendorReportApproved;
use App\Notifications\VendorReportCanceled;
use App\Notifications\VendorReportRejected;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class VendorReportApprovalService
{
    public function __construct(
        private VendorReportActivityService $activityService
    ) {}

    public function approve(
        VendorReport $report,
        User $approver,
        ?string $note = null,
        ?int $selectedNextApproverId = null
    ): VendorReport {
        return DB::transaction(function () use ($report, $approver, $note, $selectedNextApproverId) {
            $report = $this->lockReport($report);
            [$currentStep, $approver] = $this->lockActionableStep($report, $approver);
            $nextStep = $report->approvalSteps()
                ->where('step_order', '>', $currentStep->step_order)
                ->orderBy('step_order')
                ->lockForUpdate()
                ->first();

            if ($currentStep->requires_selection) {
                if (! $selectedNextApproverId) {
                    throw new \Exception('Bước này yêu cầu chọn người duyệt tiếp theo');
                }
                if (! $nextStep || ! $nextStep->isPending() || ! $currentStep->selection_role) {
                    throw new \Exception('Không tìm thấy bước tiếp theo hợp lệ để chọn người duyệt');
                }
                if (! User::activeWithRole($currentStep->selection_role)->whereKey($selectedNextApproverId)->exists()) {
                    throw new \Exception('Người duyệt được chọn phải đang hoạt động và có đúng vai trò yêu cầu');
                }
            } elseif ($selectedNextApproverId !== null) {
                throw new \Exception('Bước này không cho phép chọn người duyệt tiếp theo');
            }

            if ($nextStep && ! $nextStep->isPending()) {
                throw new \Exception('Bước duyệt tiếp theo không còn ở trạng thái chờ duyệt');
            }

            $currentStep->update([
                'assignee_user_id' => $currentStep->assignee_user_id ?? $approver->id,
                'status' => 'APPROVED',
                'acted_by' => $approver->id,
                'acted_at' => now(),
                'note' => $note,
                'selected_next_approver_id' => $selectedNextApproverId,
            ]);

            if ($nextStep) {
                if ($selectedNextApproverId !== null) {
                    $nextStep->update(['assignee_user_id' => $selectedNextApproverId]);
                }
                $report->update(['current_step_id' => $nextStep->id]);
            } else {
                $report->update([
                    'status' => 'APPROVED',
                    'approved_at' => now(),
                    'current_step_id' => null,
                ]);
                $this->activityService->logCompleted($report);
            }

            $this->activityService->logApproved($report, $approver, $note, $currentStep->step_order);

            DB::afterCommit(function () use ($report, $nextStep) {
                if ($nextStep) {
                    foreach ($nextStep->pendingApprovers()->get() as $recipient) {
                        $recipient->notify(new VendorReportApprovalRequired($report, $nextStep));
                    }
                } else {
                    $this->notifyReportOwners($report, new VendorReportApproved($report));
                }
            });

            return $report->refresh();
        });
    }

    public function reject(
        VendorReport $report,
        User $approver,
        string $rejectionNote
    ): VendorReport {
        return DB::transaction(function () use ($report, $approver, $rejectionNote) {
            $report = $this->lockReport($report);
            [$currentStep, $approver] = $this->lockActionableStep($report, $approver);

            $currentStep->update([
                'assignee_user_id' => $currentStep->assignee_user_id ?? $approver->id,
                'status' => 'REJECTED',
                'acted_by' => $approver->id,
                'acted_at' => now(),
                'note' => $rejectionNote,
            ]);
            $report->update([
                'status' => 'REJECTED',
                'rejected_at' => now(),
                'rejected_note' => $rejectionNote,
                'current_step_id' => null,
            ]);
            $this->activityService->logRejected($report, $approver, $rejectionNote, $currentStep->step_order);

            DB::afterCommit(fn () => $this->notifyReportOwners(
                $report,
                new VendorReportRejected($report, $currentStep, $rejectionNote)
            ));

            return $report->refresh();
        });
    }

    public function cancel(
        VendorReport $report,
        User $admin,
        string $reason
    ): VendorReport {
        return DB::transaction(function () use ($report, $admin, $reason) {
            $report = $this->lockReport($report);
            $admin = User::find($admin->id);
            if (! $admin || ! $admin->is_active || ! $admin->hasRole('admin_system')) {
                throw new \Exception('Bạn không có quyền hủy phiếu');
            }
            if ($report->status === 'CANCELED') {
                throw new \Exception('Phiếu đã ở trạng thái Hủy');
            }

            // All transitions lock the report first, then its steps in workflow order.
            $report->approvalSteps()->orderBy('step_order')->lockForUpdate()->get();
            $report->update([
                'status' => 'CANCELED',
                'current_step_id' => null,
                'canceled_at' => now(),
                'canceled_reason' => $reason,
            ]);
            $report->approvalSteps()->where('status', 'PENDING')->update([
                'status' => 'SKIPPED',
                'acted_by' => null,
                'acted_at' => null,
                'note' => null,
            ]);

            $actorIds = collect([$report->created_by])
                ->merge($report->approvalSteps()->whereNotNull('acted_by')->pluck('acted_by'))
                ->unique()->filter()->values()->all();
            $this->activityService->logCanceled($report, $admin, $reason);

            DB::afterCommit(function () use ($report, $admin, $reason, $actorIds) {
                foreach (User::whereIn('id', $actorIds)->where('is_active', true)->get() as $actor) {
                    $actor->notify(new VendorReportCanceled($report, $admin, $reason));
                }
            });

            return $report->refresh();
        });
    }

    private function lockReport(VendorReport $expected): VendorReport
    {
        $report = VendorReport::whereKey($expected->id)->lockForUpdate()->firstOrFail();
        if ($report->status !== $expected->status || $report->current_step_id !== $expected->current_step_id) {
            throw new \Exception('Phiếu đã được xử lý. Vui lòng tải lại trước khi thực hiện thao tác');
        }

        return $report;
    }

    /** @return array{VendorReportApprovalStep, User} */
    private function lockActionableStep(VendorReport $report, User $actor): array
    {
        if ($report->status !== 'IN_APPROVAL') {
            throw new \Exception('Phiếu không ở trạng thái Đang duyệt');
        }
        $step = $report->approvalSteps()->whereKey($report->current_step_id)->lockForUpdate()->first();
        if (! $step) {
            throw new \Exception('Không tìm thấy bước duyệt hiện tại');
        }
        $actor = User::find($actor->id);
        if (! $actor || ! $step->canActOn($actor)) {
            throw new \Exception('Bạn không có quyền xử lý bước duyệt này');
        }

        return [$step, $actor];
    }

    private function notifyReportOwners(VendorReport $report, Notification $notification): void
    {
        $ownerIds = array_filter([$report->created_by, $report->purchasing_admin_id]);
        foreach (User::whereIn('id', $ownerIds)->where('is_active', true)->get() as $owner) {
            $owner->notify($notification);
        }
    }
}
