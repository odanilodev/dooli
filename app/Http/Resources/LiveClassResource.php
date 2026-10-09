<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiveClassResource extends JsonResource
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
            'status' => $this->status,
            'teams_count' => $this->whenCounted('teams'),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
