<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\GeneralStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'company_id', 'user_id', 'name', 'email', 'phone',
    'code', 'share_token', 'commission_percent', 'status', 'notes',
])]
class Seller extends Model
{
    use BelongsToCompany, HasFactory;

    public const KIT_TOKEN_LENGTH = 8;

    protected $hidden = ['share_token'];

    protected $attributes = [
        'status' => GeneralStatus::Active->value,
        'commission_percent' => 0,
    ];

    protected function casts(): array
    {
        return [
            'commission_percent' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(SellerCommission::class);
    }

    public function isActive(): bool
    {
        return $this->status === GeneralStatus::Active->value;
    }

    public function kitUrl(): string
    {
        return $this->company?->sellerKitUrl((string) $this->share_token) ?? '';
    }

    public static function normalizeCode(?string $value): ?string
    {
        $code = Str::lower(trim((string) $value));

        return $code === '' ? null : $code;
    }
}
