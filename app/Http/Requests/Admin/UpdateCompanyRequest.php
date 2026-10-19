<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'string', 'max:255', 'alpha_dash',
                Rule::unique('companies', 'slug')->ignore($this->route('company')),
            ],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
        ];
    }
}
