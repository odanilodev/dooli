<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use App\Http\Requests\Concerns\ScopedExists;
use App\Models\Lesson;
use App\Models\Topic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLessonRequest extends FormRequest
{
    use ScopedExists;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'module_id' => ['sometimes', 'integer', $this->companyExists('modules')],
            'topic_id' => ['sometimes', 'nullable', 'integer', $this->companyExists('topics'), $this->topicBelongsToModule()],
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

    protected function prepareForValidation(): void
    {
        $lesson = $this->route('lesson');

        if (! $lesson instanceof Lesson || ! $this->filled('topic_id')) {
            return;
        }

        $moduleId = Topic::query()->whereKey($this->input('topic_id'))->value('module_id');

        if ($moduleId !== null) {
            $this->merge(['module_id' => $moduleId]);
        }
    }

    private function topicBelongsToModule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null) {
                return;
            }

            $lesson = $this->route('lesson');
            $moduleId = $this->input('module_id', $lesson instanceof Lesson ? $lesson->module_id : null);
            $topic = Topic::query()->whereKey($value)->first();

            if (! $topic || $moduleId === null || (int) $topic->module_id !== (int) $moduleId) {
                $fail('O tópico informado não pertence ao módulo.');
            }
        };
    }
}
