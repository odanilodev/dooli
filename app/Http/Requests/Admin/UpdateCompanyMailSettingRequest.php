<?php

namespace App\Http\Requests\Admin;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyMailSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('email_domain')) {
            $domain = $this->input('email_domain');

            $this->merge([
                'email_domain' => is_string($domain) ? Company::normalizeDomain($domain) : $domain,
            ]);
        }

        foreach (['from_address', 'reply_to'] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $this->merge([$field => trim((string) $this->input($field))]);
            }
        }

        if (! $this->filled('email_domain') && $this->filled('from_address')) {
            $domain = strrchr((string) $this->input('from_address'), '@');

            if ($domain !== false) {
                $this->merge(['email_domain' => Company::normalizeDomain(substr($domain, 1))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'provider' => ['sometimes', Rule::in(['resend'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'email_domain' => [
                'nullable', 'string', 'max:253',
                'regex:/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i',
            ],
            'region' => ['sometimes', Rule::in(['us-east-1', 'eu-west-1', 'sa-east-1', 'ap-northeast-1'])],
            'credentials' => ['nullable', 'array'],
            'credentials.api_key' => ['nullable', 'string', 'max:500'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
