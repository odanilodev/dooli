<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_user_can_view_own_profile(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $this->student->id);
    }

    public function test_user_can_update_own_profile(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->putJson('/api/v1/me/profile', [
                'name' => 'Nome Atualizado',
                'city' => 'São Paulo',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nome Atualizado');

        $this->assertDatabaseHas('users', ['id' => $this->student->id, 'city' => 'São Paulo']);
    }

    public function test_profile_update_cannot_change_role_or_status(): void
    {
        $originalType = $this->student->user_type_id;

        $this->withToken($this->apiLogin($this->student))
            ->putJson('/api/v1/me/profile', [
                'name' => 'Aluno',
                'user_type_id' => 1,
                'status' => 'inactive',
            ])
            ->assertOk();

        $this->assertSame($originalType, $this->student->fresh()->user_type_id);
        $this->assertSame($this->student->status, $this->student->fresh()->status);
    }

    public function test_user_can_change_password(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->putJson('/api/v1/me/password', [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('new-password-123', $this->student->fresh()->password));
    }

    public function test_change_password_requires_correct_current_password(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->putJson('/api/v1/me/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }

    public function test_changing_password_revokes_other_tokens(): void
    {
        $current = $this->student->createToken('current')->plainTextToken;
        $other = $this->student->createToken('other');

        $this->withToken($current)
            ->putJson('/api/v1/me/password', [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'current']);
    }
}
