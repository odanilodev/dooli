<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['add-to-teams', 'activate', 'deactivate', 'delete'])],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct'],
            'team_ids' => ['required_if:action,add-to-teams', 'array', 'min:1'],
            'team_ids.*' => ['integer', 'distinct'],
            'expired_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
