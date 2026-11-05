<?php

namespace App\Services\MarketData;

use App\Exceptions\MarketDataException;
use App\Models\MarketAsset;
use App\Services\Robots\DeltaScreenerRobot;
use Illuminate\Support\Facades\Cache;

/**
 * Cadastra um ativo digitado pelo aluno: só entra na base se a fonte de dados
 * reconhecer o símbolo. Já baixa o histórico diário e o intraday; a partir daí
 * as coletas agendadas passam a incluí-lo (ver MarketAsset::symbolsFor).
 */
class MarketAssetRegistrar
{
    public function __construct(
        private readonly MarketDataCollector $daily,
        private readonly IntradayCollector $intraday,
    ) {}

    /** @throws MarketDataException quando o símbolo não existe na fonte */
    public function register(string $market, string $symbol): MarketAsset
    {
        $existed = MarketAsset::query()->where(['market' => $market, 'symbol' => $symbol])->exists();

        $result = $this->daily->collectSymbol(app($this->daily->providerFor($market)), $market, $symbol);
        $asset = MarketAsset::query()->where(['market' => $market, 'symbol' => $symbol])->first();
        $hasData = $asset && $asset->candles()->exists();

        if (! $hasData) {
            if (! $existed) {
                $asset?->delete();
            }

            throw new MarketDataException($result['message'] ?? "{$symbol}: nenhum dado encontrado para este ativo.");
        }

        // Intraday é complemento: se a fonte falhar, o ativo continua válido pelo diário.
        $this->intraday->collectSymbol($this->intraday->provider($market), $market, $symbol);

        Cache::forget(DeltaScreenerRobot::OPTIONS_CACHE_KEY);

        return $asset;
    }
}
