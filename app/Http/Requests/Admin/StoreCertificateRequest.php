<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesCertificateLayout;
use App\Http\Requests\Concerns\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreCertificateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'course_id' => ['required', 'integer', $this->companyExists('courses')],
            'version' => ['nullable', 'string', 'max:50'],
            'copy_from' => ['nullable', 'integer', $this->companyExists('certificates')],
            'time' => ['sometimes', 'integer', 'min:0'],
            'background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_background' => ['sometimes', 'boolean'],
            'remove_signature' => ['sometimes', 'boolean'],
            'body' => ['nullable', 'string', 'max:5000'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
            'modules_placement' => ['sometimes', 'string', 'in:none,front,extra,back'],
            'modules_title' => ['nullable', 'string', 'max:255'],
            'modules_background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_modules_background' => ['sometimes', 'boolean'],
            'qr_enabled' => ['sometimes', 'boolean'],
            'qr_placement' => ['sometimes', 'string', 'in:front,modules,both'],
            ...$this->layoutRules(),
            ...$this->layoutRules('modules_layout'),
        ];
    }
}
