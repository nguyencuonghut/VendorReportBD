# Hướng Dẫn Sử Dụng Roles & Permissions với Spatie

## 📋 Tổng Quan

Dự án đã được tích hợp 2 packages của Spatie:
- **spatie/laravel-permission** - Quản lý Roles & Permissions
- **spatie/laravel-activitylog** - Ghi log hoạt động của users

## 🎯 Cấu Trúc Đã Tạo

### 1. Models
- `App\Models\Role` - Extended từ Spatie Role với ActivityLog
- `App\Models\Permission` - Extended từ Spatie Permission với ActivityLog
- `App\Models\User` - Đã thêm traits: `HasRoles`, `LogsActivity`

### 2. Middleware
- `RoleMiddleware` - Kiểm tra role của user
- `PermissionMiddleware` - Kiểm tra permission của user

### 3. Controllers
- `RoleController` - CRUD cho roles
- `PermissionController` - CRUD cho permissions (cần triển khai)
- `ActivityLogController` - Xem logs hoạt động

### 4. Database Tables
- `roles` - Lưu trữ roles
- `permissions` - Lưu trữ permissions
- `model_has_roles` - Gán roles cho users
- `model_has_permissions` - Gán permissions trực tiếp cho users
- `role_has_permissions` - Gán permissions cho roles
- `activity_log` - Lưu log hoạt động

## 🚀 Roles & Permissions Mặc Định

### Roles:
1. **super-admin** - Có tất cả quyền
2. **admin** - Quản lý users, backups, xem logs
3. **manager** - Quản lý users, backups
4. **user** - Xem logs cơ bản

### Permissions:
- User Management: `view users`, `create users`, `edit users`, `delete users`
- Role Management: `view roles`, `create roles`, `edit roles`, `delete roles`
- Permission Management: `view permissions`, `assign permissions`
- Backup Management: `view backups`, `create backups`, `restore backups`, `delete backups`, `configure backups`
- Activity Log: `view activity logs`, `delete activity logs`

## 💡 Cách Sử Dụng

### 1. Trong Controller

```php
use Illuminate\Support\Facades\Auth;

// Kiểm tra role
if (Auth::user()->hasRole('admin')) {
    // User có role admin
}

// Kiểm tra permission
if (Auth::user()->can('edit users')) {
    // User có quyền edit users
}

// Kiểm tra nhiều roles (OR)
if (Auth::user()->hasAnyRole(['admin', 'super-admin'])) {
    // User có 1 trong các roles
}

// Kiểm tra tất cả roles (AND)
if (Auth::user()->hasAllRoles(['admin', 'manager'])) {
    // User có tất cả các roles
}

// Gán role cho user
$user->assignRole('admin');

// Xóa role khỏi user
$user->removeRole('admin');

// Gán permission cho user
$user->givePermissionTo('edit users');

// Xóa permission
$user->revokePermissionTo('edit users');
```

### 2. Trong Routes (web.php)

```php
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ActivityLogController;

// Bảo vệ route với role
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('roles', RoleController::class);
});

// Bảo vệ route với permission
Route::middleware(['auth', 'permission:view activity logs'])->group(function () {
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
});

// Bảo vệ với nhiều roles (OR)
Route::middleware(['auth', 'role:admin|super-admin'])->group(function () {
    // Routes
});
```

### 3. Trong Blade/Inertia

```php
// Trong Blade
@role('admin')
    <p>Chỉ admin mới thấy</p>
@endrole

@can('edit users')
    <button>Edit User</button>
@endcan

// Trong Inertia (share qua HandleInertiaRequests)
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => [
            'user' => $request->user(),
            'roles' => $request->user()?->getRoleNames(),
            'permissions' => $request->user()?->getAllPermissions()->pluck('name'),
        ],
    ];
}
```

### 4. Activity Log

```php
use Illuminate\Support\Facades\Auth;

// Ghi log tự động (đã config trong model)
$user->update(['name' => 'New Name']); // Tự động log

// Ghi log thủ công
activity()
    ->performedOn($model) // Model bị tác động
    ->causedBy(Auth::user()) // User thực hiện
    ->withProperties(['key' => 'value']) // Dữ liệu thêm
    ->log('User updated profile'); // Mô tả

// Lấy logs của model
$activities = Activity::forSubject($user)->get();

// Lấy logs của user thực hiện
$activities = Activity::causedBy($user)->get();

// Lấy logs gần đây
$activities = Activity::latest()->limit(10)->get();
```

## 📝 Ví Dụ Thực Tế

### 1. Tạo Role Mới với Permissions

