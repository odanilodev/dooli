<?php

namespace App\Http\Requests\Admin;

use App\Enums\BillingInterval;
use App\Enums\BillingType;
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
            'billing_type' => ['sometimes', Rule::in(BillingType::values())],
            'billing_interval' => [
                'nullable', 'required_if:billing_type,'.BillingType::Subscription->value,
                Rule::in(BillingInterval::values()),
            ],
            'access_duration_days' => [
                'nullable', 'integer', 'min:1', 'max:3650',
                'prohibited_if:billing_type,'.BillingType::Subscription->value,
            ],
            'grace_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'drip_enabled' => ['sometimes', 'boolean'],
            'module_drips' => ['sometimes', 'array'],
            'module_drips.*.module_id' => ['required', 'integer'],
            'module_drips.*.interval_days' => ['required', 'integer', 'min:0', 'max:365'],
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => ['integer', $this->companyExists('teams')],
        ];
    }
}
