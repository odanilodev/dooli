<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => [
                'required',
                'integer',
                Rule::exists('comments', 'id')
                    ->where('company_id', (int) $this->user()?->company_id),
            ],
            'content' => ['required', 'string', 'max:2000'],
        ];
    }
}