```php
use App\Models\Role;
use App\Models\Permission;

$role = Role::create(['name' => 'teacher']);
$role->givePermissionTo([
    'view users',
    'create users',
    'view activity logs',
]);
```

### 2. Gán Role cho User Mới

```php
use App\Models\User;

$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => bcrypt('password'),
]);

$user->assignRole('teacher');
```

### 3. Kiểm Tra Quyền Trước Khi Thực Hiện Hành Động

```php
public function deleteUser(User $user)
{
    if (!Auth::user()->can('delete users')) {
        abort(403, 'Bạn không có quyền xóa user');
    }
    
    $userName = $user->name;
    $user->delete();
    
    // Log hoạt động
    activity()
        ->causedBy(Auth::user())
        ->log("Deleted user: {$userName}");
        
    return redirect()->back()->with('success', 'User đã được xóa');
}
```

### 4. Middleware Tùy Chỉnh

```php
// Trong routes/web.php
Route::middleware(['auth', 'role:super-admin|admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);
});

Route::middleware(['auth', 'permission:view backups'])->group(function () {
    Route::get('/backups', [BackupController::class, 'index']);
});
```

## 🔧 Các Lệnh Artisan Hữu Ích

```bash
# Xem cache permissions
php artisan permission:cache-reset

# Tạo permission mới (thông qua tinker)
php artisan tinker
>>> Permission::create(['name' => 'new permission']);

# Tạo role mới
>>> Role::create(['name' => 'new-role']);

# Gán permission cho role
>>> $role = Role::findByName('admin');
>>> $role->givePermissionTo('new permission');
```

## 📊 Users Mẫu Đã Tạo

| Email | Password | Role | Mô Tả |
|-------|----------|------|-------|
| nguyenvancuong@honghafeed.com.vn | Hongha@123 | super-admin | Super Admin - Toàn quyền |
| admin@example.com | password | admin | Admin - Quản lý hệ thống |
| manager@example.com | password | manager | Manager - Quản lý users & backups |
| (random users) | password | user | Users thông thường |

## 🎨 Next Steps - Cần Làm

### 1. Tạo Views/Pages (Inertia)
- [ ] `resources/js/Pages/Roles/Index.vue` - Danh sách roles
- [ ] `resources/js/Pages/Roles/Create.vue` - Tạo role mới
- [ ] `resources/js/Pages/Roles/Edit.vue` - Sửa role
- [ ] `resources/js/Pages/Roles/Show.vue` - Chi tiết role
- [ ] `resources/js/Pages/ActivityLogs/Index.vue` - Danh sách logs
- [ ] `resources/js/Pages/ActivityLogs/Show.vue` - Chi tiết log

### 2. Thêm Routes vào web.php

```php
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ActivityLogController;

Route::middleware(['auth'])->group(function () {
    // Roles Management
    Route::middleware('permission:view roles')->group(function () {
        Route::resource('roles', RoleController::class);
    });

    // Activity Logs
    Route::middleware('permission:view activity logs')->group(function () {
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/activity-logs/{activity}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
    });

    Route::middleware('permission:delete activity logs')->group(function () {
        Route::delete('/activity-logs/{activity}', [ActivityLogController::class, 'destroy'])->name('activity-logs.destroy');
        Route::post('/activity-logs/clear', [ActivityLogController::class, 'clear'])->name('activity-logs.clear');
    });
});
```

### 3. Cập Nhật HandleInertiaRequests

Thêm roles & permissions vào shared data để sử dụng trong Vue components:

```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => [
            'user' => $request->user(),
            'roles' => $request->user()?->getRoleNames(),
            'permissions' => $request->user()?->getAllPermissions()->pluck('name'),
        ],
    ];
}
```

### 4. Tạo Components Vue

- RoleSelector.vue - Component chọn role
- PermissionCheckbox.vue - Checkbox permissions
- ActivityLogTable.vue - Bảng hiển thị logs

## 🔐 Bảo Mật

1. **Luôn kiểm tra permissions trong Controller**, không chỉ dựa vào middleware
2. **Sử dụng Policy** cho logic phức tạp
3. **Log các hành động quan trọng** (tạo, sửa, xóa)
4. **Cache permissions** để tăng performance (đã tự động)
5. **Định kỳ review logs** để phát hiện bất thường

## 📚 Tài Liệu Tham Khảo

- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
- [Spatie Laravel Activitylog](https://spatie.be/docs/laravel-activitylog)
- [Laravel Authorization](https://laravel.com/docs/authorization)

---

**Tác giả:** GitHub Copilot  
**Ngày tạo:** 16/10/2025
