<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentCourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $progress = $this->progress;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'image_url' => $this->imageUrl(),
            'status' => $this->status,
            'total_lessons' => $progress['total_lessons'] ?? 0,
            'completed_lessons' => $progress['completed_lessons'] ?? 0,
            'percentage' => $progress['percentage'] ?? 0,
            'completed' => (bool) ($progress['completed'] ?? false),
            'completed_at' => $progress['completed_at'] ?? null,
            'required_lessons' => $progress['required_lessons'] ?? 0,
            'new_lessons_since_completion' => $progress['new_lessons_since_completion'] ?? 0,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
