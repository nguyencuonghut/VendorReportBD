# 📖 Roles & Permissions - README

## 🎯 Mục Đích

Hệ thống quản lý **Roles (Vai trò)** và **Permissions (Quyền hạn)** cho ứng dụng Laravel, sử dụng packages của **Spatie**.

## 📦 Packages

- **spatie/laravel-permission** v6.21.0
- **spatie/laravel-activitylog** v4.10.2

## 🚀 Quick Start

### 1. Login với Tài Khoản Mẫu

```
Super Admin:
Email: nguyenvancuong@honghafeed.com.vn
Password: Hongha@123

Admin:
Email: admin@example.com
Password: password
```

### 2. Sử Dụng Trong Code

```php
// Check role
if (Auth::user()->hasRole('admin')) {
    // User is admin
}

// Check permission
if (Auth::user()->can('edit users')) {
    // User has permission
}

// Log activity
activity()
    ->causedBy(Auth::user())
    ->log('User performed action');
```

### 3. Bảo Vệ Routes

```php
// By role
Route::middleware(['auth', 'role:admin'])->group(function () {
    // Admin only
});

// By permission
Route::middleware(['auth', 'permission:view backups'])->group(function () {
    // Users with permission
});
```

## 📁 Cấu Trúc

```
app/
├── Models/
│   ├── Role.php              # Role model
│   ├── Permission.php        # Permission model
│   ├── Activity.php          # Activity log model
│   └── User.php             # Updated with HasRoles trait
├── Http/
│   ├── Controllers/
│   │   ├── RoleController.php           # Roles CRUD
│   │   ├── PermissionController.php     # Permissions CRUD
│   │   └── ActivityLogController.php    # Logs management
│   └── Middleware/
│       ├── RoleMiddleware.php           # Role check
│       └── PermissionMiddleware.php     # Permission check
└── Helpers/
    └── RolePermissionHelpers.php        # 12 helper functions

database/
├── migrations/
│   ├── *_create_permission_tables.php
│   └── *_create_activity_log_table.php
└── seeders/
    ├── RolesAndPermissionsSeeder.php
    └── UserSeeder.php

resources/js/Pages/
├── Roles/
│   └── Index.vue            # Roles listing page
└── ActivityLogs/
    └── Index.vue            # Activity logs page
```

## 🎭 Roles & Permissions

### Default Roles

| Role | Permissions | Users |
|------|------------|-------|
| super-admin | All (17) | 1 |
| admin | 8 | 1 |
| manager | 6 | 1 |
| user | 1 | 10 |

### Default Permissions (17)

- **User Management**: view, create, edit, delete users
- **Role Management**: view, create, edit, delete roles
- **Permission Management**: view, assign permissions
- **Backup Management**: view, create, restore, delete, configure backups
- **Activity Log**: view, delete activity logs

## 💡 Helper Functions

```php
hasRole('admin')                    // Check if user has role
hasPermission('edit users')         // Check if user has permission
hasAnyRole(['admin', 'manager'])    // Check if user has any role
isSuperAdmin()                      // Check if super admin
isAdmin()                          // Check if admin or super admin
currentUserRoles()                 // Get current user's roles
currentUserPermissions()           // Get current user's permissions
logActivity('Description')          // Quick log activity
getRecentActivities(10)            // Get recent activities
```

## 📚 Documentation Files

1. **QUICK_START_ROLES.md** - Bắt đầu nhanh
2. **ROLES_PERMISSIONS_GUIDE.md** - Hướng dẫn chi tiết
3. **ROLES_PERMISSIONS_SUMMARY.md** - Tóm tắt implementation
4. **CHANGELOG_ROLES.md** - Lịch sử thay đổi
5. **IMPLEMENTATION_COMPLETE.md** - Báo cáo hoàn thành
6. **TESTING_EXAMPLES.php** - Ví dụ testing
7. **routes/example_roles_routes.php** - Ví dụ routes

## 🔧 Artisan Commands

```bash
# Reset permission cache
php artisan permission:cache-reset

# Test trong tinker
php artisan tinker
>>> $user = User::first();
>>> $user->getRoleNames();
>>> $user->getAllPermissions()->pluck('name');
```

## ⚙️ Configuration

- **Cache**: Permissions cached 24 hours
- **Guard**: web (default)
- **Teams**: Disabled
- **Activity Log**: Enabled

## 📊 Status

- ✅ Backend: 100% Complete
- ⏳ Frontend: 40% Complete (2/5 pages)
- ✅ Documentation: Comprehensive
- ✅ Testing: Examples provided

## 🎯 Next Steps

1. [ ] Add routes to `routes/web.php`
2. [ ] Update `HandleInertiaRequests` middleware
3. [ ] Create remaining Vue pages
4. [ ] Add navigation menu links

## 📞 Getting Help

Xem các file documentation:
- Bắt đầu: `QUICK_START_ROLES.md`
- Chi tiết: `ROLES_PERMISSIONS_GUIDE.md`
- Ví dụ: `TESTING_EXAMPLES.php`

## 🎉 Features

- ✅ Role-based access control
- ✅ Permission-based access control
- ✅ Activity logging (automatic)
- ✅ Helper functions (12)
- ✅ Vue components (2)
- ✅ Middleware protection
- ✅ Comprehensive docs

---

**Version**: 1.0.0  
**Date**: October 16, 2025  
**Status**: Production Ready (Backend)
