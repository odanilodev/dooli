<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('companies', 'slug')],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_username' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', Password::defaults()],
        ];
    }
}
