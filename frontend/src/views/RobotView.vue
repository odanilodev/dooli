<template>
  <main class="content">
    <div class="page-header">
      <div>
        <router-link class="btn btn-outline btn-sm robot-back" :to="{ name: 'robos' }">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
          Robôs
        </router-link>
        <h1 class="page-title">{{ robot?.name || 'Robô' }}</h1>
        <p class="page-subtitle">{{ robot?.description }}</p>
      </div>
    </div>

    <section class="panel" v-if="robot">
      <form @submit.prevent="run">
        <div class="field">
          <span class="label">Mercado</span>
          <div class="seg">
            <button v-for="(m, code) in markets" :key="code" type="button" :class="{ on: form.market === code }" @click="selectMarket(code)">
              {{ m.label }}
            </button>
          </div>
          <small class="hint" v-if="market.symbols.length">
            {{ market.symbols.length }} ativos · dados de {{ fmtDate(market.from) }} a {{ fmtDate(market.to) }}
          </small>
          <small class="hint warn" v-else>Ainda não há dados coletados para este mercado.</small>
        </div>

        <div class="row">
          <div class="field">
            <span class="label">Operação</span>
            <div class="seg">
              <button type="button" :class="{ on: form.side === 'buy' }" @click="form.side = 'buy'">Compra</button>
              <button type="button" :class="{ on: form.side === 'sell' }" @click="form.side = 'sell'">Venda</button>
            </div>
          </div>

          <div class="field">
            <span class="label">Limiar de entrada (%)</span>
            <div class="chips">
              <button v-for="t in thresholds" :key="t" type="button" class="chip" :class="{ on: form.threshold === t }" @click="form.threshold = t">{{ t }}%</button>
              <input v-model.number="form.threshold" class="num" type="number" step="0.1" min="0.1" max="50" required />
            </div>
          </div>
        </div>

        <div class="field">
          <span class="label">Período</span>
          <div class="chips">
            <button v-for="p in periods" :key="p.label" type="button" class="chip" @click="applyPeriod(p.months)">{{ p.label }}</button>
            <input v-model="form.start_date" type="date" :min="market.from" :max="form.end_date" required />
            <span class="sep">até</span>
            <input v-model="form.end_date" type="date" :min="form.start_date" :max="market.to" required />
          </div>
        </div>

        <div class="field">
          <span class="label">Ativos</span>
          <div class="picker" ref="pickerEl">
            <button type="button" class="picker-btn" @click="pickerOpen = !pickerOpen" :disabled="!market.symbols.length">
              <span>{{ pickerLabel }}</span>
              <span class="caret">▾</span>
            </button>
            <div class="picker-menu" v-if="pickerOpen">
              <input v-model="symbolSearch" type="search" placeholder="Buscar ou digitar um ativo..." @keydown.enter.prevent="canAdd && addAsset()" />
              <div class="picker-actions">
                <button type="button" class="link" @click="selected = market.symbols.map((s) => s.symbol)">Marcar todos</button>
                <button type="button" class="link" @click="selected = []">Limpar</button>
              </div>
              <ul>
                <li v-for="s in filteredSymbols" :key="s.symbol">
                  <label>
                    <input type="checkbox" :checked="selected.includes(s.symbol)" @change="toggleSymbol(s.symbol)" />
                    {{ s.symbol }}
                  </label>
                </li>
                <li class="muted" v-if="!filteredSymbols.length && !canAdd">Nenhum ativo encontrado.</li>
              </ul>
              <button type="button" class="add-asset" v-if="canAdd" :disabled="adding" @click="addAsset">
                {{ adding ? 'Buscando dados...' : `Adicionar “${typedSymbol}” à base` }}
              </button>
              <small class="add-error" v-if="addError">{{ addError }}</small>
            </div>
          </div>
        </div>

        <button class="btn btn-primary" type="submit" :disabled="running || !market.symbols.length">
          {{ running ? 'Executando...' : 'Executar análise' }}
        </button>
      </form>
    </section>

    <p class="error" v-if="error">{{ error }}</p>

    <template v-if="result">
      <div class="stats">
        <div class="stat"><span>Ativos com sinal</span><strong>{{ result.summary.assets }}</strong></div>
        <div class="stat"><span>Operações</span><strong>{{ result.summary.total_trades }}</strong></div>
        <div class="stat"><span>% de acerto geral</span><strong>{{ overallWinRate }}%</strong></div>
        <div class="stat"><span>Resultado líquido</span><strong :class="tone(result.summary.net_result)">{{ signed(result.summary.net_result) }}%</strong></div>
      </div>

      <div class="toolbar">
        <input v-model="filters.search" type="search" placeholder="Buscar ativo..." />
        <label>Mín. operações <input v-model.number="filters.minTrades" type="number" min="0" /></label>
        <label>Mín. % acerto <input v-model.number="filters.minWinRate" type="number" min="0" max="100" /></label>
        <label class="check"><input v-model="filters.onlyPositive" type="checkbox" /> Só resultado positivo</label>
        <button type="button" class="btn btn-outline btn-sm" @click="exportCsv" :disabled="!visibleRows.length">Exportar CSV</button>
      </div>

      <div class="table-wrap" v-if="visibleRows.length">
        <table>
          <thead>
            <tr>
              <th v-for="c in columns" :key="c.key" @click="sortBy(c.key)" :class="{ sorted: sort.key === c.key }">
                {{ c.label }}<span class="arrow" v-if="sort.key === c.key">{{ sort.dir === 'asc' ? '↑' : '↓' }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="row in visibleRows" :key="row.symbol">
              <tr class="row-main" :class="{ open: expanded === row.symbol }" @click="toggleDetail(row.symbol)">
                <td><strong>{{ row.symbol }}</strong></td>
                <td>{{ row.total_trades }}</td>
                <td>{{ row.gains }}</td>
                <td>{{ row.losses }}</td>
                <td>{{ row.win_rate }}%</td>
                <td :class="tone(row.net_result)">{{ signed(row.net_result) }}</td>
                <td :class="tone(row.avg_result)">{{ signed(row.avg_result) }}</td>
                <td class="pos">{{ signed(row.best_trade) }}</td>
                <td class="neg">{{ signed(row.worst_trade) }}</td>
                <td>{{ qty(row.avg_volume) }}</td>
              </tr>
              <tr class="row-detail" v-if="expanded === row.symbol">
                <td :colspan="columns.length">
                  <p class="muted" v-if="detailLoading">Carregando operações...</p>
                  <div class="detail-scroll" v-else>
                    <table class="mini">
                      <thead><tr><th>Data</th><th>Abertura</th><th>Entrada</th><th>Fechamento</th><th>Volume</th><th>Resultado</th></tr></thead>
                      <tbody>
                        <tr v-for="t in detail" :key="t.date">
                          <td>{{ fmtDate(t.date) }}</td>
                          <td>{{ price(t.open) }}</td>
                          <td>{{ price(t.entry_price) }}</td>
                          <td>{{ price(t.close) }}</td>
                          <td>{{ qty(t.volume) }}</td>
                          <td :class="tone(t.result)">{{ signed(t.result) }}%</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <div class="empty-state" v-else>
        <strong>Nenhum ativo para exibir</strong>
        <span>{{ result.rows.length ? 'Os filtros escondem todos os resultados.' : 'Nenhuma operação disparou. Tente outro período, mercado ou limiar.' }}</span>
      </div>
    </template>
  </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api/http';

const route = useRoute();
const slug = route.params.slug;

const robot = ref(null);
const result = ref(null);
const lastParams = ref(null);
const error = ref('');
const running = ref(false);
const selected = ref([]);
const expanded = ref(null);
const detail = ref([]);
const detailLoading = ref(false);

const thresholds = [1, 2, 3, 5];
const periods = [
  { label: '3 meses', months: 3 },
  { label: '6 meses', months: 6 },
  { label: '1 ano', months: 12 },
  { label: 'Tudo', months: 0 },
];

const columns = [
  { key: 'symbol', label: 'Ativo' },
  { key: 'total_trades', label: 'Operações' },
  { key: 'gains', label: 'Acertos' },
  { key: 'losses', label: 'Erros' },
  { key: 'win_rate', label: '% acerto' },
  { key: 'net_result', label: 'Líquido (%)' },
  { key: 'avg_result', label: 'Média (%)' },
  { key: 'best_trade', label: 'Melhor' },
  { key: 'worst_trade', label: 'Pior' },
  { key: 'avg_volume', label: 'Volume médio' },
];

const form = reactive({ market: 'crypto', side: 'buy', threshold: 2, start_date: '', end_date: '' });
const filters = reactive({ search: '', minTrades: 0, minWinRate: 0, onlyPositive: false });
const sort = reactive({ key: 'net_result', dir: 'desc' });

const markets = computed(() => robot.value?.options?.markets ?? {});
const market = computed(() => markets.value[form.market] ?? { symbols: [], from: null, to: null });

const pickerOpen = ref(false);
const pickerEl = ref(null);
const symbolSearch = ref('');

const filteredSymbols = computed(() => {
  const term = symbolSearch.value.trim().toUpperCase();
  return market.value.symbols.filter((s) => !term || s.symbol.includes(term));
});

const adding = ref(false);
const addError = ref('');
const typedSymbol = computed(() => symbolSearch.value.trim().toUpperCase());
// Só oferece cadastrar quando o que foi digitado ainda não está na base deste mercado.
const canAdd = computed(() => /^[A-Z0-9.\-]{1,15}$/.test(typedSymbol.value)
  && !market.value.symbols.some((s) => s.symbol === typedSymbol.value));

async function addAsset() {
  adding.value = true;
  addError.value = '';

  try {
    await api.post(`/me/robots/${slug}/assets`, { market: form.market, symbol: typedSymbol.value });
    const symbol = typedSymbol.value;
    const { data } = await api.get(`/me/robots/${slug}`);
    robot.value = data.data;
    selected.value = [...selected.value, symbol];
    symbolSearch.value = '';
  } catch (e) {
    addError.value = e.response?.data?.errors?.symbol?.[0] || e.response?.data?.message || 'Não foi possível adicionar o ativo.';
  } finally {
    adding.value = false;
  }
}

const pickerLabel = computed(() => {
  const total = market.value.symbols.length;
  if (!selected.value.length || selected.value.length === total) return `Todos os ativos (${total})`;
  return selected.value.length <= 3 ? selected.value.join(', ') : `${selected.value.length} ativos selecionados`;
});

function closePickerOutside(e) {
  if (pickerEl.value && !pickerEl.value.contains(e.target)) pickerOpen.value = false;
}

onMounted(() => document.addEventListener('click', closePickerOutside));
onBeforeUnmount(() => document.removeEventListener('click', closePickerOutside));

const overallWinRate = computed(() => {
  const s = result.value?.summary;
  return s && s.total_trades ? ((s.gains / s.total_trades) * 100).toFixed(1) : '0.0';
});

const visibleRows = computed(() => {
  if (!result.value) return [];
  const term = filters.search.trim().toUpperCase();

  return result.value.rows
    .filter((r) => (!term || r.symbol.includes(term))
      && r.total_trades >= (filters.minTrades || 0)
      && Number(r.win_rate) >= (filters.minWinRate || 0)
      && (!filters.onlyPositive || Number(r.net_result) > 0))
    .sort((a, b) => {
      const x = sort.key === 'symbol' ? a.symbol : Number(a[sort.key]);
      const y = sort.key === 'symbol' ? b.symbol : Number(b[sort.key]);
      const cmp = x > y ? 1 : x < y ? -1 : 0;
      return sort.dir === 'asc' ? cmp : -cmp;
    });
});

const fmtDate = (iso) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '—');
const signed = (v) => `${Number(v) > 0 ? '+' : ''}${Number(v).toFixed(2)}`;
const tone = (v) => (Number(v) > 0 ? 'pos' : Number(v) < 0 ? 'neg' : '');
const qty = (v) => (v == null ? '—' : Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 2 }));
const price = (v) => Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 4 });

