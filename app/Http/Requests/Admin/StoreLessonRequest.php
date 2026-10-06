<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use App\Http\Requests\Concerns\ScopedExists;
use App\Models\Topic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLessonRequest extends FormRequest
{
    use ScopedExists;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('topic_id') && ! $this->filled('module_id')) {
            $moduleId = Topic::query()->whereKey($this->input('topic_id'))->value('module_id');

            if ($moduleId !== null) {
                $this->merge(['module_id' => $moduleId]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'module_id' => ['nullable', 'required_without:topic_id', 'integer', $this->companyExists('modules')],
            'topic_id' => ['nullable', 'integer', $this->companyExists('topics'), $this->topicBelongsToModule()],
            'description' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
            'video_id' => ['nullable', 'integer', $this->companyExists('videos')],
            'document_id' => ['nullable', 'integer', $this->companyExists('documents')],
            'required_lesson_id' => ['nullable', 'integer', $this->companyExists('lessons')],
            'require_full_watch' => ['sometimes', 'boolean'],
            'webinar_link' => ['nullable', 'url', 'max:255'],
            'webinar_starts_at' => ['nullable', 'date'],
            'published_at' => ['nullable', 'date'],
            'attachment_ids' => ['sometimes', 'array'],
            'attachment_ids.*' => ['integer', $this->companyExists('attachments')],
        ];
    }

    private function topicBelongsToModule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null) {
                return;
            }

            $moduleId = (int) $this->input('module_id');
            $topic = Topic::query()->whereKey($value)->first();

            if (! $topic || (int) $topic->module_id !== $moduleId) {
                $fail('O tópico informado não pertence ao módulo.');
            }
        };
    }
}
