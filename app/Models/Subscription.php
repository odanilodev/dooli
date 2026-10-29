<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'user_id', 'store_product_id', 'gateway_id',
    'seller_id', 'referral_code', 'commission_percent',
    'provider_subscription_id', 'status', 'amount',
    'current_period_start', 'current_period_end', 'grace_ends_at',
    'next_billing_at', 'canceled_at', 'metadata',
])]
class Subscription extends Model
{
    use BelongsToCompany, HasFactory;

    protected $attributes = ['status' => SubscriptionStatus::Pending->value];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'commission_percent' => 'decimal:2',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'grace_ends_at' => 'datetime',
            'next_billing_at' => 'datetime',
            'canceled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function storeProduct(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active->value
            && ($this->current_period_end === null || $this->current_period_end->isFuture());
    }
}
