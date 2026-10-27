<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Visão geral da escola · {{ rangeLabel }}</p>
      </div>
      <div class="dash-filters">
        <div class="filter-tabs">
          <button
            v-for="tab in periodTabs"
            :key="tab.value"
            class="filter-tab"
            :class="{ active: mode === 'preset' && period === tab.value }"
            @click="setPeriod(tab.value)"
          >{{ tab.label }}</button>
          <button
            class="filter-tab"
            :class="{ active: mode === 'custom' }"
            @click="openCustom"
          >Personalizado</button>
        </div>
        <div v-if="mode === 'custom'" class="dash-range">
          <input class="form-control" type="date" v-model="customFrom" :max="customTo || undefined">
          <span class="dash-range-sep">até</span>
          <input class="form-control" type="date" v-model="customTo" :min="customFrom || undefined" :max="maxCustomTo || undefined">
          <button class="btn btn-primary btn-sm" :disabled="!canApplyRange" @click="applyRange">Aplicar</button>
          <span v-if="customRangeTooLong" class="form-error show dash-range-error">O período máximo é de 3 meses.</span>
        </div>
      </div>
    </div>

    <p v-if="loading && !data" class="page-subtitle">Carregando...</p>

    <template v-else-if="data">

      <div class="kpi-grid">
        <div class="stat-card">
          <span class="stat-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c0-3 2.5-5.5 5.5-5.5s5.5 2.5 5.5 5.5"/><path d="M16 5.6a3 3 0 0 1 0 5.8"/><path d="M17.6 13.8c2 .6 3.4 2.4 3.4 5"/></svg>
          </span>
          <span class="stat-value">{{ kpis.students_total }}</span>
          <span class="stat-label">Alunos · {{ kpis.students_active }} ativos</span>
        </div>

        <div class="stat-card">
          <span class="stat-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c0-3 2.5-5.5 5.5-5.5s5.5 2.5 5.5 5.5"/><path d="M18 8v6M15 11h6"/></svg>
          </span>
          <span class="stat-value">{{ kpis.students_new }}</span>
          <span class="stat-label">Novos alunos no período</span>
        </div>

        <div class="stat-card">
          <span class="stat-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h18v10H3z"/><path d="M3 11h18"/><path d="M7 15h3"/></svg>
          </span>
          <span class="stat-value">{{ formatMoney(kpis.revenue) }}</span>
          <span class="stat-label">Receita · ticket médio {{ formatMoney(kpis.avg_ticket) }}</span>
        </div>

        <div class="stat-card">
          <span class="stat-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9l3 3v15H6z"/><path d="M9 12h6M9 16h6M9 8h3"/></svg>
          </span>
          <span class="stat-value">{{ kpis.orders_paid }}</span>
          <span class="stat-label">Pedidos pagos · de {{ kpis.orders_total }} pedidos</span>
        </div>

        <div class="stat-card">
          <span class="stat-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20v3H6.5A2.5 2.5 0 0 1 4 20.5z"/></svg>
          </span>
          <span class="stat-value">{{ kpis.courses_published }}</span>
          <span class="stat-label">Cursos publicados · de {{ kpis.courses_total }} no total</span>
        </div>

        <div class="stat-card">
          <span class="stat-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3.5 19c0-3 2.5-5.5 5.5-5.5s5.5 2.5 5.5 5.5"/><path d="M16 5.5a3 3 0 0 1 0 5.9"/><path d="M17.5 13.7c2.2.6 3.9 2.5 3.9 5.3"/></svg>
          </span>
          <span class="stat-value">{{ kpis.teams_active }}</span>
          <span class="stat-label">Turmas ativas</span>
        </div>

        <div class="stat-card">
          <span class="stat-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="5"/><path d="M8.5 13.5 7 21l5-3 5 3-1.5-7.5"/></svg>
          </span>
          <span class="stat-value">{{ kpis.certificates_valid }}</span>
          <span class="stat-label">Certificados válidos</span>
        </div>

        <div class="stat-card">
          <span class="stat-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6-5.4-2.8L6.6 19.7l1-6L3.2 9.4l6.1-.9z"/></svg>
          </span>
          <span class="stat-value">{{ formatRating(kpis.rating_avg) }}</span>
          <span class="stat-label">Avaliação média · {{ kpis.ratings_count }} avaliações</span>
        </div>
      </div>

      <div class="dash-grid dash-grid-2">
        <section class="dash-panel">
          <div class="dash-panel-head">
            <h2 class="section-title">Receita no período</h2>
            <span class="dash-panel-tag">{{ formatMoney(kpis.revenue) }}</span>
          </div>
          <div class="dash-panel-body">
            <ChartCanvas v-if="revenueSeries.length" type="line" :data="revenueChartData" :options="revenueChartOptions" />
            <p v-else class="dash-empty">Sem movimentação no período.</p>
          </div>
        </section>

        <section class="dash-panel">
          <div class="dash-panel-head">
            <h2 class="section-title">Pedidos por status</h2>
          </div>
          <div class="dash-panel-body">
            <ChartCanvas v-if="ordersTotal" type="doughnut" :data="statusChartData" :options="statusChartOptions" />
            <p v-else class="dash-empty">Nenhum pedido no período.</p>
          </div>
        </section>
      </div>

      <div class="dash-grid dash-grid-3">
        <section class="dash-panel">
          <div class="dash-panel-head">
            <h2 class="section-title">Cursos mais vendidos</h2>
          </div>
          <ul v-if="data.top_courses.length" class="dash-list">
            <li v-for="(course, index) in data.top_courses" :key="course.id" class="dash-row">
              <span class="dash-rank">{{ index + 1 }}</span>
              <span class="dash-thumb" :style="thumbStyle(course.image)"></span>
              <span class="dash-main">
                <span class="dash-name">{{ course.name }}</span>
                <span class="dash-sub">{{ course.sales }} venda(s)</span>
              </span>
              <span class="dash-value">{{ formatMoney(course.revenue) }}</span>
            </li>
          </ul>
          <p v-else class="dash-empty">Nenhuma venda registrada.</p>
        </section>

        <section class="dash-panel">
          <div class="dash-panel-head">
            <h2 class="section-title">Top compradores</h2>
          </div>
          <ul v-if="data.top_buyers.length" class="dash-list">
            <li v-for="(buyer, index) in data.top_buyers" :key="buyer.id" class="dash-row">
              <span class="dash-rank">{{ index + 1 }}</span>
              <span class="dash-avatar">{{ initials(buyer.name) }}</span>
              <span class="dash-main">
                <span class="dash-name">{{ buyer.name }}</span>
                <span class="dash-sub">{{ buyer.orders_count }} pedido(s)</span>
              </span>
              <span class="dash-value">{{ formatMoney(buyer.total) }}</span>
            </li>
          </ul>
          <p v-else class="dash-empty">Nenhum comprador ainda.</p>
        </section>

        <section class="dash-panel">
          <div class="dash-panel-head">
            <h2 class="section-title">Alunos por curso</h2>
          </div>
          <ul v-if="data.students_per_course.length" class="dash-list">
            <li v-for="course in data.students_per_course" :key="course.id" class="dash-row">
              <span class="dash-thumb" :style="thumbStyle(course.image)"></span>
              <span class="dash-main">
                <span class="dash-name">{{ course.name }}</span>
                <span class="dash-sub">{{ course.students_count }} aluno(s)</span>
              </span>
            </li>
          </ul>
          <p v-else class="dash-empty">Nenhuma matrícula ativa.</p>
        </section>
      </div>

      <div class="dash-grid dash-grid-2">
        <section class="dash-panel">
          <div class="dash-panel-head">
            <h2 class="section-title">Últimos pedidos</h2>
          </div>
          <ul v-if="data.recent_orders.length" class="dash-list">
            <li v-for="order in data.recent_orders" :key="order.id" class="dash-row">
              <span class="dash-main">
                <span class="dash-name">{{ order.user_name || 'Cliente removido' }}</span>
                <span class="dash-sub">{{ formatDate(order.created_at) }}</span>
              </span>
              <span class="status-pill" :class="`is-${order.status}`">{{ statusLabel(order.status) }}</span>
              <span class="dash-value">{{ formatMoney(order.total) }}</span>
            </li>
          </ul>
          <p v-else class="dash-empty">Nenhum pedido no período.</p>
        </section>

        <section class="dash-panel">
          <div class="dash-panel-head">
            <h2 class="section-title">Últimos alunos</h2>
          </div>
          <ul v-if="data.recent_students.length" class="dash-list">
            <li v-for="student in data.recent_students" :key="student.id" class="dash-row">
              <span class="dash-avatar">{{ initials(student.name) }}</span>
              <span class="dash-main">
                <span class="dash-name">{{ student.name }}</span>
                <span class="dash-sub">{{ student.email }}</span>
              </span>
              <span class="dash-sub">{{ formatDate(student.created_at) }}</span>
            </li>
          </ul>
          <p v-else class="dash-empty">Nenhum aluno cadastrado no período.</p>
        </section>
      </div>

    </template>
  </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';
