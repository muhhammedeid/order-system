<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_filament_login_page_renders(): void
    {
        $this->get('/admin/login')->assertStatus(200);
    }

    public function test_admin_can_authenticate_and_reach_the_filament_dashboard(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertStatus(200);
    }
}
