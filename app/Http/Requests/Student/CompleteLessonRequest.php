<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;

class CompleteLessonRequest extends FormRequest
{
    use ScopedExists;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lesson_id' => ['required', 'integer', $this->companyExists('lessons')],
            'course_id' => ['required', 'integer', $this->companyExists('courses')],
            'team_id' => ['required', 'integer', $this->companyExists('teams')],
            'module_id' => ['required', 'integer', $this->companyExists('modules')],
        ];
    }
}
