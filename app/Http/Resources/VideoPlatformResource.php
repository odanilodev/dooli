<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoPlatformResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'status' => $this->status,
            // Credentials are never exposed; only whether they are configured.
            'has_credentials' => ! empty($this->credentials),
            'settings' => $this->settings,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
