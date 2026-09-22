<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use App\Enums\VideoPlayer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'string', 'max:255'],
            'time' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
            'player' => ['nullable', Rule::in(VideoPlayer::values())],
            'link' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
        ];
    }
}
