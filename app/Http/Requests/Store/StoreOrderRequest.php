<?php

namespace App\Http\Requests\Store;

use App\Http\Requests\Concerns\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    use ScopedExists;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.store_product_id' => ['required', 'integer', $this->companyExists('store_products')],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'coupon_code' => ['nullable', 'string', 'max:255'],
            'referral_code' => ['nullable', 'string', 'max:32'],
        ];
    }
}
