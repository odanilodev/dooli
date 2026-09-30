<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AdminUserTypeTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    private function makeType(array $attributes = []): UserType
    {
        return UserType::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'code' => null,
            'permission_menu' => [],
        ], $attributes));
    }

    public function test_admin_can_list_user_types(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/user-types')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_admin_can_create_a_user_type_with_permissions(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/user-types', [
                'name' => 'Financeiro',
                'permission_menu' => [1, 2, 3],
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Financeiro')
            ->assertJsonPath('data.permission_menu', [1, 2, 3]);

        $this->assertDatabaseHas('user_types', [
            'name' => 'Financeiro',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_user_type_code_is_unique_per_company(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/user-types', [
                'name' => 'Duplicado',
                'code' => 'admin',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_user_type_code_must_be_valid(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/user-types', [
                'name' => 'Estranho',
                'code' => 'invalido',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_admin_can_update_a_user_type(): void
    {
        $type = $this->makeType(['name' => 'Antigo', 'permission_menu' => [1]]);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/user-types/{$type->id}", [
                'name' => 'Novo',
                'permission_menu' => [5, 6],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Novo')
            ->assertJsonPath('data.permission_menu', [5, 6]);
    }

    public function test_admin_can_delete_a_user_type(): void
    {
        $type = $this->makeType();

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/user-types/{$type->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('user_types', ['id' => $type->id]);
    }

    public function test_cannot_delete_a_user_type_with_users(): void
    {
        $type = $this->makeType();
        User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $type->id,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/user-types/{$type->id}")
            ->assertStatus(409);
    }

    public function test_student_cannot_manage_user_types(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/user-types')
            ->assertForbidden();
    }

    public function test_guest_cannot_list_user_types(): void
    {
        $this->getJson('/api/v1/admin/user-types')->assertUnauthorized();
    }
}
