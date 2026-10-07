<?php

namespace App\Http\Requests\Admin;

use App\Enums\ScheduleLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schedulable_type' => ['required', Rule::in(ScheduleLevel::values())],
            'schedulable_id' => ['required', 'integer'],
            'available_at' => ['nullable', 'date'],
        ];
    }
}
