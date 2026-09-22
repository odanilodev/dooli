<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use App\Http\Requests\Concerns\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLessonRequest extends FormRequest
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
            'topic_id' => ['required', 'integer', $this->companyExists('topics')],
            'description' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
            'video_id' => ['nullable', 'integer', $this->companyExists('videos')],
            'document_id' => ['nullable', 'integer', $this->companyExists('documents')],
            'webinar_link' => ['nullable', 'url', 'max:255'],
            'published_at' => ['nullable', 'date'],
            'attachment_ids' => ['sometimes', 'array'],
            'attachment_ids.*' => ['integer', $this->companyExists('attachments')],
        ];
    }
}
