<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_super_admin_reaches_menus_companies_and_branding(): void
    {
        $token = $this->apiLogin($this->makeSuperAdmin());

        $this->withToken($token)->getJson('/api/v1/admin/menus')->assertOk();
        $this->withToken($token)->getJson('/api/v1/admin/companies')->assertOk();
        $this->withToken($token)->getJson("/api/v1/admin/companies/{$this->company->id}/settings")->assertOk();
    }

    public function test_super_admin_is_hidden_from_normal_admin_user_listing(): void
    {
        $this->makeSuperAdmin();

        $response = $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/users')
            ->assertOk();

        $this->assertFalse(
            collect($response->json('data'))->contains(fn ($user) => $user['is_super_admin'] === true),
        );
    }

    public function test_super_admin_sees_other_super_admins_in_user_listing(): void
    {
        $super = $this->makeSuperAdmin();
        $viewer = $this->makeSuperAdmin();

        $response = $this->withToken($this->apiLogin($viewer))
            ->getJson('/api/v1/admin/users')
            ->assertOk();

        $this->assertTrue(
            collect($response->json('data'))->contains(fn ($user) => $user['id'] === $super->id),
        );
    }

    public function test_normal_admin_cannot_view_update_or_delete_a_super_admin(): void
    {
        $super = $this->makeSuperAdmin();
        $token = $this->apiLogin($this->admin);

        $this->withToken($token)->getJson("/api/v1/admin/users/{$super->id}")->assertForbidden();
        $this->withToken($token)->putJson("/api/v1/admin/users/{$super->id}", ['name' => 'Hack'])->assertForbidden();
        $this->withToken($token)->deleteJson("/api/v1/admin/users/{$super->id}")->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $super->id]);
    }

    public function test_normal_admin_bulk_actions_skip_super_admins(): void
    {
        $super = $this->makeSuperAdmin();

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/users/bulk', [
                'action' => 'delete',
                'user_ids' => [$super->id],
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $super->id]);
    }

    public function test_normal_admin_cannot_reach_menus_companies_or_branding(): void
    {
        $token = $this->apiLogin($this->admin);

        $this->withToken($token)->getJson('/api/v1/admin/menus')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/companies')->assertForbidden();
        $this->withToken($token)->getJson("/api/v1/admin/companies/{$this->company->id}/settings")->assertForbidden();
    }

    public function test_normal_admin_keeps_regular_admin_access(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/courses')
            ->assertOk();
    }

    public function test_super_admin_creates_company_with_default_types_and_admin(): void
    {
        $response = $this->withToken($this->apiLogin($this->makeSuperAdmin()))
            ->postJson('/api/v1/admin/companies', [
                'name' => 'Nova Escola',
                'domain' => 'https://www.NovaEscola.com.br/',
                'admin_name' => 'Admin Novo',
                'admin_username' => 'novoadmin',
                'admin_email' => 'novoadmin@escola.test',
                'admin_password' => 'password123',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Nova Escola')
            ->assertJsonPath('data.domain', 'novaescola.com.br');

        $companyId = (int) $response->json('data.id');

        $this->assertDatabaseHas('companies', [
            'id' => $companyId,
            'domain' => 'novaescola.com.br',
        ]);

        $codes = UserType::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->pluck('code')
            ->all();

        $this->assertEqualsCanonicalizing(['admin', 'instructor', 'student'], $codes);

        // Sem as ações de progresso a conclusão de aula falha ("Ação de conclusão não configurada").
        $this->assertEqualsCanonicalizing(
            ['started', 'viewed', 'completed'],
            \App\Models\Action::withoutGlobalScopes()->where('company_id', $companyId)->pluck('code')->all(),
        );

        $admin = User::withoutGlobalScopes()->where('email', 'novoadmin@escola.test')->first();

        $this->assertNotNull($admin);
        $this->assertSame($companyId, $admin->company_id);
        $this->assertFalse($admin->isSuperAdmin());
        $this->assertSame('admin', $admin->userType()->withoutGlobalScopes()->value('code'));
    }

    public function test_company_domain_must_be_unique(): void
    {
        Company::factory()->create(['domain' => 'escola.test']);

        $this->withToken($this->apiLogin($this->makeSuperAdmin()))
            ->postJson('/api/v1/admin/companies', [
                'name' => 'Outra Escola',
                'domain' => 'https://www.escola.test/',
                'admin_name' => 'Admin Outro',
                'admin_username' => 'adminoutro',
                'admin_email' => 'adminoutro@escola.test',
                'admin_password' => 'password123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('domain');
    }

    public function test_company_domain_must_be_a_valid_host(): void
    {
        $this->withToken($this->apiLogin($this->makeSuperAdmin()))
            ->postJson('/api/v1/admin/companies', [
                'name' => 'Escola Inválida',
                'domain' => 'dominio invalido',
                'admin_name' => 'Admin Inválido',
                'admin_username' => 'admininvalido',
                'admin_email' => 'admininvalido@escola.test',
                'admin_password' => 'password123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('domain');
    }

    public function test_super_admin_can_update_and_clear_company_domain(): void
    {
        $company = Company::factory()->create(['domain' => 'antigo.test']);
        $token = $this->apiLogin($this->makeSuperAdmin());

        $this->withToken($token)
            ->putJson("/api/v1/admin/companies/{$company->id}", [
                'domain' => 'https://www.NovoDominio.com/',
            ])
            ->assertOk()
            ->assertJsonPath('data.domain', 'novodominio.com');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'domain' => 'novodominio.com',
        ]);

        $this->withToken($token)
            ->putJson("/api/v1/admin/companies/{$company->id}", [
                'domain' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.domain', null);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'domain' => null,
        ]);
    }

    public function test_normal_admin_cannot_create_company(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/companies', [
                'name' => 'X',
                'admin_name' => 'Y',
                'admin_username' => 'yuser',
                'admin_email' => 'y@escola.test',
                'admin_password' => 'password123',
            ])
            ->assertForbidden();
    }

    public function test_normal_admin_cannot_escalate_a_user_to_super_admin(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/users', [
                'name' => 'Fake Super',
                'username' => 'fakesuper',
                'email' => 'fakesuper@cursos.test',
                'password' => 'password123',
                'user_type_id' => $this->admin->user_type_id,
                'is_super_admin' => true,
            ])
            ->assertCreated();

        $created = User::withoutGlobalScopes()->where('email', 'fakesuper@cursos.test')->first();

        $this->assertNotNull($created);
        $this->assertFalse($created->isSuperAdmin());
    }
}
