<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'image' => $this->image,
            'image_url' => $this->imageUrl(),
            'time' => $this->time,
            'status' => $this->status,
            'provider_status' => $this->provider_status,
            'player' => $this->player,
            'code' => $this->code,
            'link' => $this->link,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
