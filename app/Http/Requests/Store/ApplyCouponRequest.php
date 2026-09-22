<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.store_product_id' => ['required', 'integer', Rule::exists('store_products', 'id')],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
