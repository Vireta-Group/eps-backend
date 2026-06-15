<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $user;

    private string $password = 'Password@123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '01700000001',
            'password_hash' => Hash::make($this->password),
        ]);
    }

    public function test_user_can_login(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'phone' => '01700000001',
            'password' => $this->password,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => ['user', 'token'],
            ]);
    }

    public function test_login_with_wrong_credentials_fails(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'phone' => '01700000001',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_authenticated_user_can_access_me(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'id' => $this->user->id,
                        'phone' => '01700000001',
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_access_me(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_user_can_logout(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/auth/logout');

        $response->assertOk();
        $this->assertCount(0, $this->user->tokens);
    }
}
