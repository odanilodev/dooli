<?php

/*
|--------------------------------------------------------------------------
| Dados de mercado (coleta para os robôs)
|--------------------------------------------------------------------------
|
| Lista de ativos acompanhados por mercado. Para acompanhar um ativo novo,
| basta incluir o símbolo aqui: a próxima coleta cria o ativo e baixa o
| histórico dos últimos `history_months` meses.
|
*/

return [

    'history_months' => 3,

    // Janela inicial dos candles de 15min (depois a coleta só continua do último salvo).
    'intraday_backfill_days' => 30,

    'markets' => [
        'b3' => [
            'label' => 'B3',
            'symbols' => [
                'AALR3', 'ABCB4', 'ABEV3', 'AERI3', 'AGRO3', 'ALOS3', 'ALPA4', 'ALUP11',
                'AMBP3', 'ANIM3', 'ARML3', 'ASAI3', 'AURE3', 'B3SA3',
                'BBAS3', 'BBDC3', 'BBDC4', 'BBSE3', 'BEEF3', 'BHIA3', 'BLAU3', 'BMOB3',
                'BPAC11', 'BRAP4', 'BRKM5', 'BRSR6',
                'CAML3', 'CASH3', 'CBAV3', 'CEAB3', 'CMIG3',
                'CMIG4', 'CMIN3', 'COGN3', 'CPFE3', 'CSAN3', 'CSMG3',
                'CSNA3', 'CURY3', 'CVCB3', 'CXSE3', 'CYRE3', 'DASA3', 'DIRR3', 'DXCO3',
                'ECOR3', 'EGIE3', 'ENEV3', 'ENGI11',
                'EQTL3', 'ESPA3', 'EVEN3', 'EZTC3', 'FESA4', 'FLRY3', 'FRAS3', 'GFSA3',
                'GGBR4', 'GGPS3', 'GMAT3', 'GOAU4', 'GRND3', 'HAPV3',
                'HBSA3', 'HYPE3', 'IFCM3', 'IGTI11', 'INTB3', 'IRBR3', 'ITSA4', 'ITUB3',
                'ITUB4', 'JALL3', 'JHSF3', 'KEPL3', 'KLBN11', 'LAVV3', 'LEVE3',
                'LJQQ3', 'LOGG3', 'LOGN3', 'LREN3', 'LUPA3', 'LWSA3', 'MATD3', 
                'MDIA3', 'MGLU3', 'MILS3', 'MLAS3', 'MOVI3', 'MRVE3', 'MULT3',
                'MYPK3', 'ONCO3', 'ORVR3', 'PCAR3', 'PETR3',
                'PETR4', 'PGMN3', 'PLPL3', 'PNVL3', 'POMO4', 'POSI3', 'PRIO3',
                'PSSA3', 'PTBL3', 'QUAL3', 'RADL3', 'RAIL3', 'RAIZ4', 'RANI3', 'RAPT4',
                'RDOR3', 'RECV3', 'RENT3', 'ROMI3', 'SANB11', 'SAPR11', 'SBFG3', 'SBSP3',
                'SEER3', 'SEQL3', 'SIMH3', 'SLCE3', 'SMFT3', 'SMTO3', 
                'SUZB3', 'TAEE11', 'TASA4', 'TEND3', 'TGMA3', 'TIMS3', 'TOTS3',
                'TRIS3', 'TTEN3', 'TUPY3', 'UGPA3', 'UNIP6', 'USIM5', 'VALE3',
                'VAMO3', 'VBBR3', 'VIVA3', 'VIVT3', 'VLID3', 'VULC3', 'WEGE3', 'WIZC3',
                'YDUQ3', 
            ],
        ],
        'us' => [
            'label' => 'EUA',
            'symbols' => [
                'AAPL', 'MSFT', 'GOOGL', 'AMZN', 'NVDA',
                'META', 'TSLA', 'JPM', 'V', 'WMT',
            ],
        ],
        'crypto' => [
            'label' => 'Cripto',
            'symbols' => [
                'BTCUSDT', 'ETHUSDT', 'BNBUSDT', 'SOLUSDT', 'XRPUSDT',
                'ADAUSDT', 'DOGEUSDT', 'AVAXUSDT', 'LINKUSDT', 'LTCUSDT',
            ],
        ],
    ],

];
