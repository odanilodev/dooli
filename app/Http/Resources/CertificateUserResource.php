<?php

namespace App\Http\Resources;

use App\Support\CertificateShare;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->public_code ?: $this->code,
            'share_url' => CertificateShare::url(
                (string) ($this->public_code ?: $this->code),
                $this->company,
            ),
            'user_id' => $this->user_id,
            'team_id' => $this->team_id,
            'lessons_count' => $this->lessons_count,
            'modules' => $this->modules ?? [],
            'certificate' => new CertificateResource($this->whenLoaded('certificate')),
            'user' => new UserResource($this->whenLoaded('user')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'status' => $this->status(),
            'invalidated_at' => $this->invalidated_at?->toIso8601String(),
            'invalidation_reason' => $this->invalidation_reason,
            'issued_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
