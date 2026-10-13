<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use App\Http\Requests\Concerns\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportProgressRequest extends FormRequest
{
    use ScopedExists;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_by' => ['nullable', Rule::in(['team', 'course', 'module', 'lesson', 'student'])],
            'team_id' => ['nullable', 'integer', $this->companyExists('teams')],
            'course_id' => ['nullable', 'integer', $this->companyExists('courses')],
            'module_id' => ['nullable', 'integer', $this->companyExists('modules')],
            'lesson_id' => ['nullable', 'integer', $this->companyExists('lessons')],
            'user_id' => ['nullable', 'integer', $this->companyExists('users')],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', $this->companyExists('users')],
            'student_status' => ['nullable', Rule::in(GeneralStatus::values())],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $data = $this->validated();

        return [
            'group_by' => $data['group_by'] ?? 'team',
            'team_id' => isset($data['team_id']) ? (int) $data['team_id'] : null,
            'course_id' => isset($data['course_id']) ? (int) $data['course_id'] : null,
            'module_id' => isset($data['module_id']) ? (int) $data['module_id'] : null,
            'lesson_id' => isset($data['lesson_id']) ? (int) $data['lesson_id'] : null,
            'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'userIds' => array_values(array_map('intval', $data['user_ids'] ?? [])),
            'student_status' => $data['student_status'] ?? null,
            'search' => $data['search'] ?? null,
            'limit' => (int) ($data['limit'] ?? 50),
            'per_page' => (int) ($data['per_page'] ?? 20),
        ];
    }
}
