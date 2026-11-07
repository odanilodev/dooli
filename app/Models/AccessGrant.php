<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\GeneralStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'user_id', 'team_id', 'source_type', 'source_id', 'drip_product_id',
    'starts_at', 'expires_at', 'status',
])]
class AccessGrant extends Model
{
    use BelongsToCompany, HasFactory;

    protected $attributes = ['status' => GeneralStatus::Active->value];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isActive(): bool
    {
        if ($this->status !== GeneralStatus::Active->value) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
