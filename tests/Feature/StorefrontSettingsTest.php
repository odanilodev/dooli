<?php

namespace Tests\Feature;

use App\Enums\BannerPlacement;
use App\Enums\GeneralStatus;
use App\Models\Banner;
use App\Models\Company;
use App\Models\Gateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class StorefrontSettingsTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    private function adminToken(): string
    {
        return $this->apiLogin($this->admin);
    }

    public function test_admin_can_view_the_default_storefront_copy(): void
    {
        $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/storefront')
            ->assertOk()
            ->assertJsonPath('data.catalog.title', 'Todos os cursos')
            ->assertJsonCount(3, 'data.benefits')
            ->assertJsonPath('data.internal.title', 'Conteúdos extras');
    }

    public function test_admin_can_update_storefront_copy_and_the_public_store_receives_it(): void
    {
        $response = $this->withToken($this->adminToken())
            ->putJson('/api/v1/admin/storefront', [
                'catalog' => [
                    'eyebrow' => 'Vitrine',
                    'title' => 'Cursos selecionados',
                    'description' => 'Uma descrição personalizada.',
                ],
                'benefits' => [
                    ['title' => 'Benefício 1', 'description' => 'Descrição 1'],
                    ['title' => 'Benefício 2', 'description' => 'Descrição 2'],
                ],
                'internal' => [
                    'eyebrow' => 'Área do aluno',
                    'title' => 'Loja interna',
                    'description' => 'Ofertas para alunos.',
                ],
                'footer' => ['note' => 'Rodapé personalizado.'],
            ])
            ->assertOk()
            ->assertJsonPath('data.catalog.title', 'Cursos selecionados')
            ->assertJsonCount(2, 'data.benefits');

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $this->company->id,
            'name' => 'storefront',
        ]);

        $this->getJson('/api/v1/public/companies/'.$this->company->slug)
            ->assertOk()
            ->assertJsonPath('data.storefront.settings.catalog.title', 'Cursos selecionados')
            ->assertJsonPath('data.storefront.settings.benefits.0.title', 'Benefício 1')
            ->assertJsonPath('data.storefront.settings.footer.note', 'Rodapé personalizado.');

        $this->assertSame('Cursos selecionados', $response->json('data.catalog.title'));
    }

    public function test_partial_update_keeps_the_previous_storefront_copy(): void
    {
        $this->withToken($this->adminToken())
            ->putJson('/api/v1/admin/storefront', [
                'catalog' => ['title' => 'Título persistente'],
                'footer' => ['note' => 'Primeiro rodapé'],
            ])
            ->assertOk();

        $this->withToken($this->adminToken())
            ->putJson('/api/v1/admin/storefront', [
                'catalog' => ['description' => null],
                'footer' => ['note' => 'Rodapé atualizado'],
            ])
            ->assertOk()
            ->assertJsonPath('data.catalog.title', 'Título persistente')
            ->assertJsonPath('data.catalog.description', null)
            ->assertJsonPath('data.footer.note', 'Rodapé atualizado');
    }

    public function test_public_store_receives_only_active_store_banners_from_its_company(): void
    {
        $storeBanner = Banner::query()->create([
            'company_id' => $this->company->id,
            'placement' => BannerPlacement::Store->value,
            'name' => 'Banner da loja',
            'title' => 'Oferta da loja',
            'sort_order' => 1,
            'status' => GeneralStatus::Active->value,
        ]);

        Banner::query()->create([
            'company_id' => $this->company->id,
            'placement' => BannerPlacement::Home->value,
            'name' => 'Banner da home',
            'title' => 'Oferta da home',
            'sort_order' => 0,
            'status' => GeneralStatus::Active->value,
        ]);

        Banner::query()->create([
            'company_id' => $this->company->id,
            'placement' => BannerPlacement::Store->value,
            'name' => 'Banner inativo',
            'status' => GeneralStatus::Inactive->value,
        ]);

        $other = Company::factory()->create();
        Banner::query()->create([
            'company_id' => $other->id,
            'placement' => BannerPlacement::Store->value,
            'name' => 'Banner de outra empresa',
            'status' => GeneralStatus::Active->value,
        ]);

        $this->getJson('/api/v1/public/companies/'.$this->company->slug)
            ->assertOk()
            ->assertJsonCount(1, 'data.storefront.banners')
            ->assertJsonPath('data.storefront.banners.0.id', $storeBanner->id)
            ->assertJsonPath('data.storefront.banners.0.title', 'Oferta da loja');
    }

    public function test_admin_can_create_a_store_banner(): void
    {
        $this->withToken($this->adminToken())
            ->postJson('/api/v1/admin/banners', [
                'placement' => BannerPlacement::Store->value,
                'name' => 'Topo da loja',
                'title' => 'Novo destaque',
                'status' => GeneralStatus::Active->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.placement', BannerPlacement::Store->value)
            ->assertJsonPath('data.title', 'Novo destaque');
    }

    public function test_admin_banner_list_can_be_filtered_by_placement(): void
    {
        Banner::query()->create([
            'company_id' => $this->company->id,
            'placement' => BannerPlacement::Home->value,
            'name' => 'Home',
            'status' => GeneralStatus::Active->value,
        ]);

        Banner::query()->create([
            'company_id' => $this->company->id,
            'placement' => BannerPlacement::Store->value,
            'name' => 'Loja',
            'status' => GeneralStatus::Active->value,
        ]);

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/banners?placement=store')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Loja')
            ->assertJsonPath('data.0.placement', 'store');
    }

    public function test_internal_storefront_endpoint_uses_the_company_header(): void
    {
        Banner::query()->create([
            'company_id' => $this->company->id,
            'placement' => BannerPlacement::Store->value,
            'name' => 'Banner da loja interna',
            'title' => 'Destaque interno',
            'status' => GeneralStatus::Active->value,
        ]);

        Gateway::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'mercadopago',
            'status' => GeneralStatus::Active->value,
        ]);

        $this->withHeaders(['X-Company' => (string) $this->company->id])
            ->getJson('/api/v1/storefront')
            ->assertOk()
            ->assertJsonPath('data.company.id', $this->company->id)
            ->assertJsonPath('data.company.slug', $this->company->slug)
            ->assertJsonPath('data.company.gateways.0.code', 'mercadopago')
            ->assertJsonPath('data.settings.catalog.title', 'Todos os cursos')
            ->assertJsonCount(1, 'data.banners')
            ->assertJsonPath('data.banners.0.title', 'Destaque interno');
    }

    public function test_students_cannot_manage_storefront_settings(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/storefront')
            ->assertForbidden();

        $this->withToken($this->apiLogin($this->student))
            ->putJson('/api/v1/admin/storefront', ['catalog' => ['title' => 'Inválido']])
            ->assertForbidden();
    }

    public function test_storefront_settings_are_isolated_between_companies(): void
    {
        $other = Company::factory()->create();

        $this->withToken($this->adminToken())
            ->putJson('/api/v1/admin/storefront', [
                'catalog' => ['title' => 'Somente desta empresa'],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('company_settings', [
            'company_id' => $other->id,
            'name' => 'storefront',
        ]);
    }
}
