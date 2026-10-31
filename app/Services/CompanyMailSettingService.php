<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Mail\TransactionalMail;
use App\Models\Company;
use App\Models\CompanyMailSetting;
use Illuminate\Support\Arr;

class CompanyMailSettingService
{
    public function __construct(
        private readonly ResendDomainService $resend,
        private readonly CompanyMailService $mail,
        private readonly DatalogService $audit,
    ) {}

    public function find(Company|int $company): ?CompanyMailSetting
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        return CompanyMailSetting::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(Company $company): array
    {
        return [
            'provider' => 'resend',
            'status' => 'active',
            'from_name' => null,
            'from_address' => null,
            'reply_to' => null,
            'email_domain' => null,
            'region' => (string) config('services.resend.region', 'sa-east-1'),
            'domain_id' => null,
            'domain_status' => 'not_started',
            'dns_records' => null,
            'last_checked_at' => null,
            'last_tested_at' => null,
            'verified_at' => null,
            'last_error' => null,
            'has_credentials' => false,
            'is_ready' => false,
        ];
    }

    public function update(Company $company, array $data): CompanyMailSetting
    {
        $setting = $this->find($company);

        if (array_key_exists('credentials', $data)) {
            $apiKey = trim((string) ($data['credentials']['api_key'] ?? ''));

            if ($apiKey === '') {
                unset($data['credentials']);
            } else {
                $data['credentials'] = ['api_key' => $apiKey];
            }
        }

        $data = Arr::only($data, [
            'provider', 'status', 'from_name', 'from_address', 'reply_to',
            'email_domain', 'region', 'credentials', 'settings',
        ]);

        if (filled($data['from_address'] ?? null) && blank($data['email_domain'] ?? null)) {
            $data['email_domain'] = $this->domainFromEmail((string) $data['from_address']);
        }

        if (filled($data['from_address'] ?? null) && filled($data['email_domain'] ?? null)) {
            $this->assertSenderBelongsToDomain(
                (string) $data['from_address'],
                (string) $data['email_domain'],
            );
        }

        $domainChanged = filled($data['email_domain'] ?? null)
            && $setting?->email_domain !== $data['email_domain'];

        if ($domainChanged) {
            $data = array_merge($data, [
                'domain_id' => null,
                'domain_status' => 'not_started',
                'dns_records' => null,
                'last_checked_at' => null,
                'verified_at' => null,
                'last_error' => null,
            ]);
        }

        if (! $setting) {
            $setting = CompanyMailSetting::query()->create([
                'company_id' => $company->id,
                ...$data,
            ]);
        } else {
            $setting->fill($data)->save();
        }

        $this->audit->log(
            'mail',
            'update',
            'company_mail_setting',
            "Configuração de e-mail atualizada para a empresa {$company->name}",
        );

        return $setting->refresh();
    }

    public function validateCredentials(CompanyMailSetting $setting): void
    {
        try {
            $this->resend->validate($this->apiKey($setting));
        } catch (\Throwable $e) {
            $this->recordError($setting, $e);

            throw $e;
        }

        $setting->forceFill(['last_error' => null])->save();
    }

    public function registerDomain(CompanyMailSetting $setting): CompanyMailSetting
    {
        $apiKey = $this->apiKey($setting);
        $domain = $this->emailDomain($setting);

        try {
            $existing = $this->resend->findDomain($apiKey, $domain);
            $data = $existing
                ? $this->resend->getDomain($apiKey, (string) $existing['id'])
                : $this->resend->createDomain($apiKey, $domain, (string) $setting->region);

            $setting->forceFill([
                'domain_id' => (string) ($data['id'] ?? null),
                'domain_status' => (string) ($data['status'] ?? 'not_started'),
                'dns_records' => $data['records'] ?? null,
                'last_checked_at' => now(),
                'verified_at' => ($data['status'] ?? null) === 'verified' ? now() : null,
                'last_error' => null,
            ])->save();
        } catch (\Throwable $e) {
            $this->recordError($setting, $e);

            throw $e;
        }

        $this->audit->log(
            'mail',
            'register_domain',
            'company_mail_setting',
            "Domínio {$domain} registrado no Resend",
        );

        return $setting->refresh();
    }

    public function checkDomain(CompanyMailSetting $setting): CompanyMailSetting
    {
        $apiKey = $this->apiKey($setting);
        $domainId = $this->domainId($setting);

        try {
            $this->resend->verifyDomain($apiKey, $domainId);
            $data = $this->resend->getDomain($apiKey, $domainId);

            $setting->forceFill([
                'domain_status' => (string) ($data['status'] ?? 'pending'),
                'dns_records' => $data['records'] ?? $setting->dns_records,
                'last_checked_at' => now(),
                'verified_at' => ($data['status'] ?? null) === 'verified' ? now() : null,
                'last_error' => null,
            ])->save();
        } catch (\Throwable $e) {
            $this->recordError($setting, $e);

            throw $e;
        }

        $this->audit->log(
            'mail',
            'check_domain',
            'company_mail_setting',
            "Domínio {$setting->email_domain} verificado no Resend",
        );

        return $setting->refresh();
    }

    public function sendTest(CompanyMailSetting $setting, string $to): mixed
    {
        $setting->loadMissing('company');

        try {
            $sent = $this->mail->send(
                $setting->company,
                $to,
                new TransactionalMail(
                    mailSubject: 'Teste de e-mail - '.$setting->company->name,
                    mailTitle: 'Teste de e-mail',
                    mailBody: 'Se você recebeu esta mensagem, a conta Resend da empresa está configurada corretamente.',
                    companyName: $setting->company->name,
                ),
            );

            $setting->forceFill([
                'last_tested_at' => now(),
                'last_error' => null,
            ])->save();

            return $sent;
        } catch (\Throwable $e) {
            $this->recordError($setting, $e);

            throw $e;
        }
    }

    private function apiKey(CompanyMailSetting $setting): string
    {
        $key = $setting->apiKey();

        if ($key === null) {
            throw new ApiException(
                'Informe a API key do Resend antes de continuar.',
                422,
                'resend_credentials_missing',
            );
        }

        return $key;
    }

    private function emailDomain(CompanyMailSetting $setting): string
    {
        $domain = Company::normalizeDomain($setting->email_domain);

        if ($domain === null) {
            throw new ApiException(
                'Informe o domínio de envio antes de registrar no Resend.',
                422,
                'mail_domain_missing',
            );
        }

        return $domain;
    }

    private function domainId(CompanyMailSetting $setting): string
    {
        $id = trim((string) $setting->domain_id);

        if ($id === '') {
            throw new ApiException(
                'Registre o domínio no Resend antes de verificar.',
                422,
                'resend_domain_missing',
            );
        }

        return $id;
    }

    private function domainFromEmail(string $email): ?string
    {
        $domain = strrchr($email, '@');

        return $domain === false ? null : Company::normalizeDomain(substr($domain, 1));
    }

    private function assertSenderBelongsToDomain(string $email, string $domain): void
    {
        $email = strtolower($email);
        $domain = strtolower((string) Company::normalizeDomain($domain));

        if (! str_ends_with($email, '@'.$domain) && ! str_ends_with($email, '.'.$domain)) {
            throw new ApiException(
                'O e-mail remetente precisa pertencer ao domínio de envio informado.',
                422,
                'mail_sender_domain_mismatch',
            );
        }
    }

    private function recordError(CompanyMailSetting $setting, \Throwable $e): void
    {
        $setting->forceFill([
            'last_error' => mb_substr($e->getMessage(), 0, 1000),
        ])->save();
    }
}
