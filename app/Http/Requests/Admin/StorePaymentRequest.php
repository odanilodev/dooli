<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', Rule::exists('orders', 'id')],
            'gateway_id' => ['required', 'integer', Rule::exists('gateways', 'id')],
            'method' => ['required', 'string', 'max:30'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