import ChartCanvas from '../components/admin/ChartCanvas.vue';

const toast = useToast();

const data = ref(null);
const loading = ref(true);
const mode = ref('preset');
const period = ref('30');
const customFrom = ref('');
const customTo = ref('');

const periodTabs = [
  { value: '7', label: '7 dias' },
  { value: '30', label: '30 dias' },
  { value: '90', label: '90 dias' },
];

const STATUS_META = {
  paid: { label: 'Pago', color: '#17c993' },
  pending: { label: 'Pendente', color: '#f0b429' },
  canceled: { label: 'Cancelado', color: '#94a3b8' },
  refunded: { label: 'Reembolsado', color: '#5b8def' },
};

const kpis = computed(() => data.value?.kpis ?? {});
const revenueSeries = computed(() => data.value?.revenue_series ?? []);
const ordersTotal = computed(() => (data.value?.order_status ?? []).reduce((sum, row) => sum + row.count, 0));

const maxCustomTo = computed(() => (customFrom.value ? addMonths(customFrom.value, 3) : ''));
const customRangeTooLong = computed(() => Boolean(customFrom.value && customTo.value) && customTo.value > maxCustomTo.value);
const canApplyRange = computed(() => Boolean(customFrom.value && customTo.value) && customFrom.value <= customTo.value && !customRangeTooLong.value);

