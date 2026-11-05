<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['market', 'symbol', 'name'])]
class MarketAsset extends Model
{
    public function candles(): HasMany
    {
        return $this->hasMany(MarketCandle::class);
    }

    /**
     * Ativos acompanhados no mercado: a lista base de config/market.php mais
     * os que os alunos adicionaram pela tela do robô.
     *
     * @return array<int, string>
     */
    public static function symbolsFor(string $market): array
    {
        return collect((array) config("market.markets.{$market}.symbols"))
            ->merge(static::query()->where('market', $market)->pluck('symbol'))
            ->unique()
            ->values()
            ->all();
    }
}
