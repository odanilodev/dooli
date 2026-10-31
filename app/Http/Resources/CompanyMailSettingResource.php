<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyMailSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'status' => $this->status,
            'from_name' => $this->from_name,
            'from_address' => $this->from_address,
            'reply_to' => $this->reply_to,
            'email_domain' => $this->email_domain,
            'region' => $this->region,
            'domain_id' => $this->domain_id,
            'domain_status' => $this->domain_status,
            'dns_records' => $this->dns_records,
            'has_credentials' => $this->hasCredentials(),
            'is_ready' => $this->isReady(),
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'last_tested_at' => $this->last_tested_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
