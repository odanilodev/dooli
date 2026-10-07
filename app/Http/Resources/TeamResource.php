<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'users_count' => $this->whenCounted('users'),
            'courses_count' => $this->whenCounted('courses'),
            'schedules_count' => $this->whenCounted('schedules'),
            'courses' => CourseResource::collection($this->whenLoaded('courses')),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