const rangeLabel = computed(() => {
  if (mode.value === 'custom') {
    return customFrom.value && customTo.value
      ? `${formatShortDate(customFrom.value)} – ${formatShortDate(customTo.value)}`
      : 'Selecione o período';
  }

  return periodTabs.find((tab) => tab.value === period.value)?.label ?? '';
});

async function load() {
  loading.value = true;

  const params = mode.value === 'custom'
    ? { from: customFrom.value, to: customTo.value }
    : { period: period.value };

  try {
    const { data: response } = await api.get('/admin/dashboard', { params });
    data.value = response.data;
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar a dashboard.');
  } finally {
    loading.value = false;
  }
}

function setPeriod(value) {
  period.value = value;
  mode.value = 'preset';
  load();
}

function openCustom() {
  if (!customFrom.value || !customTo.value) {
    const today = new Date();
    const start = new Date();
    start.setDate(start.getDate() - 29);

    customFrom.value = toISODate(start);
    customTo.value = toISODate(today);
  }

  if (customRangeTooLong.value) {
    customTo.value = maxCustomTo.value;
  }

  mode.value = 'custom';
  load();
}

function applyRange() {
  if (!canApplyRange.value) {
    return;
  }

  mode.value = 'custom';
  load();
}

onMounted(load);

const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

function formatMoney(value) {
  return money.format(Number(value) || 0);
}

function formatRating(value) {
  return (Number(value) || 0).toFixed(1).replace('.', ',');
}

function formatDate(value) {
  if (!value) {
    return '';
  }

  try {
    return new Date(value).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
  } catch (e) {
    return '';
  }
}

function formatShortDate(value) {
  const [year, month, day] = (value || '').split('-');

  return year && month && day ? `${day}/${month}/${year}` : value;
}

function toISODate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

function addMonths(value, months) {
  const [year, month, day] = (value || '').split('-').map(Number);

  if (!year || !month || !day) {
    return '';
  }

  const date = new Date(year, month - 1 + months, 1, 12);
  const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
  date.setDate(Math.min(day, lastDay));

  return toISODate(date);
}

