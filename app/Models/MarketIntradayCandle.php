<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['market_asset_id', 'ts', 'open', 'high', 'low', 'close', 'volume'])]
class MarketIntradayCandle extends Model
{
    protected function casts(): array
    {
        return ['ts' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MarketAsset::class, 'market_asset_id');
    }
}
