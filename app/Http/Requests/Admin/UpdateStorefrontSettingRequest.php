<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStorefrontSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'catalog' => ['sometimes', 'array'],
            'catalog.eyebrow' => ['nullable', 'string', 'max:80'],
            'catalog.title' => ['nullable', 'string', 'max:120'],
            'catalog.description' => ['nullable', 'string', 'max:500'],

            'benefits' => ['sometimes', 'array', 'max:3'],
            'benefits.*.title' => ['nullable', 'string', 'max:80'],
            'benefits.*.description' => ['nullable', 'string', 'max:160'],

            'internal' => ['sometimes', 'array'],
            'internal.eyebrow' => ['nullable', 'string', 'max:80'],
            'internal.title' => ['nullable', 'string', 'max:120'],
            'internal.description' => ['nullable', 'string', 'max:500'],

            'footer' => ['sometimes', 'array'],
            'footer.note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
