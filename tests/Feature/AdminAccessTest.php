<?php

namespace Tests\Feature;

use App\Models\Menu;
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

    private function menu(string $link): Menu
    {
        return Menu::query()->create([
            'name' => $link,
            'type' => 'link',
            'link' => $link,
            'sort_order' => 1,
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
        $user = $this->permittedUser([$this->menu('/admin/cursos')->id]);

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
        $user = $this->permittedUser([$this->menu('/admin/cursos')->id]);

        $this->withToken($this->apiLogin($user))->getJson('/api/v1/admin/menus')->assertForbidden();
        $this->withToken($this->apiLogin($user))->getJson('/api/v1/admin/users')->assertForbidden();
        $this->withToken($this->apiLogin($user))->getJson('/api/v1/admin/user-types')->assertForbidden();
    }

    public function test_permission_is_scoped_to_the_permitted_menu(): void
    {
        $courses = $this->menu('/admin/cursos');
        $this->menu('/admin/cupons');
        $user = $this->permittedUser([$courses->id]);

        $this->withToken($this->apiLogin($user))->getJson('/api/v1/admin/courses')->assertOk();
        $this->withToken($this->apiLogin($user))->getJson('/api/v1/admin/coupons')->assertForbidden();
        $this->withToken($this->apiLogin($user))->getJson('/api/v1/admin/orders')->assertForbidden();
        $this->withToken($this->apiLogin($user))->getJson('/api/v1/admin/payments')->assertForbidden();
    }

    public function test_operator_with_users_menu_manages_only_students(): void
    {
        $operator = $this->permittedUser([$this->menu('/admin/usuarios')->id]);
        $token = $this->apiLogin($operator);

        $this->withToken($token)->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonMissing(['email' => $this->admin->email])
            ->assertJsonFragment(['email' => $this->student->email]);

        $this->withToken($token)->postJson('/api/v1/admin/users', [
            'name' => 'Novo Aluno',
            'username' => 'novo.aluno',
            'email' => 'novo.aluno@example.com',
            'password' => 'Senha-Forte-123!',
            'user_type_id' => $this->student->user_type_id,
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/admin/users', [
            'name' => 'Falso Admin',
            'username' => 'falso.admin',
            'email' => 'falso.admin@example.com',
            'password' => 'Senha-Forte-123!',
            'user_type_id' => $this->admin->user_type_id,
        ])->assertUnprocessable();

        $this->withToken($token)->putJson("/api/v1/admin/users/{$this->student->id}", ['name' => 'Aluno Editado'])->assertOk();
        $this->withToken($token)->putJson("/api/v1/admin/users/{$this->admin->id}", ['name' => 'Hackeado'])->assertForbidden();
        $this->withToken($token)->putJson("/api/v1/admin/users/{$this->student->id}", ['user_type_id' => $this->admin->user_type_id])->assertUnprocessable();
        $this->withToken($token)->deleteJson("/api/v1/admin/users/{$this->admin->id}")->assertForbidden();

        $this->withToken($token)->getJson('/api/v1/admin/courses')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/user-types')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/dashboard')->assertOk();
    }

    public function test_admin_reaches_every_module(): void
    {
        $this->withToken($this->apiLogin($this->admin))->getJson('/api/v1/admin/coupons')->assertOk();
    }

    public function test_student_cannot_access_admin_area(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/courses')
            ->assertForbidden();
    }
}