function selectMarket(code) {
  form.market = code;
  selected.value = [];
  applyPeriod(12);
}

function applyPeriod(months) {
  const to = market.value.to || new Date().toISOString().slice(0, 10);
  form.end_date = to;

  if (!months) {
    form.start_date = market.value.from || to;
    return;
  }

  const d = new Date(`${to}T00:00:00`);
  d.setMonth(d.getMonth() - months);
  const start = d.toISOString().slice(0, 10);
  form.start_date = market.value.from && start < market.value.from ? market.value.from : start;
}

function toggleSymbol(symbol) {
  selected.value = selected.value.includes(symbol)
    ? selected.value.filter((s) => s !== symbol)
    : [...selected.value, symbol];
}

function sortBy(key) {
  if (sort.key === key) {
    sort.dir = sort.dir === 'asc' ? 'desc' : 'asc';
  } else {
    sort.key = key;
    sort.dir = key === 'symbol' ? 'asc' : 'desc';
  }
}

async function run() {
  running.value = true;
  error.value = '';
  expanded.value = null;

  const params = { ...form, ...(selected.value.length ? { symbols: [...selected.value] } : {}) };

  try {
    const { data } = await api.post(`/me/robots/${slug}/run`, params);
    result.value = data.data;
    lastParams.value = params;
  } catch (e) {
    result.value = null;
    error.value = e.response?.data?.message || 'Não foi possível executar o robô.';
  } finally {
    running.value = false;
  }
}

