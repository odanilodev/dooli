<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'content' => $this->content,
            'module_id' => $this->module_id,
            'topic_id' => $this->topic_id,
            'status' => $this->status,
            'content_type' => $this->content_type?->value,
            'sort_order' => $this->sort_order,
            'required_lesson_id' => $this->required_lesson_id,
            'require_full_watch' => (bool) $this->require_full_watch,
            'comments_enabled' => (bool) $this->comments_enabled,
            'student_replies_enabled' => (bool) $this->student_replies_enabled,
            'published_at' => $this->published_at?->toIso8601String(),
            'webinar_link' => $this->webinar_link,
            'webinar_starts_at' => $this->webinar_starts_at?->toIso8601String(),
            'available_at' => $this->when(
                ($this->schedule_available_at ?? null) !== null,
                fn () => $this->schedule_available_at?->toIso8601String(),
            ),
            'is_locked' => $this->when(
                ($this->schedule_available_at ?? null) !== null,
                fn () => (bool) $this->schedule_locked,
            ),
            'video' => new VideoResource($this->whenLoaded('video')),
            'document' => new DocumentResource($this->whenLoaded('document')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
