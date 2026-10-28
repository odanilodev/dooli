<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentHomeTeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'course_ids' => $this->course_ids ?? [],
            'courses' => StudentCourseResource::collection($this->homeCourses),
            'continue_watching' => $this->continue_watching ?? [],
        ];
    }
}
