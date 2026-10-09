<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use App\Http\Requests\Concerns\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLiveClassRequest extends FormRequest
{
    use ScopedExists;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'link' => ['required', 'string', 'max:2048'],
            'starts_at' => ['required', 'date'],
            'duration' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'team_ids' => ['sometimes', 'array'],
            'team_ids.*' => ['integer', $this->companyExists('teams')],
        ];
    }
}
