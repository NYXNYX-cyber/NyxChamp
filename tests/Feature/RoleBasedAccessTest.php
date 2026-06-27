<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBasedAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin');
        $response->assertOk();
    }

    public function test_teacher_cannot_access_admin_dashboard(): void
    {
        $teacher = User::factory()->teacher()->create();

        $response = $this->actingAs($teacher)->get('/admin');
        $response->assertForbidden();
    }

    public function test_student_cannot_access_admin_dashboard(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get('/admin');
        $response->assertForbidden();
    }

    public function test_anonymous_user_redirected_to_login(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/login');
    }

    public function test_user_can_update_institution(): void
    {
        $user = User::factory()->student()->create(['institution' => null]);

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'institution' => 'SMA Negeri 1 Bandung',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('SMA Negeri 1 Bandung', $user->fresh()->institution);
    }

    public function test_user_can_clear_institution(): void
    {
        $user = User::factory()->create(['institution' => 'Old School']);

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'institution' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->institution);
    }
}
