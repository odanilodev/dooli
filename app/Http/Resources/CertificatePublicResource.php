<?php

namespace App\Http\Resources;

use App\Support\CertificateShare;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public payload used by the share page. Intentionally limited to presentation
 * data and the holder name, without contact details or internal identifiers.
 */
class CertificatePublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'valid' => true,
            'holder_name' => $this->user?->name,
            'course' => $this->certificate?->course?->name,
            'lessons_count' => $this->lessons_count,
            'issued_at' => $this->created_at?->toIso8601String(),
            'modules' => $this->modules ?? [],
            'certificate' => [
                'title' => $this->certificate?->title,
                'body' => $this->certificate?->body,
                'time' => $this->certificate?->time,
                'background_url' => $this->certificate?->backgroundUrl(),
                'signature_url' => $this->certificate?->signatureUrl(),
                'signatory_name' => $this->certificate?->signatory_name,
                'signatory_title' => $this->certificate?->signatory_title,
                'layout' => $this->certificate?->layout,
                'modules_placement' => $this->certificate?->modules_placement ?: 'front',
                'modules_title' => $this->certificate?->modules_title,
                'modules_layout' => $this->certificate?->modules_layout,
                'modules_background_url' => $this->certificate?->modulesBackgroundUrl(),
                'qr_enabled' => (bool) $this->certificate?->qr_enabled,
                'qr_placement' => $this->certificate?->qr_placement ?: 'front',
            ],
            'share_url' => CertificateShare::url((string) $this->code),
        ];
    }
}