function initials(name) {
  const parts = (name || '').trim().split(/\s+/);

  if (!parts[0]) {
    return '?';
  }

  return ((parts[0][0] || '') + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
}

function statusLabel(status) {
  return STATUS_META[status]?.label ?? status;
}

function thumbStyle(image) {
  return image ? { backgroundImage: `url(${image})` } : {};
}

function cssVar(name, fallback) {
  if (typeof window === 'undefined') {
    return fallback;
  }

  return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}

function hexToRgba(hex, alpha) {
  const value = (hex || '').replace('#', '');

  if (value.length !== 6) {
    return hex;
  }

  const r = parseInt(value.slice(0, 2), 16);
  const g = parseInt(value.slice(2, 4), 16);
  const b = parseInt(value.slice(4, 6), 16);

  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

const revenueChartData = computed(() => {
  const accent = cssVar('--accent', '#17c993');

  return {
    labels: revenueSeries.value.map((point) => point.label),
    datasets: [
      {
        label: 'Receita',
        data: revenueSeries.value.map((point) => point.revenue),
        borderColor: accent,
        backgroundColor: hexToRgba(accent, 0.16),
        fill: true,
        tension: 0.35,
        borderWidth: 2,
        pointRadius: 0,
        pointHoverRadius: 4,
      },
    ],
  };
});

const revenueChartOptions = computed(() => {
  const muted = cssVar('--text-muted', '#5b6b83');
  const border = cssVar('--border', '#22304a');

  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: (ctx) => ` ${formatMoney(ctx.parsed.y)}` } },
    },
    scales: {
      x: { grid: { display: false }, ticks: { color: muted, maxRotation: 0, autoSkip: true } },
      y: {
        beginAtZero: true,
        grid: { color: border },
        ticks: { color: muted, callback: (value) => formatMoney(value) },
      },
    },
  };
});

const statusChartData = computed(() => {
  const rows = data.value?.order_status ?? [];

  return {
    labels: rows.map((row) => STATUS_META[row.status]?.label ?? row.status),
    datasets: [
      {
        data: rows.map((row) => row.count),
        backgroundColor: rows.map((row) => STATUS_META[row.status]?.color ?? '#94a3b8'),
        borderWidth: 0,
      },
    ],
  };
});

const statusChartOptions = computed(() => {
  const muted = cssVar('--text-muted', '#5b6b83');

  return {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '62%',
    plugins: {
      legend: {
        position: 'bottom',
        labels: { color: muted, boxWidth: 10, padding: 14, usePointStyle: true },
      },
    },
  };
});
</script>

<style scoped>
.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;}
.dash-filters{display:flex;align-items:center;gap:14px;flex-wrap:wrap;justify-content:flex-end;}
.dash-range{display:grid;grid-template-columns:auto auto auto auto;align-items:center;column-gap:8px;row-gap:4px;}
.dash-range .form-control{width:auto;min-width:150px;}
.dash-range-sep{font-size:12px;font-weight:600;color:var(--text-secondary);}
.dash-range-error{grid-column:1 / 4;}
.dash-grid{display:grid;gap:16px;margin-top:18px;}
.dash-grid-2{grid-template-columns:repeat(auto-fit,minmax(320px,1fr));}
.dash-grid-3{grid-template-columns:repeat(auto-fit,minmax(260px,1fr));}
.dash-panel{
  background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);
  padding:18px;box-shadow:var(--shadow-sm);display:flex;flex-direction:column;gap:14px;
}
.dash-panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;}
.dash-panel-tag{font-size:13px;font-weight:800;color:var(--accent);}
.dash-panel-body{min-height:0;}
.dash-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;}
.dash-row{display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid var(--border);}
.dash-row:last-child{border-bottom:0;padding-bottom:0;}
.dash-rank{
  width:22px;flex:none;font-size:12px;font-weight:800;color:var(--text-muted);text-align:center;
}
.dash-thumb{
  width:34px;height:34px;flex:none;border-radius:9px;background:var(--bg-card-alt);
  background-size:cover;background-position:center;border:1px solid var(--border);
}
.dash-avatar{
  width:34px;height:34px;flex:none;border-radius:50%;background:var(--accent-soft);color:var(--accent);
  display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;
}
.dash-main{flex:1;min-width:0;display:flex;flex-direction:column;gap:1px;}
.dash-name{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.dash-sub{font-size:11.5px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.dash-value{font-size:13px;font-weight:800;white-space:nowrap;}
.dash-empty{font-size:12.5px;color:var(--text-muted);margin:0;padding:8px 0;}
.status-pill.is-paid{background:rgba(23,201,147,.15);color:#17c993;}
.status-pill.is-pending{background:rgba(240,180,41,.15);color:#f0b429;}
.status-pill.is-canceled{background:rgba(148,163,184,.18);color:#94a3b8;}
.status-pill.is-refunded{background:rgba(91,141,239,.16);color:#5b8def;}
@media (max-width:1100px){.kpi-grid{grid-template-columns:repeat(2,1fr);}}
@media (max-width:560px){.kpi-grid{grid-template-columns:1fr;}}
</style>
