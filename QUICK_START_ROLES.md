# ⚡ Quick Start - Roles & Permissions

## 🎯 Bắt Đầu Sử Dụng Ngay

### 1️⃣ Login với Users Mẫu

```
Super Admin:
- Email: nguyenvancuong@honghafeed.com.vn
- Password: Hongha@123

Admin:
- Email: admin@example.com
- Password: password

Manager:
- Email: manager@example.com
- Password: password
```

### 2️⃣ Thêm Routes Cơ Bản

Mở `routes/web.php` và thêm:

```php
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ActivityLogController;

Route::middleware(['auth'])->group(function () {
    // Roles Management
    Route::resource('roles', RoleController::class);
    
    // Activity Logs
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])
        ->name('activity-logs.index');
    Route::delete('/activity-logs/{activity}', [ActivityLogController::class, 'destroy'])
        ->name('activity-logs.destroy');
    Route::post('/activity-logs/clear', [ActivityLogController::class, 'clear'])
        ->name('activity-logs.clear');
});
```

### 3️⃣ Share Auth Data với Inertia

Mở `app/Http/Middleware/HandleInertiaRequests.php` và cập nhật method `share`:

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

### 4️⃣ Test Ngay!

**Backend đã sẵn sàng!** Bạn có thể:

#### A. Test trong Controller:
```php
use Illuminate\Support\Facades\Auth;

if (Auth::user()->hasRole('admin')) {
    // User is admin
}

if (Auth::user()->can('edit users')) {
    // User has permission
}
```

#### B. Test trong Routes:
```php
Route::middleware(['auth', 'role:admin'])->group(function () {
    // Admin only
});

Route::middleware(['auth', 'permission:view backups'])->group(function () {
    // Users with permission
});
```

#### C. Test trong Tinker:
```bash
php artisan tinker

# Check user roles
>>> $user = User::first();
>>> $user->getRoleNames();

# Check permissions
>>> $user->getAllPermissions()->pluck('name');

# Assign role
>>> $user->assignRole('admin');

# Give permission
>>> $user->givePermissionTo('edit users');

# View activity logs
>>> Activity::latest()->take(10)->get();
```

### 5️⃣ Sử Dụng trong Vue

Sau khi share auth data, trong Vue components:

```vue
<template>
  <!-- Check role -->
  <div v-if="$page.props.auth.roles?.includes('admin')">
    <h1>Admin Panel</h1>
  </div>

  <!-- Check permission -->
  <button 
    v-if="$page.props.auth.permissions?.includes('edit users')"
    @click="editUser"
  >
    Edit User
  </button>

  <!-- Show user info -->
  <p>Hello, {{ $page.props.auth.user.name }}!</p>
  <p>Roles: {{ $page.props.auth.roles?.join(', ') }}</p>
</template>
```

## 📋 Checklist Setup

- [x] ✅ Packages installed (spatie/laravel-permission & activitylog)
- [x] ✅ Migrations run
- [x] ✅ Seeders run (roles, permissions, users)
- [x] ✅ Models created (Role, Permission, User updated)
- [x] ✅ Middleware created and registered
- [x] ✅ Controllers created
- [x] ✅ Vue pages created (Roles/Index, ActivityLogs/Index)
- [ ] ⏳ Add routes to web.php
- [ ] ⏳ Update HandleInertiaRequests
- [ ] ⏳ Create remaining Vue pages
- [ ] ⏳ Add navigation links

## 🎨 Pages Đã Có

1. **Roles Management**
   - Location: `resources/js/Pages/Roles/Index.vue`
   - Route: `/roles` (cần thêm vào web.php)
   - Features: View all roles, permissions, users count

2. **Activity Logs**
   - Location: `resources/js/Pages/ActivityLogs/Index.vue`
   - Route: `/activity-logs` (cần thêm vào web.php)
   - Features: View logs, filter, search, pagination

## 🔥 Các Tính Năng Sẵn Sàng

### ✅ Đã Hoàn Thành:
- Role & Permission management (backend)
- Activity logging (tự động)
- Middleware bảo vệ routes
- 4 roles mặc định (super-admin, admin, manager, user)
- 17 permissions mặc định
- 13 users mẫu
- 2 Vue pages đẹp

### ⏳ Cần Làm Thêm:
- Thêm routes vào web.php
- Tạo các Vue pages còn thiếu (Create, Edit, Show)
- Tạo navigation menu
- Tích hợp vào các features hiện có (Backups, Users, etc.)

## 🚀 Next Actions

1. **Copy routes** từ `routes/example_roles_routes.php` vào `routes/web.php`
2. **Update HandleInertiaRequests** để share auth data
3. **Tạo navigation menu** với links đến `/roles` và `/activity-logs`
4. **Test** bằng cách login và truy cập các routes

## 💡 Tips

- Luôn check permissions ở cả middleware VÀ controller
- Log các hành động quan trọng
- Review activity logs định kỳ
- Dùng `php artisan permission:cache-reset` nếu permissions không update

## 📚 Xem Thêm

- `ROLES_PERMISSIONS_GUIDE.md` - Hướng dẫn chi tiết
- `ROLES_PERMISSIONS_SUMMARY.md` - Tóm tắt những gì đã làm
- `routes/example_roles_routes.php` - Ví dụ routes đầy đủ

---

**Ready to go!** 🎉 Backend hoàn chỉnh, chỉ cần thêm routes và UI!
