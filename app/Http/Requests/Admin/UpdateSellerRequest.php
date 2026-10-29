<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use App\Http\Requests\Concerns\ScopedExists;
use App\Models\Seller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateSellerRequest extends FormRequest
{
    use ScopedExists;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'email' => is_string($this->email) ? Str::lower(trim($this->email)) : $this->email,
            'code' => is_string($this->code) ? Str::lower(trim($this->code)) : $this->code,
        ]);
    }

    public function rules(): array
    {
        $companyId = (int) $this->user()?->company_id;
        $seller = $this->route('seller');
        $sellerId = $seller instanceof Seller ? $seller->id : (int) $seller;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'user_id' => [
                'nullable', 'integer',
                $this->companyExists('users'),
                Rule::unique('sellers', 'user_id')
                    ->where('company_id', $companyId)
                    ->ignore($sellerId),
            ],
            'code' => [
                'nullable', 'string', 'max:32', 'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique('sellers', 'code')
                    ->where('company_id', $companyId)
                    ->ignore($sellerId),
            ],
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
