<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gateway_id' => [
                'required', 'integer',
                Rule::exists('gateways', 'id')
                    ->where('company_id', (int) $this->user()?->company_id)
                    ->where('status', 'active'),
            ],
            'method' => ['required', Rule::in(['pix', 'credit_card'])],
        ];
    }
}
