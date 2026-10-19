<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class CompanyBrandingTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
        $this->superAdmin = $this->makeSuperAdmin();
        Storage::fake('public');
    }

    private function adminToken(): string
    {
        return $this->apiLogin($this->superAdmin);
    }

    private function setting(string $name): ?string
    {
        return CompanySetting::query()
            ->where('company_id', $this->company->id)
            ->where('name', $name)
            ->value('value');
    }

    public function test_admin_can_view_branding_settings(): void
    {
        $this->withToken($this->adminToken())
            ->getJson("/api/v1/admin/companies/{$this->company->id}/settings")
            ->assertOk()
            ->assertJsonPath('data.primary_color', null)
            ->assertJsonPath('data.logo_light', null)
            ->assertJsonPath('data.logo_light_url', null)
            ->assertJsonPath('data.logo_dark', null)
            ->assertJsonPath('data.logo_dark_url', null)
            ->assertJsonPath('data.logo', null)
            ->assertJsonPath('data.logo_url', null);
    }

    public function test_admin_can_update_the_primary_color(): void
    {
        $this->withToken($this->adminToken())
            ->putJson("/api/v1/admin/companies/{$this->company->id}/settings", ['primary_color' => '#ff8800'])
            ->assertOk()
            ->assertJsonPath('data.primary_color', '#ff8800');

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $this->company->id,
            'name' => 'primary_color',
            'value' => '#ff8800',
        ]);
    }

    public function test_admin_can_upload_and_replace_each_theme_logo_independently(): void
    {
        $response = $this->withToken($this->adminToken())
            ->post("/api/v1/admin/companies/{$this->company->id}/settings", [
                '_method' => 'PUT',
                'logo_light' => UploadedFile::fake()->image('logo-light.png', 240, 80),
                'logo_dark' => UploadedFile::fake()->image('logo-dark.png', 240, 80),
            ])
            ->assertOk();

        $light = $this->setting('logo_light');
        $dark = $this->setting('logo_dark');

        $this->assertNotNull($light);
        $this->assertNotNull($dark);
        $this->assertNotSame($light, $dark);
        Storage::disk('public')->assertExists($light);
        Storage::disk('public')->assertExists($dark);
        $this->assertSame(Storage::disk('public')->url($light), $response->json('data.logo_light_url'));
        $this->assertSame(Storage::disk('public')->url($dark), $response->json('data.logo_dark_url'));
        $this->assertSame(Storage::disk('public')->url($light), $response->json('data.logo_url'));

        $this->withToken($this->adminToken())
            ->post("/api/v1/admin/companies/{$this->company->id}/settings", [
                '_method' => 'PUT',
                'logo_light' => UploadedFile::fake()->image('logo-light-2.png', 240, 80),
            ])
            ->assertOk();

        $newLight = $this->setting('logo_light');

        $this->assertNotSame($light, $newLight);
        $this->assertSame($dark, $this->setting('logo_dark'));
        Storage::disk('public')->assertMissing($light);
        Storage::disk('public')->assertExists($newLight);
        Storage::disk('public')->assertExists($dark);

        $this->withToken($this->adminToken())
            ->post("/api/v1/admin/companies/{$this->company->id}/settings", [
                '_method' => 'PUT',
                'logo_dark' => UploadedFile::fake()->image('logo-dark-2.png', 240, 80),
            ])
            ->assertOk();

        $newDark = $this->setting('logo_dark');

        $this->assertNotSame($dark, $newDark);
        $this->assertSame($newLight, $this->setting('logo_light'));
        Storage::disk('public')->assertMissing($dark);
        Storage::disk('public')->assertExists($newDark);
        Storage::disk('public')->assertExists($newLight);
    }

    public function test_admin_can_reset_primary_color_to_default(): void
    {
        CompanySetting::query()->create([
            'company_id' => $this->company->id,
            'name' => 'primary_color',
            'value' => '#ff8800',
        ]);

        $this->withToken($this->adminToken())
            ->putJson("/api/v1/admin/companies/{$this->company->id}/settings", ['primary_color' => null])
            ->assertOk()
            ->assertJsonPath('data.primary_color', null);

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $this->company->id,
            'name' => 'primary_color',
            'value' => null,
        ]);
    }

    public function test_reset_keeps_the_uploaded_logos(): void
    {
        CompanySetting::query()->create([
            'company_id' => $this->company->id,
            'name' => 'primary_color',
            'value' => '#ff8800',
        ]);
        CompanySetting::query()->create([
            'company_id' => $this->company->id,
            'name' => 'logo_light',
            'value' => 'branding/1/logo-light.png',
        ]);
        CompanySetting::query()->create([
            'company_id' => $this->company->id,
            'name' => 'logo_dark',
            'value' => 'branding/1/logo-dark.png',
        ]);

        $this->withToken($this->adminToken())
            ->putJson("/api/v1/admin/companies/{$this->company->id}/settings", ['primary_color' => null])
            ->assertOk()
            ->assertJsonPath('data.primary_color', null)
            ->assertJsonPath('data.logo_light', 'branding/1/logo-light.png')
            ->assertJsonPath(
                'data.logo_light_url',
                Storage::disk('public')->url('branding/1/logo-light.png'),
            )
            ->assertJsonPath('data.logo_dark', 'branding/1/logo-dark.png')
            ->assertJsonPath(
                'data.logo_dark_url',
                Storage::disk('public')->url('branding/1/logo-dark.png'),
            )
            ->assertJsonPath('data.logo', 'branding/1/logo-light.png')
            ->assertJsonPath(
                'data.logo_url',
                Storage::disk('public')->url('branding/1/logo-light.png'),
            );
    }

    public function test_legacy_logo_setting_is_exposed_as_the_light_logo(): void
    {
        CompanySetting::query()->create([
            'company_id' => $this->company->id,
            'name' => 'logo',
            'value' => 'branding/1/legacy-logo.png',
        ]);

        $this->withToken($this->adminToken())
            ->getJson("/api/v1/admin/companies/{$this->company->id}/settings")
            ->assertOk()
            ->assertJsonPath('data.logo_light', 'branding/1/legacy-logo.png')
            ->assertJsonPath(
                'data.logo_light_url',
                Storage::disk('public')->url('branding/1/legacy-logo.png'),
            )
            ->assertJsonPath('data.logo_dark', null)
            ->assertJsonPath('data.logo_dark_url', null)
            ->assertJsonPath('data.logo', 'branding/1/legacy-logo.png')
            ->assertJsonPath(
                'data.logo_url',
                Storage::disk('public')->url('branding/1/legacy-logo.png'),
            );
    }

    public function test_primary_color_must_be_a_valid_hex(): void
    {
        $this->withToken($this->adminToken())
            ->putJson("/api/v1/admin/companies/{$this->company->id}/settings", ['primary_color' => 'verde'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('primary_color');
    }

    public function test_theme_logos_must_be_valid_images(): void
    {
        foreach (['logo_light', 'logo_dark'] as $field) {
            $this->withToken($this->adminToken())
                ->post("/api/v1/admin/companies/{$this->company->id}/settings", [
                    '_method' => 'PUT',
                    $field => UploadedFile::fake()->create('arquivo.txt', 1, 'text/plain'),
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors($field);
        }
    }

    public function test_students_cannot_manage_company_settings(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson("/api/v1/admin/companies/{$this->company->id}/settings")
            ->assertForbidden();
    }

    public function test_super_admin_can_manage_another_company_branding(): void
    {
        $other = Company::factory()->create();

        CompanySetting::query()->create([
            'company_id' => $other->id,
            'name' => 'primary_color',
            'value' => '#111111',
        ]);

        $this->withToken($this->adminToken())
            ->putJson("/api/v1/admin/companies/{$other->id}/settings", ['primary_color' => '#abcdef'])
            ->assertOk()
            ->assertJsonPath('data.primary_color', '#abcdef');

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $other->id,
            'name' => 'primary_color',
            'value' => '#abcdef',
        ]);

        $this->assertDatabaseMissing('company_settings', [
            'company_id' => $this->company->id,
            'name' => 'primary_color',
        ]);
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
            'name' => 'logo_light',
            'value' => 'branding/1/logo-light.png',
        ]);
        CompanySetting::query()->create([
            'company_id' => $this->company->id,
            'name' => 'logo_dark',
            'value' => 'branding/1/logo-dark.png',
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
            ->assertJsonPath('data.logo_light', 'branding/1/logo-light.png')
            ->assertJsonPath(
                'data.logo_light_url',
                Storage::disk('public')->url('branding/1/logo-light.png'),
            )
            ->assertJsonPath('data.logo_dark', 'branding/1/logo-dark.png')
            ->assertJsonPath(
                'data.logo_dark_url',
                Storage::disk('public')->url('branding/1/logo-dark.png'),
            )
            ->assertJsonPath('data.logo', 'branding/1/logo-light.png')
            ->assertJsonPath(
                'data.logo_url',
                Storage::disk('public')->url('branding/1/logo-light.png'),
            );
    }

    public function test_public_branding_endpoint_requires_a_tenant(): void
    {
        $this->getJson('/api/v1/branding')->assertStatus(400);
    }
}
