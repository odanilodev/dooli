<?php

namespace App\Http\Requests\Student;

use App\Rules\Cpf;
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

            // Checkout transparente (cartão): dados gerados pelo Card Payment Brick do Mercado Pago.
            'card_token' => ['nullable', 'string', 'max:255', 'required_with:card_payment_method_id'],
            'card_payment_method_id' => ['nullable', 'string', 'max:60'],
            'issuer_id' => ['nullable', 'string', 'max:60'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:24'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'payer' => ['nullable', 'array'],
            'payer.email' => ['nullable', 'email', 'max:255'],
            'payer.identification' => ['nullable', 'array'],
            'payer.identification.type' => ['nullable', 'string', 'max:20'],
            'payer.identification.number' => [
                'nullable', 'string', 'max:30',
                // Só valida quando o documento é CPF (o cartão também aceita CNPJ).
                Rule::when(
                    strtoupper((string) $this->input('payer.identification.type', 'CPF')) === 'CPF',
                    [new Cpf],
                ),
            ],
        ];
    }
}
