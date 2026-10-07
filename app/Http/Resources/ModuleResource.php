<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'status' => $this->status,
            'sort_order' => $this->whenPivotLoaded('course_modules', fn () => $this->pivot->sort_order) ?? $this->sort_order,
            'available_at' => $this->when(
                ($this->schedule_available_at ?? null) !== null,
                fn () => $this->schedule_available_at?->toIso8601String(),
            ),
            'is_locked' => $this->when(
                ($this->schedule_available_at ?? null) !== null,
                fn () => (bool) $this->schedule_locked,
            ),
            'topics' => TopicResource::collection($this->whenLoaded('topics')),
            'lessons' => LessonResource::collection($this->whenLoaded('looseLessons')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
