<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Seller;
use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class DomainTenantTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_public_payload_and_catalog_resolve_the_company_by_domain(): void
    {
        $this->company->update(['domain' => 'ourovelho.test']);
        $data = $this->buildCourse($this->company->id);

        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $data['course']->id,
            'title' => 'Curso Ouro',
            'slug' => 'curso-ouro',
        ]);

        $headers = ['X-Company-Domain' => 'WWW.OuroVelho.TEST:443'];

        $this->withHeaders($headers)
            ->getJson('/api/v1/public/tenant')
            ->assertOk()
            ->assertJsonPath('data.id', $this->company->id)
            ->assertJsonPath('data.slug', $this->company->slug);

        $this->withHeaders($headers)
            ->getJson('/api/v1/public/tenant/store/products')
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id);
    }

    public function test_catalog_does_not_leak_products_from_another_company(): void
    {
        $this->company->update(['domain' => 'ourovelho.test']);

        $other = Company::factory()->create(['domain' => 'outra.test']);
        $otherCourse = $this->buildCourse($other->id);

        StoreProduct::factory()->create([
            'company_id' => $other->id,
            'course_id' => $otherCourse['course']->id,
            'title' => 'Curso de outra empresa',
        ]);

        $data = $this->buildCourse($this->company->id);
        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $data['course']->id,
            'title' => 'Curso da Ouro',
        ]);

        $response = $this->withHeader('X-Company-Domain', 'ourovelho.test')
            ->getJson('/api/v1/public/tenant/store/products')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($product->id, $response->json('data.0.id'));
    }

    public function test_login_is_scoped_to_the_domain_company(): void
    {
        $this->company->update(['domain' => 'ourovelho.test']);

        $other = Company::factory()->create(['domain' => 'outra.test']);
        $otherUser = User::factory()->create([
            'company_id' => $other->id,
            'email' => 'aluno@outra.test',
        ]);

        $this->withHeader('X-Company-Domain', 'ourovelho.test')
            ->postJson('/api/v1/public/tenant/auth/login', [
                'email' => $this->student->email,
                'password' => 'password',
                'device_name' => 'web',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $this->student->id);

        $this->withHeader('X-Company-Domain', 'ourovelho.test')
            ->postJson('/api/v1/public/tenant/auth/login', [
                'email' => $otherUser->email,
                'password' => 'password',
                'device_name' => 'web',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'invalid_credentials');
    }

    public function test_authenticated_requests_ignore_the_domain_header(): void
    {
        $this->company->update(['domain' => 'ourovelho.test']);
        $other = Company::factory()->create(['domain' => 'outra.test']);

        $token = $this->apiLogin($this->student);

        $this->withToken($token)
            ->withHeader('X-Company-Domain', 'outra.test')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $this->student->id)
            ->assertJsonPath('data.company_id', $this->company->id);

        $this->assertNotSame($other->id, $this->company->id);
    }

    public function test_unknown_domain_returns_company_not_found(): void
    {
        $this->withHeader('X-Company-Domain', 'naoexiste.test')
            ->getJson('/api/v1/public/tenant')
            ->assertNotFound()
            ->assertJsonPath('code', 'company_not_found');
    }

    public function test_platform_domains_are_never_resolved_as_tenants(): void
    {
        config()->set('app.platform_domains', ['admin.plataforma.test']);

        $this->withHeader('X-Company-Domain', 'admin.plataforma.test')
            ->getJson('/api/v1/public/tenant')
            ->assertNotFound()
            ->assertJsonPath('code', 'company_not_found');
    }

    public function test_company_urls_use_the_domain_when_configured(): void
    {
        config()->set('app.frontend_scheme', 'https');
        config()->set('app.frontend_url', 'http://localhost:5174');

        $this->company->update(['domain' => 'ourovelho.test']);

        $this->assertSame('https://ourovelho.test/', $this->company->publicStoreUrl());
        $this->assertSame('https://ourovelho.test/?ref=joao123', $this->company->publicStoreUrl('joao123'));
        $this->assertSame('https://ourovelho.test/produto/curso-php?ref=joao123', $this->company->publicProductUrl('curso-php', 'joao123'));
        $this->assertSame('https://ourovelho.test/vendedor/token-123', $this->company->sellerKitUrl('token-123'));
        $this->assertSame('https://ourovelho.test/pedido/42', $this->company->publicOrderUrl(42));
        $this->assertSame('https://ourovelho.test/minhas-assinaturas', $this->company->publicSubscriptionsUrl());

        $this->company->update(['domain' => null]);

        $this->assertSame(
            'http://localhost:5174/e/'.$this->company->slug.'/loja',
            $this->company->publicStoreUrl(),
        );
        $this->assertSame(
            'http://localhost:5174/e/'.$this->company->slug.'/loja/curso-php?ref=joao123',
            $this->company->publicProductUrl('curso-php', 'joao123'),
        );
        $this->assertSame(
            'http://localhost:5174/e/'.$this->company->slug.'/vendedor/token-123',
            $this->company->sellerKitUrl('token-123'),
        );
        $this->assertSame(
            'http://localhost:5174/e/'.$this->company->slug.'/pedido/42',
            $this->company->publicOrderUrl(42),
        );
        $this->assertSame(
            'http://localhost:5174/e/'.$this->company->slug.'/assinaturas',
            $this->company->publicSubscriptionsUrl(),
        );
    }

    public function test_seller_kit_resolves_the_tenant_by_domain(): void
    {
        $this->company->update(['domain' => 'vendas.test']);

        $seller = Seller::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $this->withHeader('X-Company-Domain', 'vendas.test')
            ->getJson('/api/v1/public/tenant/seller-kits/'.$seller->share_token)
            ->assertOk()
            ->assertJsonPath('data.seller.name', $seller->name);
    }
}
