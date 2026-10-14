<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
            'module_ids' => ['required', 'array', 'min:1'],
            'module_ids.*' => ['integer', 'distinct'],
        ];
    }
}
