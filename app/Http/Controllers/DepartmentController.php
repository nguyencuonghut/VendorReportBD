<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Department::class);

        $departments = Department::query()
            ->with(['headUser', 'parent'])
            ->withCount('users')
            ->when($request->search, function($q, $search) {
                $q->where(function($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                          ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('code')
            ->get();

        $users = \App\Models\User::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $allDepartments = Department::orderBy('code')->get(['id', 'code', 'name']);

        return Inertia::render('Departments/Index', [
            'departments' => DepartmentResource::collection($departments)->resolve(),
            'users' => $users,
            'allDepartments' => $allDepartments,
            'filters' => $request->only(['search', 'is_active']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Department::class);

        $parentDepartments = Department::active()
            ->orderBy('code')
            ->get();

        return Inertia::render('Departments/Create', [
            'parentDepartments' => DepartmentResource::collection($parentDepartments),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDepartmentRequest $request)
    {
        $this->authorize('create', Department::class);

        $department = Department::create($request->validated());

        activity()
            ->performedOn($department)
            ->causedBy(auth()->user())
            ->log('department_created');

        return redirect()->route('departments.index')
            ->with('success', 'Tạo phòng ban thành công');
    }

    /**
     * Display the specified resource.
     */
    public function show(Department $department)
    {
        $this->authorize('view', $department);

        $department->load(['headUser', 'parent', 'children', 'users']);

        return Inertia::render('Departments/Show', [
            'department' => new DepartmentResource($department),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Department $department)
    {
        $this->authorize('update', $department);

        $department->load(['headUser', 'parent']);

        $users = \App\Models\User::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $parentDepartments = Department::active()
            ->where('id', '!=', $department->id)
            ->orderBy('code')
            ->get();

        $allDepartments = Department::where('id', '!=', $department->id)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Departments/Edit', [
            'department' => (new DepartmentResource($department))->resolve(),
            'users' => $users,
            'parentDepartments' => DepartmentResource::collection($parentDepartments)->resolve(),
            'allDepartments' => $allDepartments,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        $this->authorize('update', $department);

        $department->update($request->validated());

        activity()
            ->performedOn($department)
            ->causedBy(auth()->user())
            ->log('department_updated');

        return redirect()->route('departments.index')
            ->with('success', 'Cập nhật phòng ban thành công');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department)
    {
        $this->authorize('delete', $department);

        // Soft delete bằng cách set is_active = false
        $department->update(['is_active' => false]);

        activity()
            ->performedOn($department)
            ->causedBy(auth()->user())
            ->log('department_deactivated');

        return redirect()->route('departments.index')
            ->with('success', 'Vô hiệu hóa phòng ban thành công');
    }
}
