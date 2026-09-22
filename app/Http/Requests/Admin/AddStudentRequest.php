<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;

class AddStudentRequest extends FormRequest
{
    use ScopedExists;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', $this->companyExists('users')],
            'expired_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
