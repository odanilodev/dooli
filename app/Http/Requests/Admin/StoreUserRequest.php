<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use App\Enums\UserTypeCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', Password::defaults()],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
            'user_type_id' => ['required', 'integer', $this->userTypeRule()],
            'image' => ['nullable', 'string', 'max:255'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Operators (non-admins with the Users menu) can only assign the student type.
     */
    private function userTypeRule(): \Illuminate\Validation\Rules\Exists
    {
        $rule = Rule::exists('user_types', 'id');
        $user = $this->user();

        if ($user && ! $user->isAdmin() && ! $user->isSuperAdmin()) {
            $rule->where('code', UserTypeCode::Student->value)->where('company_id', $user->company_id);
        }

        return $rule;
    }
}
