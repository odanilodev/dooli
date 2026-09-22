<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreCertificateRequest extends FormRequest
{
    use ScopedExists;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'course_id' => ['required', 'integer', $this->companyExists('courses')],
            'grade' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'version' => ['nullable', 'string', 'max:50'],
            'time' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
