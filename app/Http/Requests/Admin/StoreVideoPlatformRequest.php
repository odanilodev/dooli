<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use App\Enums\VideoPlayer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoPlatformRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::in(VideoPlayer::values())],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
            'credentials' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
