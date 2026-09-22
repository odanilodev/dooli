<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'release_at' => $this->release_at?->toIso8601String(),
            'type' => $this->whenLoaded('type', fn () => [
                'id' => $this->type->id,
                'slug' => $this->type->slug,
                'name' => $this->type->name,
            ]),
            'read_at' => $this->whenPivotLoaded('notification_users', fn () => $this->pivot->read_at),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
