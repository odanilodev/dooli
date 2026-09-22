<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use App\Http\Requests\Concerns\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTopicRequest extends FormRequest
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
            'module_id' => ['required', 'integer', $this->companyExists('modules')],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
