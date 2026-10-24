<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('domain')) {
            $domain = $this->input('domain');

            $this->merge([
                'domain' => is_string($domain) ? Company::normalizeDomain($domain) : $domain,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('companies', 'slug')],
            'domain' => [
                'sometimes', 'nullable', 'string', 'max:253',
                'regex:/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i',
                Rule::unique('companies', 'domain'),
            ],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_username' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', Password::defaults()],
        ];
    }
}
