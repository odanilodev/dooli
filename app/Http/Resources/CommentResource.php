<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'lesson_id' => $this->lesson_id,
            'module_id' => $this->module_id,
            'course_id' => $this->course_id,
            'team_id' => $this->team_id,
            'parent_id' => $this->parent_id,
            'image' => $this->image,
            'is_admin' => (bool) $this->user?->isAdmin(),
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'course' => $this->whenLoaded('course', fn () => [
                'id' => $this->course->id,
                'name' => $this->course->name,
            ]),
            'module' => $this->whenLoaded('module', fn () => [
                'id' => $this->module->id,
                'name' => $this->module->name,
            ]),
            'lesson' => $this->whenLoaded('lesson', fn () => [
                'id' => $this->lesson->id,
                'name' => $this->lesson->name,
                'comments_enabled' => (bool) $this->lesson->comments_enabled,
                'student_replies_enabled' => (bool) $this->lesson->student_replies_enabled,
            ]),
            'replies' => self::collection($this->whenLoaded('replies')),
            'replies_count' => $this->whenCounted('replies'),
            'awaiting_reply' => $this->whenCounted('admin_replies', fn ($count) => (int) $count === 0),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
