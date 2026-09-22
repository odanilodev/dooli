<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use App\Enums\UserTypeCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userTypeId = $this->route('user_type')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::in(UserTypeCode::values()),
                Rule::unique('user_types', 'code')
                    ->ignore($userTypeId)
                    ->where('company_id', (int) $this->user()?->company_id),
            ],
            'permission_menu' => ['nullable', 'array'],
            'permission_menu.*' => ['integer'],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
        ];
    }
}
