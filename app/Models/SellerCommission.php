<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\SellerCommissionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'seller_id', 'order_id', 'order_item_id', 'payment_id',
    'base_amount', 'commission_percent', 'amount', 'refunded_amount',
    'status', 'approved_at', 'paid_at', 'reversed_at',
    'payment_reference', 'payment_notes',
])]
class SellerCommission extends Model
{
    use BelongsToCompany, HasFactory;

    protected $attributes = [
        'status' => SellerCommissionStatus::Approved->value,
        'refunded_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'commission_percent' => 'decimal:2',
            'amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function netAmount(): float
    {
        return round(max((float) $this->amount - (float) $this->refunded_amount, 0), 2);
    }

    public function isPayable(): bool
    {
        return $this->status === SellerCommissionStatus::Approved->value
            && $this->netAmount() > 0;
    }
}
