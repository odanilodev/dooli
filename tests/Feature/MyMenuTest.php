<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class MyMenuTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    private function menu(array $attributes = []): Menu
    {
        return Menu::query()->create(array_merge([
            'name' => 'Item',
            'sub' => null,
            'link' => '/item',
            'type' => 'link',
            'sort_order' => 1,
            'icon' => null,
        ], $attributes));
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

    public function test_user_with_empty_permission_sees_all_menus(): void
    {
        $this->menu(['name' => 'Dashboard', 'link' => '/']);
        $this->menu(['name' => 'Turmas', 'link' => '/admin/turmas', 'sort_order' => 2]);

        $this->withToken($this->apiLogin($this->permittedUser()))
            ->getJson('/api/v1/me/menus')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'Dashboard')
            ->assertJsonPath('data.1.name', 'Turmas');
    }

    public function test_user_sees_only_permitted_menus_with_ancestors(): void
    {
        $section = $this->menu(['name' => 'Administração', 'type' => 'section', 'link' => null]);
        $child = $this->menu(['name' => 'Cursos', 'sub' => (string) $section->id, 'link' => '/admin/cursos']);
        $this->menu(['name' => 'Vídeos', 'sub' => (string) $section->id, 'link' => '/admin/videos']);

        $this->withToken($this->apiLogin($this->permittedUser([$child->id])))
            ->getJson('/api/v1/me/menus')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Administração')
            ->assertJsonPath('data.1.name', 'Cursos');
    }

    public function test_admin_sees_all_menus(): void
    {
        $this->menu(['name' => 'Dashboard', 'link' => '/']);
        $this->menu(['name' => 'Menus', 'link' => '/admin/menus', 'sort_order' => 2]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/me/menus')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_guest_cannot_list_my_menus(): void
    {
        $this->getJson('/api/v1/me/menus')->assertUnauthorized();
    }
}
