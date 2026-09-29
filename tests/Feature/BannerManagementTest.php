<?php

namespace Tests\Feature;

use App\Enums\GeneralStatus;
use App\Models\Banner;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class BannerManagementTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
        Storage::fake('public');
    }

    private function adminToken(): string
    {
        return $this->apiLogin($this->admin);
    }

    public function test_admin_can_create_a_banner_with_hero_content_and_image(): void
    {
        $response = $this->withToken($this->adminToken())
            ->post('/api/v1/admin/banners', [
                'name' => 'Home Hero',
                'eyebrow' => 'CURSO EM DESTAQUE',
                'title' => 'Do Zero ao Trader',
                'title_highlight' => 'Consistente',
                'description' => 'Aprenda estratégias e gestão de risco.',
                'quote' => 'Disciplina hoje, liberdade amanhã.',
                'cta_label' => 'Continuar assistindo',
                'cta_link' => '/cursos',
                'secondary_label' => 'Ver detalhes',
                'secondary_link' => '/cursos',
                'image' => UploadedFile::fake()->image('hero.png', 800, 400),
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Do Zero ao Trader')
            ->assertJsonPath('data.eyebrow', 'CURSO EM DESTAQUE')
            ->assertJsonPath('data.cta_label', 'Continuar assistindo');

        $banner = Banner::query()->firstOrFail();

        $this->assertSame($this->company->id, (int) $banner->company_id);
        $this->assertNotNull($banner->image);
        Storage::disk('public')->assertExists($banner->image);
        $this->assertSame(Storage::disk('public')->url($banner->image), $response->json('data.image_url'));
    }

    public function test_admin_can_replace_the_banner_image_and_the_old_file_is_removed(): void
    {
        Storage::disk('public')->put('banners/1/old.jpg', 'old');

        $banner = Banner::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Hero',
            'image' => 'banners/1/old.jpg',
            'status' => GeneralStatus::Active->value,
        ]);

        $this->withToken($this->adminToken())
            ->post("/api/v1/admin/banners/{$banner->id}", [
                '_method' => 'PUT',
                'title' => 'Título novo',
                'image' => UploadedFile::fake()->image('new.png', 640, 360),
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Título novo');

        $banner->refresh();

        $this->assertNotSame('banners/1/old.jpg', $banner->image);
        Storage::disk('public')->assertMissing('banners/1/old.jpg');
        Storage::disk('public')->assertExists($banner->image);
    }

    public function test_banner_image_must_be_a_valid_image(): void
    {
        $this->withToken($this->adminToken())
            ->post('/api/v1/admin/banners', [
                'name' => 'Inválido',
                'image' => UploadedFile::fake()->create('arquivo.txt', 1, 'text/plain'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_deleting_a_banner_removes_the_image_file(): void
    {
        Storage::disk('public')->put('banners/1/gone.jpg', 'x');

        $banner = Banner::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Hero',
            'image' => 'banners/1/gone.jpg',
            'status' => GeneralStatus::Active->value,
        ]);

        $this->withToken($this->adminToken())
            ->deleteJson("/api/v1/admin/banners/{$banner->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('banners', ['id' => $banner->id]);
        Storage::disk('public')->assertMissing('banners/1/gone.jpg');
    }

    public function test_students_cannot_manage_banners(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/banners')
            ->assertForbidden();
    }

    public function test_public_endpoint_returns_only_active_banners_from_the_tenant_ordered_by_sort_order(): void
    {
        Banner::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Segundo',
            'title' => 'B',
            'sort_order' => 2,
            'status' => GeneralStatus::Active->value,
        ]);
        Banner::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Primeiro',
            'title' => 'A',
            'sort_order' => 1,
            'status' => GeneralStatus::Active->value,
        ]);
        Banner::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Inativo',
            'title' => 'C',
            'sort_order' => 0,
            'status' => GeneralStatus::Inactive->value,
        ]);

        $other = Company::factory()->create();
        Banner::query()->create([
            'company_id' => $other->id,
            'name' => 'Outra empresa',
            'title' => 'Z',
            'sort_order' => 0,
            'status' => GeneralStatus::Active->value,
        ]);

        $this->withHeaders(['X-Company' => (string) $this->company->id])
            ->getJson('/api/v1/banners')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'A')
            ->assertJsonPath('data.1.title', 'B');
    }

    public function test_public_banners_endpoint_requires_a_tenant(): void
    {
        $this->getJson('/api/v1/banners')->assertStatus(400);
    }
}
