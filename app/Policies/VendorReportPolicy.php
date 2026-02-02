<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorReport;
use Illuminate\Auth\Access\Response;

class VendorReportPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VendorReport $vendorReport): bool
    {
        if (!$user->is_active) {
            return false;
        }

        // Admin system: xem tất cả
        if ($user->hasRole('admin_system')) {
            return true;
        }

        // Requester: chỉ xem phiếu của mình
        if ($user->hasRole('requester') && $user->id === $vendorReport->created_by) {
            return true;
        }

        // Purchasing Admin: xem các phiếu được gán (trừ DRAFT)
        if ($user->hasRole('purchasing_admin') &&
            $user->id === $vendorReport->purchasing_admin_id &&
            $vendorReport->status !== 'DRAFT') {
            return true;
        }

        // Trưởng phòng: xem phiếu của nhân viên trong phòng và phiếu cần mình duyệt
        if ($this->isDepartmentHead($user)) {
            // Xem phiếu của nhân viên trong phòng
            if ($this->isReportFromSameDepartment($user, $vendorReport)) {
                return true;
            }
            // Hoặc phiếu cần mình duyệt
            if ($this->isCurrentApprover($user, $vendorReport)) {
                return true;
            }
        }

        // Approver roles: xem phiếu cần mình duyệt hoặc đã duyệt
        if ($user->hasAnyRole(['internal_control', 'national_purchasing', 'tech_board', 'bod'])) {
            // Phiếu cần mình duyệt
            if ($this->isCurrentApprover($user, $vendorReport)) {
                return true;
            }
            // Phiếu mà mình đã duyệt/từ chối
            if ($this->hasParticipatedInApproval($user, $vendorReport)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // User phải active và có department
        return $user->is_active && $user->department_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VendorReport $vendorReport): bool
    {
        // Chỉ creator và status = DRAFT
        return $user->is_active &&
               $user->id === $vendorReport->created_by &&
               $vendorReport->status === 'DRAFT';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VendorReport $vendorReport): bool
    {
        // Chỉ creator và status = DRAFT
        return $user->is_active &&
               $user->id === $vendorReport->created_by &&
               $vendorReport->status === 'DRAFT';
    }

    /**
     * Determine whether the user can submit the model.
     */
    public function submit(User $user, VendorReport $vendorReport): bool
    {
        return $user->is_active &&
               $user->id === $vendorReport->created_by &&
               $vendorReport->status === 'DRAFT';
    }

    /**
     * Determine whether the user can approve the model.
     */
    public function approve(User $user, VendorReport $vendorReport): bool
    {
        return $user->is_active &&
               $vendorReport->status === 'IN_APPROVAL' &&
               $this->isCurrentApprover($user, $vendorReport);
    }

    /**
     * Determine whether the user can reject the model.
     */
    public function reject(User $user, VendorReport $vendorReport): bool
    {
        return $user->is_active &&
               $vendorReport->status === 'IN_APPROVAL' &&
               $this->isCurrentApprover($user, $vendorReport);
    }

    /**
     * Determine whether the user can clone the model.
     */
    public function clone(User $user, VendorReport $vendorReport): bool
    {
        // Creator hoặc purchasing admin có thể clone từ phiếu REJECTED
        return $user->is_active &&
               $vendorReport->status === 'REJECTED' &&
               (
                   $user->id === $vendorReport->created_by ||
                   $user->id === $vendorReport->purchasing_admin_id
               );
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VendorReport $vendorReport): bool
    {
        return $user->hasRole('admin_system');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VendorReport $vendorReport): bool
    {
        return $user->hasRole('admin_system');
    }

    /**
     * Helper: Kiểm tra user có phải assignee của current step không
     */
    private function isCurrentApprover(User $user, VendorReport $vendorReport): bool
    {
        $currentStep = $vendorReport->currentStep;

        if (!$currentStep) {
            return false;
        }

        // Trường hợp 1: Đã được assign cụ thể cho user
        if ($currentStep->assignee_user_id === $user->id) {
            return true;
        }

        // Trường hợp 2: Chưa assign user cụ thể, nhưng role khớp
        if (!$currentStep->assignee_user_id && $currentStep->assignee_role) {
            return $user->hasRole($currentStep->assignee_role);
        }

        return false;
    }

    /**
     * Helper: Kiểm tra user có tham gia duyệt phiếu này không
     */
    private function hasParticipatedInApproval(User $user, VendorReport $vendorReport): bool
    {
        $participated = $vendorReport->approvalSteps()
            ->where('acted_by', $user->id)
            ->whereIn('status', ['APPROVED', 'REJECTED'])
            ->exists();

        return $participated;
    }

    /**
     * Helper: Kiểm tra user có phải trưởng phòng không
     */
    private function isDepartmentHead(User $user): bool
    {
        if (!$user->department_id) {
            return false;
        }

        $department = $user->department;
        return $department && $department->head_user_id === $user->id;
    }

    /**
     * Helper: Kiểm tra phiếu có từ cùng phòng ban với user không
     */
    private function isReportFromSameDepartment(User $user, VendorReport $vendorReport): bool
    {
        if (!$user->department_id) {
            return false;
        }

        $creator = $vendorReport->creator;
        return $creator && $creator->department_id === $user->department_id;
    }
}
