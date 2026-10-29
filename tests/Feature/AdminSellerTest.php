<?php

namespace Tests\Feature;

use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Seller;
use App\Models\StoreProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AdminSellerTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_admin_can_create_seller_and_receive_kit_url(): void
    {
        $response = $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/sellers', [
                'name' => 'João Vendedor',
                'email' => 'joao@example.com',
                'phone' => '(11) 99999-9999',
                'commission_percent' => 12.5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'João Vendedor')
            ->assertJsonPath('data.code', 'joao-vendedor')
            ->assertJsonPath('data.status', GeneralStatus::Active->value)
            ->assertJsonPath('data.metrics.students_count', 0);

        $seller = Seller::query()->firstOrFail();
        $kitUrl = $response->json('data.kit_url');

        $this->assertStringContainsString('/e/'.$this->company->slug.'/vendedor/'.$seller->share_token, $kitUrl);
        $this->assertArrayNotHasKey('share_token', $response->json('data'));
    }

    public function test_seller_code_and_linked_user_are_scoped_to_company(): void
    {
        $otherCompany = Company::factory()->create();
        $otherSeller = Seller::factory()->create([
            'company_id' => $otherCompany->id,
            'code' => 'joao',
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/sellers', [
                'name' => 'João',
                'code' => 'joao',
                'commission_percent' => 10,
            ])
            ->assertCreated();

        $this->assertSame(
            $otherSeller->code,
            Seller::query()->where('company_id', $this->company->id)->firstOrFail()->code,
        );

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/sellers', [
                'name' => 'Outro',
                'code' => 'joao',
                'commission_percent' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_student_cannot_manage_sellers(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/sellers')
            ->assertStatus(403);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/admin/sellers', [
                'name' => 'João',
                'commission_percent' => 10,
            ])
            ->assertStatus(403);
    }

    public function test_public_kit_lists_released_products_with_referral_links(): void
    {
        $seller = Seller::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'joao123',
        ]);

        StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'title' => 'Curso de PHP',
            'slug' => 'curso-de-php',
            'status' => GeneralStatus::Active->value,
            'release_at' => null,
        ]);

        StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'title' => 'Produto futuro',
            'status' => GeneralStatus::Active->value,
            'release_at' => now()->addWeek(),
        ]);

        $response = $this->getJson(
            '/api/v1/public/companies/'.$this->company->slug.'/seller-kits/'.$seller->share_token,
        )
            ->assertOk()
            ->assertJsonPath('data.seller.name', $seller->name)
            ->assertJsonPath('data.seller.code', 'joao123')
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.title', 'Curso de PHP');

        $shareUrl = $response->json('data.products.0.share_url');

        $this->assertStringContainsString('/e/'.$this->company->slug.'/loja/curso-de-php', $shareUrl);
        $this->assertStringContainsString('ref=joao123', $shareUrl);
    }

    public function test_inactive_seller_kit_is_not_available(): void
    {
        $seller = Seller::factory()->inactive()->create([
            'company_id' => $this->company->id,
        ]);

        $this->getJson(
            '/api/v1/public/companies/'.$this->company->slug.'/seller-kits/'.$seller->share_token,
        )->assertNotFound();
    }

    public function test_rotating_kit_token_invalidates_the_old_link(): void
    {
        $seller = Seller::factory()->create(['company_id' => $this->company->id]);
        $oldToken = $seller->share_token;

        $response = $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/sellers/'.$seller->id.'/rotate-kit')
            ->assertOk();

        $newToken = $seller->fresh()->share_token;

        $this->assertNotSame($oldToken, $newToken);
        $this->assertStringContainsString('/vendedor/'.$newToken, $response->json('data.kit_url'));

        $this->getJson('/api/v1/public/companies/'.$this->company->slug.'/seller-kits/'.$oldToken)
            ->assertNotFound();
        $this->getJson('/api/v1/public/companies/'.$this->company->slug.'/seller-kits/'.$newToken)
            ->assertOk();
    }
}
