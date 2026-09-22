<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image,
            'slug' => $this->slug,
            'tags' => $this->tags,
            'price' => $this->price,
            'sale' => $this->sale,
            'status' => $this->status,
            'release_at' => $this->release_at?->toIso8601String(),
            'course' => new CourseResource($this->whenLoaded('course')),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
