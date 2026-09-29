<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VideoUploadCredentialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'folder_id' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
        ];
    }
}
