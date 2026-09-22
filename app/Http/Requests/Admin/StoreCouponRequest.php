<?php

namespace App\Http\Requests\Admin;

use App\Enums\CouponType;
use App\Enums\GeneralStatus;
use App\Http\Requests\Concerns\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    use ScopedExists;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:255',
                Rule::unique('coupons', 'code')->where('company_id', (int) $this->user()?->company_id),
            ],
            'type' => ['required', Rule::in(CouponType::values())],
            'value' => ['required', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'store_product_ids' => ['sometimes', 'array'],
            'store_product_ids.*' => ['integer', $this->companyExists('store_products')],
        ];
    }
}
