<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use App\Http\Requests\Concerns\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStoreProductRequest extends FormRequest
{
    use ScopedExists;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', $this->companyExists('courses')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('store_products', 'slug')->where('company_id', (int) $this->user()?->company_id),
            ],
            'tags' => ['nullable', 'array'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
            'release_at' => ['nullable', 'date'],
            'team_ids' => ['sometimes', 'array'],
            'team_ids.*' => ['integer', $this->companyExists('teams')],
        ];
    }
}
