<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use App\Http\Resources\UserResource;
use App\Http\Resources\RoleResource;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', User::class);

        // Include soft deleted users for Super Admin
        $users = UserResource::collection(
            User::withTrashed()->with('roles')->withCount('roles')->latest()->get()
        )->resolve();

        $roles = RoleResource::collection(
            Role::all()
        )->resolve();

        return inertia('UserIndex', compact('users', 'roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', User::class);

        return inertia('UserCreate');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();
        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        // Sync roles
        if (isset($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        // Log activity
        activity()
            ->causedBy(Auth::user())
            ->performedOn($user)
            ->withProperties([
                'user_name' => $user->name,
                'user_email' => $user->email,
                'roles' => $validated['roles'] ?? []
            ])
            ->log('Đã tạo người dùng mới');

        return redirect()->route('users.index')->with([
            'message' => 'users.createSuccess',
            'type' => 'success'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);

        return inertia('UserShow', [
            'user' => new UserResource($user)
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $this->authorize('update', $user);

        return inertia('UserEdit', [
            'user' => new UserResource($user)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validated();
        
        // Store old values for logging
        $oldName = $user->name;
        $oldEmail = $user->email;
        $changes = [];
        
        // Check what changed
        if (isset($validated['name']) && $validated['name'] !== $oldName) {
            $changes['name'] = ['old' => $oldName, 'new' => $validated['name']];
        }
        if (isset($validated['email']) && $validated['email'] !== $oldEmail) {
            $changes['email'] = ['old' => $oldEmail, 'new' => $validated['email']];
        }

        $user->update($validated);

        // Sync roles if provided
        if (isset($validated['roles'])) {
            $oldRoles = $user->roles->pluck('name')->toArray();
            $user->syncRoles($validated['roles']);
            $changes['roles'] = ['old' => $oldRoles, 'new' => $validated['roles']];
        }

        // Log update if there are changes
        if (!empty($changes)) {
            activity()
                ->causedBy(Auth::user())
                ->performedOn($user)
                ->withProperties($changes)
                ->log('Đã cập nhật thông tin người dùng');
        }

        return redirect()->route('users.index')->with([
            'message' => 'users.updateSuccess',
            'type' => 'success'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $userName = $user->name;
        $userEmail = $user->email;

        $user->delete();

        // Log soft delete
        activity()
            ->causedBy(Auth::user())
            ->performedOn($user)
            ->withProperties([
                'user_name' => $userName,
                'user_email' => $userEmail
            ])
            ->log('Đã xóa người dùng');

        return redirect()->route('users.index')->with([
            'message' => 'users.deleteSuccess',
            'type' => 'success'
        ]);
    }

    /**
     * Remove multiple resources from storage.
     */
    public function bulkDelete(Request $request)
    {
        $this->authorize('bulkDelete', User::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:users,id'
        ]);

        $users = User::whereIn('id', $request->ids)->get();
        $userNames = $users->pluck('name')->toArray();

        User::whereIn('id', $request->ids)->delete();

        // Log bulk delete
        activity()
            ->causedBy(Auth::user())
            ->withProperties(['users' => $userNames])
            ->log('Đã xóa nhiều người dùng');

        return redirect()->route('users.index')->with([
            'message' => 'users.bulkDeleteSuccess',
            'type' => 'success'
        ]);
    }

    /**
     * Restore a soft deleted user.
     */
    public function restore(string $id)
    {
        $user = User::withTrashed()->findOrFail($id);

        $this->authorize('restore', $user);

        $user->restore();

        // Log restore action
        activity()
            ->causedBy(Auth::user())
            ->performedOn($user)
            ->withProperties([
                'user_name' => $user->name,
                'user_email' => $user->email
            ])
            ->log('Đã khôi phục người dùng');

        return redirect()->route('users.index')->with([
            'message' => 'users.restoreSuccess',
            'type' => 'success'
        ]);
    }

    /**
     * Permanently delete a user.
     */
    public function forceDelete(string $id)
    {
        $user = User::withTrashed()->findOrFail($id);

        $this->authorize('forceDelete', $user);

        // Log before deleting (vì sau khi delete không còn user)
        activity()
            ->causedBy(Auth::user())
            ->withProperties([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email
            ])
            ->log('Đã xóa vĩnh viễn người dùng');

        $user->forceDelete();

        return redirect()->route('users.index')->with([
            'message' => 'User permanently deleted',
            'type' => 'success'
        ]);
    }
}
