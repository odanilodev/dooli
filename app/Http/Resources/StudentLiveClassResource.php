<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentLiveClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'link' => $this->link,
            'image' => $this->image,
            'image_url' => $this->imageUrl(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'duration' => $this->duration,
        ];
    }
}
