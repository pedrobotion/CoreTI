<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
    }

    public function test_admin_can_initiate_user_password_reset_with_token_link(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $oldHash = $user->password;

        $response = $this->actingAs($admin)->post(route('admin.reset-password', $user));

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('password_reset');

        $user->refresh();

        $this->assertTrue($user->must_change_password);
        $this->assertNotSame($oldHash, $user->password);
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_admin_cannot_manage_own_role(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.update-role', $admin));

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertForbidden();
    }
}
