<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login(): void
    {
        Admin::factory()->create([
            'email' => 'admin@softigital.com',
            'password' => 'adminpassword',
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@softigital.com',
            'password' => 'adminpassword',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'admin' => ['id', 'name', 'email', 'created_at'],
                    'token',
                ],
            ]);
    }

    public function test_admin_login_fails_with_wrong_credentials(): void
    {
        Admin::factory()->create([
            'email' => 'admin@softigital.com',
            'password' => 'adminpassword',
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@softigital.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_customer_cannot_login_as_admin(): void
    {
        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'customer@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }
}





