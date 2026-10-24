<?php

namespace App\Http\Requests\Admin;

use App\Enums\BannerPlacement;
use App\Enums\GeneralStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'placement' => ['sometimes', Rule::in(BannerPlacement::values())],
            'name' => ['sometimes', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'link' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'title_highlight' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'quote' => ['nullable', 'string', 'max:255'],
            'cta_label' => ['nullable', 'string', 'max:255'],
            'cta_link' => ['nullable', 'string', 'max:255'],
            'secondary_label' => ['nullable', 'string', 'max:255'],
            'secondary_link' => ['nullable', 'string', 'max:255'],
        ];
    }
}
