<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRegistrationSource;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'email' => is_string($this->email) ? Str::lower(trim($this->email)) : $this->email,
            'referral_code' => is_string($this->referral_code) ? Str::lower(trim($this->referral_code)) : $this->referral_code,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                // Cadastro da loja que nunca confirmou o e-mail não conta como "já existe":
                // o AuthService reaproveita essa conta e reenvia o código.
                Rule::unique('users', 'email')
                    ->where('company_id', app(CompanyContext::class)->id())
                    ->where(fn ($query) => $query
                        ->whereNotNull('email_verified_at')
                        ->orWhereNull('registration_source')
                        ->orWhere('registration_source', '!=', UserRegistrationSource::Store->value)),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['required', 'accepted'],
            'referral_code' => ['nullable', 'string', 'max:32'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
