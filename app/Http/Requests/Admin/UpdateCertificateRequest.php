<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesCertificateLayout;
use App\Http\Requests\Concerns\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCertificateRequest extends FormRequest
{
    use ScopedExists, ValidatesCertificateLayout;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareLayoutForValidation();
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
            'background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'body' => ['nullable', 'string', 'max:5000'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
            ...$this->layoutRules(),
        ];
    }
}
