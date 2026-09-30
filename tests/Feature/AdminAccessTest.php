<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    private function permittedUser(array $permission = []): User
    {
        $type = UserType::factory()->create([
            'company_id' => $this->company->id,
            'code' => null,
            'permission_menu' => $permission,
        ]);

        return User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $type->id,
        ]);
    }

    public function test_user_without_permission_cannot_access_admin_area(): void
    {
        $this->withToken($this->apiLogin($this->permittedUser()))
            ->getJson('/api/v1/admin/courses')
            ->assertForbidden();
    }

    public function test_user_with_permission_can_list_and_create(): void
    {
        $user = $this->permittedUser([1]);

        $this->withToken($this->apiLogin($user))
            ->getJson('/api/v1/admin/courses')
            ->assertOk();

        $this->withToken($this->apiLogin($user))
            ->postJson('/api/v1/admin/courses', ['name' => 'Curso do Instrutor'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Curso do Instrutor');
    }

    public function test_user_with_permission_cannot_reach_sensitive_modules(): void
    {
        $user = $this->permittedUser([1]);

        $this->withToken($this->apiLogin($user))->getJson('/api/v1/admin/menus')->assertForbidden();
        $this->withToken($this->apiLogin($user))->getJson('/api/v1/admin/users')->assertForbidden();
        $this->withToken($this->apiLogin($user))->getJson('/api/v1/admin/user-types')->assertForbidden();
    }

    public function test_student_cannot_access_admin_area(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/courses')
            ->assertForbidden();
    }
}