async function toggleDetail(symbol) {
  if (expanded.value === symbol) {
    expanded.value = null;
    return;
  }

  expanded.value = symbol;
  detail.value = [];
  detailLoading.value = true;

  try {
    const { data } = await api.post(`/me/robots/${slug}/detail`, { ...lastParams.value, symbol });
    detail.value = data.data.trades;
  } finally {
    detailLoading.value = false;
  }
}

function exportCsv() {
  const head = columns.map((c) => c.label).join(';');
  const lines = visibleRows.value.map((r) => columns.map((c) => r[c.key]).join(';'));
  const blob = new Blob([`﻿${[head, ...lines].join('\n')}`], { type: 'text/csv;charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `${slug}-${form.market}-${form.start_date}_${form.end_date}.csv`;
  a.click();
  URL.revokeObjectURL(a.href);
}

onMounted(async () => {
  const { data } = await api.get(`/me/robots/${slug}`);
  robot.value = data.data;

  const first = Object.keys(markets.value).find((c) => markets.value[c].symbols.length) || Object.keys(markets.value)[0];
  selectMarket(first);
});
</script>

<style scoped>
.robot-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; }
.panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 22px; margin-bottom: 20px; }
form { display: flex; flex-direction: column; gap: 16px; }
.row { display: flex; flex-wrap: wrap; gap: 24px; }
.field { display: flex; flex-direction: column; gap: 7px; }
.label { font-size: 12px; font-weight: 700; color: var(--text-muted); display: flex; gap: 8px; align-items: center; }
.hint { font-size: 11.5px; color: var(--text-muted); }
.hint.warn { color: #f59e0b; }
.muted { font-weight: 400; color: var(--text-muted); }
.seg { display: inline-flex; border: 1.5px solid var(--border); border-radius: 10px; overflow: hidden; align-self: flex-start; }
.seg button { padding: 8px 16px; background: transparent; border: 0; color: var(--text-secondary); font: inherit; font-size: 13px; cursor: pointer; }
.seg button.on { background: var(--accent-grad); color: var(--accent-ink); font-weight: 700; }
.chips { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.chip { padding: 6px 12px; border-radius: 999px; border: 1.5px solid var(--border); background: transparent; color: var(--text-secondary); font: inherit; font-size: 12px; cursor: pointer; }
.chip:hover { border-color: var(--accent); }
.chip.on { border-color: var(--accent); color: var(--accent); font-weight: 700; }
.link { background: none; border: 0; color: var(--accent); font: inherit; font-size: 11.5px; cursor: pointer; padding: 0; }
.sep { font-size: 12px; color: var(--text-muted); }
input[type=date], input[type=number], input[type=search] {
  padding: 7px 10px; border-radius: 9px; border: 1.5px solid var(--border);
  background: var(--bg-input); color: var(--text-primary); font: inherit; font-size: 13px;
}
input.num { width: 76px; }
form .btn { align-self: flex-start; }
.picker { position: relative; align-self: flex-start; min-width: 280px; }
.picker-btn { width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 9px 12px; border-radius: 10px; border: 1.5px solid var(--border); background: var(--bg-input); color: var(--text-primary); font: inherit; font-size: 13px; cursor: pointer; }
.picker-btn:disabled { opacity: .5; cursor: not-allowed; }
.caret { color: var(--text-muted); font-size: 11px; }
.picker-menu { position: absolute; z-index: 20; top: calc(100% + 6px); left: 0; right: 0; min-width: 280px; background: var(--bg-card); border: 1.5px solid var(--border); border-radius: 12px; padding: 10px; box-shadow: 0 12px 32px rgba(0, 0, 0, .35); display: flex; flex-direction: column; gap: 8px; }
.picker-menu input[type=search] { width: 100%; box-sizing: border-box; }
.picker-actions { display: flex; justify-content: space-between; }
.picker-menu ul { list-style: none; margin: 0; padding: 0; max-height: 240px; overflow-y: auto; }
.picker-menu li label { display: flex; align-items: center; gap: 8px; padding: 6px 4px; font-size: 13px; cursor: pointer; border-radius: 6px; }
.picker-menu li label:hover { background: rgba(255, 255, 255, .05); }
.add-asset { padding: 9px 10px; border-radius: 9px; border: 1.5px dashed var(--accent); background: transparent; color: var(--accent); font: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; }
.add-asset:disabled { opacity: .6; cursor: wait; }
.add-error { color: #ef4444; font-size: 11.5px; }
.picker-menu li.muted { padding: 6px 4px; font-size: 12px; }
.error { color: #ef4444; font-size: 13px; }

.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 16px; }
.stat { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px 14px; display: flex; flex-direction: column; gap: 4px; }
.stat span { font-size: 11.5px; color: var(--text-muted); }
.stat strong { font-size: 20px; }

.toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 12px; }
.toolbar label { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted); }
.toolbar label input[type=number] { width: 70px; }
.toolbar .check { cursor: pointer; }
.toolbar .check input[type="checkbox"] {
  appearance: none; -webkit-appearance: none; margin: 0; flex-shrink: 0;
  width: 18px; height: 18px; display: inline-grid; place-content: center; cursor: pointer;
  border: 1.5px solid var(--border); border-radius: 6px; background: var(--bg-input);
  transition: border-color .14s ease, background .14s ease;
}
.toolbar .check input[type="checkbox"]::before {
  content: ""; width: 10px; height: 10px; transform: scale(0); transition: transform .12s ease;
  box-shadow: inset 1em 1em var(--accent-ink);
  clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
}
.toolbar .check:hover input[type="checkbox"] { border-color: var(--accent); }
.toolbar .check input[type="checkbox"]:checked { background: var(--accent); border-color: var(--accent); }
.toolbar .check input[type="checkbox"]:checked::before { transform: scale(1); }
.toolbar .check input[type="checkbox"]:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.toolbar .btn { margin-left: auto; }

.table-wrap { background: var(--bg-card); overflow-x: auto; border: 1px solid var(--border); border-radius: 12px; }
table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
th, td { padding: 10px 12px; text-align: right; white-space: nowrap; }
th:first-child, td:first-child { text-align: left; }
thead th { color: var(--text-muted); font-weight: 700; border-bottom: 1px solid var(--border); cursor: pointer; user-select: none; }
thead th.sorted { color: var(--text-primary); }
.arrow { margin-left: 4px; color: var(--accent); }
.row-main { cursor: pointer; }
.row-main:hover td, .row-main.open td { background: rgba(255, 255, 255, .03); }
tbody tr + tr td { border-top: 1px solid var(--border); }
.row-detail td { text-align: left; background: rgba(0, 0, 0, .12); }
.detail-scroll { max-height: 280px; overflow-y: auto; }
.mini { font-size: 12px; }
.mini th { cursor: default; }
.pos { color: #22c55e; font-weight: 700; }
.neg { color: #ef4444; font-weight: 700; }
</style>
