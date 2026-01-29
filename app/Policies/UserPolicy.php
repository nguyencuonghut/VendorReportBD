<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if user can view any users
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can view a specific user
     */
    public function view(User $user, User $model): bool
    {
        // User can view their own profile or if they're Super Admin
        return $user->id === $model->id || $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can create users
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can update a user
     */
    public function update(User $user, User $model): bool
    {
        // User can update their own profile or if they're Super Admin
        return $user->id === $model->id || $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can delete a user
     */
    public function delete(User $user, User $model): bool
    {
        // Cannot delete yourself, only Super Admin can delete others
        return $user->id !== $model->id && $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can restore deleted users
     */
    public function restore(User $user, User $model): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can permanently delete users
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can bulk delete users
     */
    public function bulkDelete(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }
}
