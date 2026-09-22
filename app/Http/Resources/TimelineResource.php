<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'module_id' => $this->module_id,
            'lesson_id' => $this->lesson_id,
            'team_id' => $this->team_id,
            'action' => $this->whenLoaded('action', fn () => [
                'id' => $this->action->id,
                'code' => $this->action->code,
                'name' => $this->action->name,
            ]),
            'lesson' => $this->whenLoaded('lesson', fn () => [
                'id' => $this->lesson->id,
                'name' => $this->lesson->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
