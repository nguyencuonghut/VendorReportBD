<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorReport;

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
        return VendorReport::visibleTo($user)->whereKey($vendorReport->getKey())->exists();
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
        // Chỉ creator có thể clone từ phiếu REJECTED
        // Mỗi phiếu chỉ được clone 1 lần (kiểm tra trong canBeCloned() method)
        return $user->is_active &&
               $vendorReport->status === 'REJECTED' &&
               $user->id === $vendorReport->created_by &&
               $vendorReport->canBeCloned(); // Kiểm tra không có phiếu con
    }

    /**
     * Determine whether the user can cancel the model.
     */
    public function cancel(User $user, VendorReport $vendorReport): bool
    {
        return $user->is_active && $user->hasRole('admin_system');
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
        return $vendorReport->currentStep?->canActOn($user) ?? false;
    }
}
