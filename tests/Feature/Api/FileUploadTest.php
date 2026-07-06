<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileUploadTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $schoolAdmin = Role::where('slug', 'school_admin')->first();

        if ($schoolAdmin) {
            DB::table('user_roles')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $this->user->id,
                'role_id' => $schoolAdmin->id,
                'scope_type' => 'tenant',
                'scope_id' => $this->tenant->id,
                'assigned_at' => now(),
                'status' => 'active',
            ]);
        }

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    public function test_can_upload_logo(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->withToken($this->token)
            ->postJson('/api/upload', [
                'file' => $file,
                'type' => 'logo',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'type' => 'logo',
                ],
            ]);

        $response->assertJsonStructure([
            'data' => [
                'url',
                'type',
                'filename',
            ],
        ]);
    }

    public function test_unauthenticated_upload_fails(): void
    {
        $file = UploadedFile::fake()->image('logo.png');

        $response = $this->postJson('/api/upload', [
            'file' => $file,
            'type' => 'logo',
        ]);

        $response->assertUnauthorized();
    }

    public function test_invalid_file_type_rejected(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->withToken($this->token)
            ->postJson('/api/upload', [
                'file' => $file,
                'type' => 'logo',
            ]);

        $response->assertStatus(422);
    }

    public function test_invalid_upload_type_rejected(): void
    {
        $file = UploadedFile::fake()->image('logo.png');

        $response = $this->withToken($this->token)
            ->postJson('/api/upload', [
                'file' => $file,
                'type' => 'invalid_type',
            ]);

        $response->assertStatus(422);
    }

    public function test_can_upload_all_logo_types(): void
    {
        $types = ['logo', 'logo_primary', 'logo_landscape', 'logo_dark', 'favicon'];

        foreach ($types as $type) {
            $file = UploadedFile::fake()->image("{$type}.png");

            $response = $this->withToken($this->token)
                ->postJson('/api/upload', [
                    'file' => $file,
                    'type' => $type,
                ]);

            $response->assertCreated()
                ->assertJson([
                    'status' => 'success',
                    'data' => ['type' => $type],
                ]);
        }
    }
}
