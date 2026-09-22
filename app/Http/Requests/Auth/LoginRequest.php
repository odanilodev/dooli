<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required_without:username', 'string', 'max:255'],
            'username' => ['required_without:email', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
