<?php

namespace Tests\Feature;

use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AdminMenuTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    private function makeMenu(array $attributes = []): Menu
    {
        return Menu::query()->create(array_merge([
            'name' => 'Item',
            'sub' => null,
            'link' => '/item',
            'sort_order' => 1,
            'icon' => 'dashboard',
        ], $attributes));
    }

    public function test_admin_can_list_menus(): void
    {
        $this->makeMenu(['name' => 'Dashboard', 'sort_order' => 1]);
        $this->makeMenu(['name' => 'Turmas', 'sort_order' => 2]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/menus')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'Dashboard')
            ->assertJsonPath('data.1.name', 'Turmas');
    }

    public function test_admin_can_create_a_menu(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/menus', [
                'name' => 'Relatórios',
                'link' => '/admin/relatorios',
                'icon' => 'content',
                'sort_order' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Relatórios');

        $this->assertDatabaseHas('menus', ['name' => 'Relatórios', 'link' => '/admin/relatorios', 'type' => 'link']);
    }

    public function test_admin_can_create_a_dropdown_item(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/menus', [
                'name' => 'Financeiro',
                'type' => 'dropdown',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'dropdown');

        $this->assertDatabaseHas('menus', ['name' => 'Financeiro', 'type' => 'dropdown']);
    }

    public function test_menu_type_must_be_valid(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/menus', ['name' => 'Estranho', 'type' => 'invalido'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_admin_can_create_a_submenu_item(): void
    {
        $parent = $this->makeMenu(['name' => 'Administração', 'link' => null]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/menus', [
                'name' => 'Conteúdo',
                'sub' => $parent->id,
                'link' => '/admin/conteudo',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sub', (string) $parent->id);
    }

    public function test_admin_can_update_a_menu(): void
    {
        $menu = $this->makeMenu();

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/menus/{$menu->id}", ['name' => 'Renomeado'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renomeado');

        $this->assertDatabaseHas('menus', ['id' => $menu->id, 'name' => 'Renomeado']);
    }

    public function test_deleting_a_menu_clears_children_parent(): void
    {
        $parent = $this->makeMenu(['name' => 'Administração', 'link' => null]);
        $child = $this->makeMenu(['name' => 'Conteúdo', 'sub' => (string) $parent->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/menus/{$parent->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('menus', ['id' => $parent->id]);
        $this->assertDatabaseHas('menus', ['id' => $child->id, 'sub' => null]);
    }

    public function test_menu_requires_a_name(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/menus', ['link' => '/x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_menu_parent_must_exist(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/menus', ['name' => 'Órfão', 'sub' => 99999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sub');
    }

    public function test_menu_cannot_be_parented_to_its_own_descendant(): void
    {
        $parent = $this->makeMenu(['name' => 'A']);
        $child = $this->makeMenu(['name' => 'B', 'sub' => (string) $parent->id]);
        $grandchild = $this->makeMenu(['name' => 'C', 'sub' => (string) $child->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/menus/{$parent->id}", ['sub' => $grandchild->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sub');
    }

    public function test_student_cannot_manage_menus(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/menus')
            ->assertForbidden();
    }

    public function test_guest_cannot_list_menus(): void
    {
        $this->getJson('/api/v1/admin/menus')->assertUnauthorized();
    }
}
