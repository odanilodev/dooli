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
            'title' => $this->title ?? $this->type?->name,
            'message' => $this->message,
            'action_url' => $this->action_url,
            'category' => $this->category,
            'severity' => $this->severity ?? 'info',
            'status' => $this->status,
            'release_at' => $this->release_at?->toIso8601String(),
            'type' => $this->whenLoaded('type', fn () => [
                'id' => $this->type->id,
                'slug' => $this->type->slug,
                'name' => $this->type->name,
            ]),
            'read_at' => $this->whenLoaded('users', fn () => $this->users->firstWhere('id', $request->user()?->id)?->pivot?->read_at),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
