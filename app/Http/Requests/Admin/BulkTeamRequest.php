<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['attach-courses', 'add-students', 'activate', 'deactivate', 'delete'])],
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => ['integer', 'distinct'],
            'course_ids' => ['required_if:action,attach-courses', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'distinct'],
            'user_ids' => ['required_if:action,add-students', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct'],
            'expired_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
