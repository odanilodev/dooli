<?php

namespace Tests\Feature;

use App\Enums\GeneralStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->admin->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email']]]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => $this->admin->email,
            'password' => 'wrong-password',
        ])->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->admin->update(['status' => GeneralStatus::Inactive->value]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $this->admin->email,
            'password' => 'password',
        ])->assertStatus(401)->assertJsonPath('code', 'invalid_credentials');
    }

    public function test_unverified_user_cannot_login(): void
    {
        $unverified = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $unverified->email,
            'password' => 'password',
        ])->assertStatus(403)->assertJsonPath('code', 'email_not_verified');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $this->admin->id);
    }
}
