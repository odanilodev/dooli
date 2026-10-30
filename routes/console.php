<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Coleta diária de dados de mercado para os robôs (horários de Brasília).
*/
Schedule::command('market:collect crypto')->twiceDaily(6, 18);
Schedule::command('market:collect b3')->weekdays()->at('19:30');
Schedule::command('market:collect us')->weekdays()->at('21:30');

/*
| Candles de 15min guardados no banco ao longo do dia (incremental: cada execução
| continua do último candle salvo). A última rodada de cada mercado fecha o dia.
*/
Schedule::command('market:collect-intraday b3')->weekdays()->at('10:30');
Schedule::command('market:collect-intraday b3')->weekdays()->at('12:30');
Schedule::command('market:collect-intraday b3')->weekdays()->at('14:30');
Schedule::command('market:collect-intraday b3')->weekdays()->at('16:30');
Schedule::command('market:collect-intraday b3')->weekdays()->at('17:45');
Schedule::command('market:collect-intraday us')->weekdays()->at('12:00');
Schedule::command('market:collect-intraday us')->weekdays()->at('14:30');
Schedule::command('market:collect-intraday us')->weekdays()->at('16:30');
Schedule::command('market:collect-intraday us')->weekdays()->at('18:00');
Schedule::command('market:collect-intraday crypto')->everyFourHours();
