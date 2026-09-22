<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'grade' => $this->grade,
            'version' => $this->version,
            'time' => $this->time,
            'course_id' => $this->course_id,
            'course' => new CourseResource($this->whenLoaded('course')),
        ];
    }
}
