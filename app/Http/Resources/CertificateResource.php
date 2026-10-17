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
            'background' => $this->background,
            'background_url' => $this->backgroundUrl(),
            'signature' => $this->signature,
            'signature_url' => $this->signatureUrl(),
            'body' => $this->body,
            'signatory_name' => $this->signatory_name,
            'signatory_title' => $this->signatory_title,
            'course' => new CourseResource($this->whenLoaded('course')),
        ];
    }
}
