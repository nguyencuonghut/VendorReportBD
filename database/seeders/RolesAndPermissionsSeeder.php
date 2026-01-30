<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User Management
            'view users',
            'create users',
            'edit users',
            'delete users',

            // Role Management
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',

            // Permission Management
            'view permissions',
            'assign permissions',

            // Backup Management
            'view backups',
            'create backups',
            'restore backups',
            'delete backups',
            'configure backups',

            // Activity Log
            'view activity logs',
            'delete activity logs',

            // Vendor Report Management
            'view vendor reports',
            'create vendor reports',
            'edit vendor reports',
            'delete vendor reports',
            'submit vendor reports',
            'approve vendor reports',
            'reject vendor reports',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create workflow-specific roles based on vendor_report_system_design.md

        // Admin System - Full system administration
        $adminSystem = Role::create(['name' => 'admin_system']);
        $adminSystem->givePermissionTo(Permission::all());

        // Requester - Người mua hàng (chọn nhà cung cấp, tạo và nộp báo cáo)
        $requester = Role::create(['name' => 'requester']);
        $requester->givePermissionTo([
            'view vendor reports',
            'create vendor reports',
            'edit vendor reports',
            'submit vendor reports',
            'view activity logs',
        ]);

        // Purchasing Admin - Hành chính/thủ tục (theo dõi, chạy giấy tờ, không tham gia duyệt)
        $purchasingAdmin = Role::create(['name' => 'purchasing_admin']);
        $purchasingAdmin->givePermissionTo([
            'view vendor reports',
            'view activity logs',
        ]);

        // Internal Control - Approve and select BOD
        $internalControl = Role::create(['name' => 'internal_control']);
        $internalControl->givePermissionTo([
            'view vendor reports',
            'approve vendor reports',
            'reject vendor reports',
            'view activity logs',
        ]);

        // National Purchasing - Approve in SPECIAL_2 workflow
        $nationalPurchasing = Role::create(['name' => 'national_purchasing']);
        $nationalPurchasing->givePermissionTo([
            'view vendor reports',
            'approve vendor reports',
            'reject vendor reports',
            'view activity logs',
        ]);

        // Tech Board - Approve in SPECIAL_3 workflow
        $techBoard = Role::create(['name' => 'tech_board']);
        $techBoard->givePermissionTo([
            'view vendor reports',
            'approve vendor reports',
            'reject vendor reports',
            'view activity logs',
        ]);

        // BOD (Ban Giám Đốc) - Final approval in most workflows
        $bod = Role::create(['name' => 'bod']);
        $bod->givePermissionTo([
            'view vendor reports',
            'approve vendor reports',
            'reject vendor reports',
            'view activity logs',
        ]);

        $this->command->info('Workflow-specific roles and permissions created successfully!');
    }
}
