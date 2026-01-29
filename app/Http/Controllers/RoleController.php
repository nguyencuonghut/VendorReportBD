<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Role;
use App\Models\Permission;
use App\Http\Resources\RoleResource;
use App\Http\Resources\PermissionResource;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class RoleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Role::class);
        $roles = RoleResource::collection(
            Role::with('permissions')->withCount(['permissions', 'users'])->latest()->get()
        )->resolve();

        $permissions = PermissionResource::collection(
            Permission::all()
        )->resolve();

        return inertia('RoleIndex', compact('roles', 'permissions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Role::class);

        $permissions = PermissionResource::collection(
            Permission::all()
        )->resolve();

        return inertia('RoleCreate', compact('permissions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request)
    {
        $this->authorize('create', Role::class);

        $validated = $request->validated();

        $role = Role::create(['name' => $validated['name']]);

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        // Log activity
        activity()
            ->performedOn($role)
            ->causedBy(Auth::user())
            ->withProperties([
                'role_name' => $role->name,
                'permissions' => $role->permissions?->pluck('name')->toArray()
            ])
            ->log('Tạo vai trò mới: ' . $role->name);

        return redirect()->route('roles.index')->with([
            'message' => 'roles.createSuccess',
            'type' => 'success'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        $this->authorize('view', $role);

        $role->load('permissions', 'users');

        return inertia('RoleShow', [
            'role' => new RoleResource($role),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        $this->authorize('update', $role);

        $role->load('permissions');

        $permissions = PermissionResource::collection(
            Permission::all()
        )->resolve();

        return inertia('RoleEdit', [
            'role' => new RoleResource($role),
            'permissions' => $permissions,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        $this->authorize('update', $role);

        $validated = $request->validated();

        $role->update(['name' => $validated['name']]);

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        activity()
            ->performedOn($role)
            ->causedBy(Auth::user())
            ->withProperties([
                'role_name' => $role->name,
                'permissions' => $role->permissions?->pluck('name')->toArray()
            ])
            ->log('Sửa vai trò: ' . $role->name);

        return redirect()->route('roles.index')->with([
            'message' => 'roles.updateSuccess',
            'type' => 'success'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);

        // Prevent deleting system roles
        $systemRoles = ['Super Admin', 'Admin'];
        if (in_array($role->name, $systemRoles)) {
            return redirect()->route('roles.index')->with([
                'message' => 'roles.cannotDeleteSystemRoles',
                'type' => 'error'
            ]);
        }

        $roleName = $role->name;
        $role->delete();

        activity()
            ->causedBy(Auth::user())
            ->performedOn($role)
            ->withProperties([
                'role_name' => $role->name,
                'permissions' => $role->permissions?->pluck('name')->toArray()
            ])
            ->log('Xóa vai trò: ' . $roleName);

        return redirect()->route('roles.index')->with([
            'message' => 'roles.deleteSuccess',
            'type' => 'success'
        ]);
    }

    /**
     * Remove multiple resources from storage.
     */
    public function bulkDelete(Request $request)
    {
        $this->authorize('bulkDelete', Role::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:roles,id'
        ]);

        // Prevent deleting system roles
        $systemRoles = ['Super Admin', 'Admin'];
        $rolesToDelete = Role::whereIn('id', $request->ids)
            ->whereNotIn('name', $systemRoles)
            ->get();

        if ($rolesToDelete->isEmpty()) {
            return redirect()->route('roles.index')->with([
                'message' => 'roles.cannotDeleteSystemRoles',
                'type' => 'error'
            ]);
        }

        foreach ($rolesToDelete as $role) {
            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'role_name' => $role->name,
                    'permissions' => $role->permissions?->pluck('name')->toArray()
                ])
                ->log('Xóa vai trò: ' . $role->name);
        }

        $rolesToDelete->each->delete();

        return redirect()->route('roles.index')->with([
            'message' => 'roles.bulkDeleteSuccess',
            'type' => 'success'
        ]);
    }
}
