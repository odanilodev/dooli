<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasImageUrl;
use App\Enums\BillingType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'course_id', 'company_id', 'title', 'image', 'description',
    'slug', 'tags', 'price', 'sale', 'status', 'release_at',
    'billing_type', 'billing_interval', 'access_duration_days', 'grace_days',
])]
class StoreProduct extends Model
{
    use BelongsToCompany, HasFactory, HasImageUrl;

    protected $attributes = [
        'status' => 'active',
        'billing_type' => BillingType::OneTime->value,
        'grace_days' => 3,
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'price' => 'decimal:2',
            'sale' => 'decimal:2',
            'release_at' => 'datetime',
            'access_duration_days' => 'integer',
            'grace_days' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'store_product_teams')
            ->withPivot(['status'])
            ->withTimestamps();
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'coupon_store_products')
            ->withTimestamps();
    }

    public function effectivePrice(): float
    {
        return (float) ($this->sale > 0 ? $this->sale : $this->price);
    }

    public function isSubscription(): bool
    {
        return $this->billing_type === BillingType::Subscription->value;
    }

    public function accessExpiresAt(Carbon $startsAt): ?Carbon
    {
        if ($this->access_duration_days === null) {
            return null;
        }

        return $startsAt->copy()->addDays($this->access_duration_days);
    }

    /**
     * Products that are already released (release_at in the past or unset).
     */
    public function scopeReleased(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('release_at')->orWhere('release_at', '<=', now());
        });
    }
}
