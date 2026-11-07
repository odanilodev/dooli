<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dias de espera de um módulo, contados a partir da liberação do módulo anterior
 * (liberação gradual do produto).
 */
#[Fillable(['company_id', 'store_product_id', 'module_id', 'interval_days'])]
class StoreProductModuleDrip extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return ['interval_days' => 'integer'];
    }

    public function storeProduct(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
