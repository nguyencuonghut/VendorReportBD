<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    /** @test */
    public function user_can_have_multiple_roles()
    {
        $user = User::factory()->create();
        $user->assignRole(['Admin', 'Manager']);

        $this->assertTrue($user->hasRole('Admin'));
        $this->assertTrue($user->hasRole('Manager'));
        $this->assertCount(2, $user->roles);
    }

    /** @test */
    public function role_has_permissions()
    {
        $role = Role::where('name', 'Super Admin')->first();

        $this->assertGreaterThan(0, $role->permissions->count());
    }

    /** @test */
    public function user_inherits_permissions_from_role()
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $this->assertTrue($user->can('view users'));
        $this->assertTrue($user->can('create users'));
    }

    /** @test */
    public function super_admin_has_all_permissions()
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $allPermissions = Permission::all();

        foreach ($allPermissions as $permission) {
            $this->assertTrue(
                $user->can($permission->name),
                "Super Admin should have {$permission->name} permission"
            );
        }
    }

    /** @test */
    public function helper_functions_work_correctly()
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $this->actingAs($user);

        $this->assertTrue(hasRole('Super Admin'));
        $this->assertTrue(hasPermission('view users'));
        $this->assertTrue(hasAnyRole(['Super Admin', 'Admin']));
        $this->assertTrue(hasAllRoles(['Super Admin']));
    }

    /** @test */
    public function guest_user_has_no_permissions()
    {
        $this->assertFalse(hasRole('Admin'));
        $this->assertFalse(hasPermission('view users'));
        $this->assertEmpty(currentUserRoles());
        $this->assertEmpty(currentUserPermissions());
    }
}
