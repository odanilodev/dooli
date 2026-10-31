<?php

namespace Tests\Feature;

use App\Mail\TransactionalMail;
use App\Models\CompanyMailSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class CompanyMailSettingTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_super_admin_can_view_mail_defaults(): void
    {
        $this->withToken($this->apiLogin($this->makeSuperAdmin()))
            ->getJson($this->endpoint())
            ->assertOk()
            ->assertJsonPath('data.provider', 'resend')
            ->assertJsonPath('data.domain_status', 'not_started')
            ->assertJsonPath('data.has_credentials', false)
            ->assertJsonPath('data.is_ready', false);
    }

    public function test_normal_admin_cannot_manage_mail_settings(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->getJson($this->endpoint())
            ->assertForbidden();

        $this->withToken($this->apiLogin($this->admin))
            ->putJson($this->endpoint(), ['from_address' => 'no-reply@example.com'])
            ->assertForbidden();
    }

    public function test_super_admin_can_save_credentials_without_exposing_them(): void
    {
        $this->withToken($this->apiLogin($this->makeSuperAdmin()))
            ->putJson($this->endpoint(), [
                'from_name' => 'Loja Teste',
                'from_address' => 'no-reply@example.com',
                'reply_to' => 'suporte@example.com',
                'credentials' => ['api_key' => 're_secret_key'],
            ])
            ->assertOk()
            ->assertJsonPath('data.from_address', 'no-reply@example.com')
            ->assertJsonPath('data.email_domain', 'example.com')
            ->assertJsonPath('data.has_credentials', true)
            ->assertJsonMissingPath('data.credentials');

        $raw = (string) DB::table('company_mail_settings')->value('credentials');

        $this->assertStringNotContainsString('re_secret_key', $raw);
        $this->assertNotEmpty($raw);
    }

    public function test_sender_must_belong_to_the_configured_domain(): void
    {
        $this->withToken($this->apiLogin($this->makeSuperAdmin()))
            ->putJson($this->endpoint(), [
                'from_address' => 'no-reply@other.com',
                'email_domain' => 'example.com',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'mail_sender_domain_mismatch');
    }

    public function test_empty_api_key_keeps_the_existing_credentials(): void
    {
        CompanyMailSetting::query()->create([
            'company_id' => $this->company->id,
            'from_address' => 'no-reply@example.com',
            'email_domain' => 'example.com',
            'credentials' => ['api_key' => 're_existing'],
        ]);

        $this->withToken($this->apiLogin($this->makeSuperAdmin()))
            ->putJson($this->endpoint(), [
                'from_name' => 'Novo Nome',
                'credentials' => ['api_key' => ''],
            ])
            ->assertOk()
            ->assertJsonPath('data.from_name', 'Novo Nome')
            ->assertJsonPath('data.has_credentials', true);
    }

    public function test_test_mail_uses_the_company_identity(): void
    {
        Mail::fake();

        CompanyMailSetting::query()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
            'from_name' => 'Loja Teste',
            'from_address' => 'no-reply@example.com',
            'email_domain' => 'example.com',
            'domain_status' => 'verified',
            'credentials' => ['api_key' => 're_test'],
        ]);

        $this->withToken($this->apiLogin($this->makeSuperAdmin()))
            ->postJson($this->endpoint('/test'), ['email' => 'destino@example.com'])
            ->assertOk()
            ->assertJsonPath('data.message', 'E-mail de teste enviado.');

        Mail::assertSent(
            TransactionalMail::class,
            fn (TransactionalMail $mail) => $mail->hasTo('destino@example.com')
                && ($mail->from[0]['address'] ?? null) === 'no-reply@example.com',
        );

        $setting = CompanyMailSetting::query()->firstOrFail();

        $this->assertNotNull($setting->last_tested_at);
        $this->assertNull($setting->last_error);
    }

    public function test_register_domain_stores_the_dns_records(): void
    {
        CompanyMailSetting::query()->create([
            'company_id' => $this->company->id,
            'from_address' => 'no-reply@example.com',
            'email_domain' => 'example.com',
            'credentials' => ['api_key' => 're_test'],
        ]);

        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response(['data' => []]);
            }

            return Http::response([
                'id' => 'dom_123',
                'name' => 'example.com',
                'status' => 'pending',
                'records' => [
                    ['record' => 'MX', 'name' => 'send', 'value' => 'feedback-smtp.example.com', 'type' => 'MX'],
                    ['record' => 'TXT', 'name' => 'send', 'value' => 'v=spf1 include:resend.com', 'type' => 'TXT'],
                ],
            ]);
        });

        $this->withToken($this->apiLogin($this->makeSuperAdmin()))
            ->postJson($this->endpoint('/register-domain'))
            ->assertOk()
            ->assertJsonPath('data.domain_id', 'dom_123')
            ->assertJsonPath('data.domain_status', 'pending')
            ->assertJsonCount(2, 'data.dns_records');
    }

    private function endpoint(string $suffix = ''): string
    {
        return '/api/v1/admin/companies/'.$this->company->id.'/mail-settings'.$suffix;
    }
}
