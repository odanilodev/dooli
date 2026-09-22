<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'coupon_id', 'store_product_id'])]
class CouponStoreProduct extends Model
{
    use BelongsToCompany, HasFactory;

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function storeProduct(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class);
    }
}
