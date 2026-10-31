<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'company_id', 'provider', 'status', 'from_name', 'from_address', 'reply_to',
    'email_domain', 'region', 'domain_id', 'domain_status', 'dns_records',
    'last_checked_at', 'last_tested_at', 'verified_at', 'last_error',
    'credentials', 'settings',
])]
#[Hidden(['credentials'])]
class CompanyMailSetting extends Model
{
    use BelongsToCompany, HasFactory;

    protected $attributes = [
        'provider' => 'resend',
        'status' => 'active',
        'region' => 'sa-east-1',
        'domain_status' => 'not_started',
    ];

    protected function casts(): array
    {
        return [
            'dns_records' => 'array',
            'settings' => 'array',
            'credentials' => 'encrypted:array',
            'last_checked_at' => 'datetime',
            'last_tested_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function apiKey(): ?string
    {
        $key = $this->credentials['api_key'] ?? null;

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    public function hasCredentials(): bool
    {
        return $this->apiKey() !== null;
    }

    public function isReady(): bool
    {
        return $this->status === 'active'
            && $this->hasCredentials()
            && filled($this->from_address)
            && filled($this->email_domain)
            && $this->domain_status === 'verified';
    }
}
