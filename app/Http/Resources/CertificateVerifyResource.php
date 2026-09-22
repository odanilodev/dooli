<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public payload for certificate verification. Intentionally limited to the
 * data needed to validate a certificate, without any contact/PII details.
 */
class CertificateVerifyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'valid' => $this->invalidated_at === null,
            'holder_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'course' => $this->whenLoaded('certificate', fn () => $this->certificate->course?->name),
            'lessons_count' => $this->lessons_count,
            'issued_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
