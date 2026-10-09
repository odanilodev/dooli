<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class CompanyBrandingTest extends TestCase
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

    public function test_admin_can_view_branding_settings(): void
    {
        $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/company/settings')
            ->assertOk()
            ->assertJsonPath('data.primary_color', null)
            ->assertJsonPath('data.logo', null)
            ->assertJsonPath('data.logo_url', null);
    }

    public function test_admin_can_update_the_primary_color(): void
    {
        $this->withToken($this->adminToken())
            ->putJson('/api/v1/admin/company/settings', ['primary_color' => '#ff8800'])
            ->assertOk()
            ->assertJsonPath('data.primary_color', '#ff8800');

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $this->company->id,
            'name' => 'primary_color',
            'value' => '#ff8800',
        ]);
    }

    public function test_admin_can_upload_a_logo_and_replace_it(): void
    {
        $response = $this->withToken($this->adminToken())
            ->post('/api/v1/admin/company/settings', [
                '_method' => 'PUT',
                'logo' => UploadedFile::fake()->image('logo.png', 240, 80),
            ])
            ->assertOk();

        $first = CompanySetting::query()
            ->where('company_id', $this->company->id)
            ->where('name', 'logo')
            ->value('value');

        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);
        $this->assertSame(Storage::disk('public')->url($first), $response->json('data.logo_url'));

        $this->withToken($this->adminToken())
            ->post('/api/v1/admin/company/settings', [
                '_method' => 'PUT',
                'logo' => UploadedFile::fake()->image('logo2.png', 240, 80),
            ])
            ->assertOk();

        $second = CompanySetting::query()
            ->where('company_id', $this->company->id)
            ->where('name', 'logo')
            ->value('value');

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_admin_can_reset_primary_color_to_default(): void
    {
        CompanySetting::query()->create([
            'company_id' => $this->company->id,
            'name' => 'primary_color',
            'value' => '#ff8800',
        ]);

        $this->withToken($this->adminToken())
            ->putJson('/api/v1/admin/company/settings', ['primary_color' => null])
            ->assertOk()
            ->assertJsonPath('data.primary_color', null);

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $this->company->id,
            'name' => 'primary_color',
            'value' => null,
        ]);
    }

    public function test_reset_keeps_the_uploaded_logo(): void
    {
        CompanySetting::query()->create([
            'company_id' => $this->company->id,
            'name' => 'primary_color',
            'value' => '#ff8800',
        ]);
        CompanySetting::query()->create([
            'company_id' => $this->company->id,
            'name' => 'logo',
            'value' => 'branding/1/logo.png',
        ]);

        $this->withToken($this->adminToken())
            ->putJson('/api/v1/admin/company/settings', ['primary_color' => null])
            ->assertOk()
            ->assertJsonPath('data.primary_color', null)
            ->assertJsonPath('data.logo', 'branding/1/logo.png')
            ->assertJsonPath('data.logo_url', Storage::disk('public')->url('branding/1/logo.png'));
    }

    public function test_primary_color_must_be_a_valid_hex(): void
    {
        $this->withToken($this->adminToken())
            ->putJson('/api/v1/admin/company/settings', ['primary_color' => 'verde'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('primary_color');
    }

    public function test_logo_must_be_a_valid_image(): void
    {
        $this->withToken($this->adminToken())
            ->post('/api/v1/admin/company/settings', [
                '_method' => 'PUT',
                'logo' => UploadedFile::fake()->create('arquivo.txt', 1, 'text/plain'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('logo');
    }

    public function test_students_cannot_manage_company_settings(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/company/settings')
            ->assertForbidden();
    }

    public function test_public_branding_endpoint_returns_the_tenant_settings(): void
    {
        CompanySetting::query()->create([
            'company_id' => $this->company->id,
            'name' => 'primary_color',
            'value' => '#123456',
        ]);
        CompanySetting::query()->create([
            'company_id' => $this->company->id,
            'name' => 'logo',
            'value' => 'branding/1/logo.png',
        ]);

        $other = Company::factory()->create();
        CompanySetting::query()->create([
            'company_id' => $other->id,
            'name' => 'primary_color',
            'value' => '#000000',
        ]);

        $this->withHeaders(['X-Company' => (string) $this->company->id])
            ->getJson('/api/v1/branding')
            ->assertOk()
            ->assertJsonPath('data.primary_color', '#123456')
            ->assertJsonPath('data.logo', 'branding/1/logo.png')
            ->assertJsonPath('data.logo_url', Storage::disk('public')->url('branding/1/logo.png'));
    }

    public function test_public_branding_endpoint_requires_a_tenant(): void
    {
        $this->getJson('/api/v1/branding')->assertStatus(400);
    }
}
