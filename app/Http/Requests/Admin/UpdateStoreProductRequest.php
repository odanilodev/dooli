<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use App\Http\Requests\Concerns\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoreProductRequest extends FormRequest
{
    use ScopedExists;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeProductId = $this->route('store_product')?->id;

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'slug' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('store_products', 'slug')
                    ->ignore($storeProductId)
                    ->where('company_id', (int) $this->user()?->company_id),
            ],
            'tags' => ['nullable', 'array'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'sale' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
            'release_at' => ['nullable', 'date'],
            'team_ids' => ['sometimes', 'array'],
            'team_ids.*' => ['integer', $this->companyExists('teams')],
        ];
    }
}
