<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\AccessGrantSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['company_id', 'user_id', 'coupon_id', 'subscription_id', 'status', 'source', 'subtotal', 'discount', 'total', 'period_start', 'period_end'])]
class Order extends Model
{
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(AccessGrant::class, 'source_id')
            ->where('source_type', AccessGrantSource::Order->value);
    }
}
