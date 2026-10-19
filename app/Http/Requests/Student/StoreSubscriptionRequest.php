<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_product_id' => [
                'required', 'integer',
                Rule::exists('store_products', 'id')
                    ->where('company_id', (int) $this->user()?->company_id)
                    ->where('status', 'active'),
            ],
            'gateway_id' => [
                'required', 'integer',
                Rule::exists('gateways', 'id')
                    ->where('company_id', (int) $this->user()?->company_id)
                    ->where('status', 'active'),
            ],
        ];
    }
}
