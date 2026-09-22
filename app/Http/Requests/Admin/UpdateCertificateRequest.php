<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCertificateRequest extends FormRequest
{
    use ScopedExists;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'title' => ['sometimes', 'string', 'max:255'],
            'course_id' => ['sometimes', 'integer', $this->companyExists('courses')],
            'grade' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'version' => ['nullable', 'string', 'max:50'],
            'time' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
