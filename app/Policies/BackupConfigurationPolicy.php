<?php

namespace App\Policies;

use App\Models\User;
use App\Models\BackupConfiguration;
use Illuminate\Auth\Access\HandlesAuthorization;

class BackupConfigurationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if user can view backup page
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can create manual backup
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can download backup
     */
    public function download(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can view backup configurations
     */
    public function viewConfigurations(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can create backup configuration
     */
    public function createConfiguration(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can update backup configuration
     */
    public function updateConfiguration(User $user, BackupConfiguration $configuration): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can delete backup configuration
     */
    public function deleteConfiguration(User $user, BackupConfiguration $configuration): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can toggle backup configuration
     */
    public function toggleConfiguration(User $user, BackupConfiguration $configuration): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can manually run backup configuration
     */
    public function runConfiguration(User $user, BackupConfiguration $configuration): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can manage Google Drive connection
     */
    public function manageGoogleDrive(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }
}
