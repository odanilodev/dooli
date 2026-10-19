<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    use ScopedExists;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', $this->companyExists('comments')],
            'lesson_id' => ['required_without:parent_id', 'integer', $this->companyExists('lessons')],
            'module_id' => ['required_without:parent_id', 'integer', $this->companyExists('modules')],
            'course_id' => ['required_without:parent_id', 'integer', $this->companyExists('courses')],
            'team_id' => ['required_without:parent_id', 'integer', $this->companyExists('teams')],
            'image' => ['nullable', 'url', 'max:255'],
        ];
    }
}
