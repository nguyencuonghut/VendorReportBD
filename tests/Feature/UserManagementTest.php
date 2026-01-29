<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    /** @test */
    public function super_admin_can_view_users_list()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        $response = $this->actingAs($superAdmin)
            ->get(route('users.index'));

        $response->assertStatus(200);
    }

    /** @test */
    public function regular_user_cannot_view_users_list()
    {
        $user = User::factory()->create();
        $user->assignRole('User');

        $response = $this->actingAs($user)
            ->get(route('users.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function super_admin_can_create_user()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        $role = Role::where('name', 'User')->first();

        $response = $this->actingAs($superAdmin)
            ->post(route('users.store'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'roles' => [$role->id],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com'
        ]);
    }

    /** @test */
    public function cannot_create_user_with_duplicate_email()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        $existingUser = User::factory()->create(['email' => 'existing@example.com']);
        $role = Role::where('name', 'User')->first();

        $response = $this->actingAs($superAdmin)
            ->post(route('users.store'), [
                'name' => 'Test User',
                'email' => 'existing@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'roles' => [$role->id],
            ]);

        $response->assertSessionHasErrors('email');
    }

    /** @test */
    public function super_admin_can_update_user()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        $user = User::factory()->create();
        $user->assignRole('User');

        $response = $this->actingAs($superAdmin)
            ->put(route('users.update', $user), [
                'name' => 'Updated Name',
                'email' => $user->email,
                'roles' => [$user->roles->first()->id],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name'
        ]);
    }

    /** @test */
    public function super_admin_can_delete_user()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        $user = User::factory()->create();

        $response = $this->actingAs($superAdmin)
            ->delete(route('users.destroy', $user));

        $response->assertRedirect();

        // Check soft delete
        $this->assertSoftDeleted('users', [
            'id' => $user->id
        ]);

        // Verify user still exists in database but with deleted_at
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email
        ]);
    }

    /** @test */
    public function user_validation_rules_are_enforced()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        $response = $this->actingAs($superAdmin)
            ->post(route('users.store'), [
                'name' => '',
                'email' => 'invalid-email',
                'password' => '123',
                'password_confirmation' => '456',
            ]);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'roles']);
    }
}
