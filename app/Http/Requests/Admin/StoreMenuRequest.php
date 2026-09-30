<?php

namespace App\Http\Requests\Admin;

use App\Enums\MenuType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(MenuType::values())],
            'sub' => ['nullable', 'integer', 'exists:menus,id'],
            'link' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'icon' => ['nullable', 'string', 'max:255'],
        ];
    }
}
