<template>
  <main class="content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Pagamentos</h1>
        <p class="page-subtitle">Recebimentos, tentativas e estornos da empresa.</p>
      </div>
      <router-link :to="{ name: 'admin-vendas' }" class="btn btn-outline btn-sm">Vendas</router-link>
    </div>

    <section class="payment-kpis">
      <article><span>Recebido</span><strong>{{ formatCurrency(summary.received) }}</strong></article>
      <article><span>Pendente</span><strong>{{ formatCurrency(summary.pending) }}</strong></article>
      <article><span>Estornado</span><strong>{{ formatCurrency(summary.refunded) }}</strong></article>
      <article><span>Falhas</span><strong>{{ formatCurrency(summary.failed) }}</strong></article>
    </section>

    <form class="payments-filters" @submit.prevent="reload">
      <input v-model="filters.search" class="form-control" type="search" placeholder="Buscar por transação ou status">
      <select v-model="filters.status" class="form-control">
        <option value="">Todos os status</option>
        <option value="pending">Pendente</option>
        <option value="paid">Aprovado</option>
        <option value="failed">Recusado</option>
        <option value="refunded">Estornado</option>
      </select>
      <select v-model="filters.method" class="form-control">
        <option value="">Todos os métodos</option>
        <option value="credit_card">Cartão</option>
        <option value="pix">PIX</option>
      </select>
      <select v-model="filters.gateway_id" class="form-control">
        <option value="">Todos os gateways</option>
        <option v-for="gateway in gateways" :key="gateway.id" :value="String(gateway.id)">{{ gateway.name }}</option>
      </select>
      <input v-model="filters.date_from" class="form-control" type="date" aria-label="Data inicial">
      <input v-model="filters.date_to" class="form-control" type="date" aria-label="Data final">
      <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
      <button v-if="hasFilters" class="btn btn-outline btn-sm" type="button" @click="clearFilters">Limpar</button>
    </form>

    <p v-if="filters.order_id" class="order-filter-note">
      Filtrando pelo pedido <strong>#{{ filters.order_id }}</strong>
      <button class="admin-link" type="button" @click="clearOrderFilter">remover filtro</button>
    </p>

    <div v-if="loading" class="empty-state"><strong>Carregando...</strong></div>
    <div v-else-if="!payments.length" class="empty-state"><strong>Nenhum pagamento</strong></div>
    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>#</th><th>Pedido</th><th>Aluno</th><th>Gateway</th><th>Método</th><th>Valor</th><th>Status</th><th>Data</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="payment in payments" :key="payment.id">
            <td>{{ payment.id }}</td>
            <td>
              #{{ payment.order?.id || payment.order_id }}
              <span v-if="payment.order?.status" class="admin-sub">{{ orderStatusLabel(payment.order.status) }}</span>
            </td>
            <td>
              {{ payment.order?.user?.name || 'Aluno' }}
              <span v-if="payment.order?.user?.email" class="admin-sub">{{ payment.order.user.email }}</span>
            </td>
            <td class="admin-muted">{{ payment.gateway?.name || '—' }}</td>
            <td class="admin-muted">{{ paymentMethodLabel(payment.method) }}</td>
            <td>{{ formatCurrency(payment.amount) }}</td>
            <td>
              <span class="status-pill" :class="paymentStatusClass(payment.status)">{{ paymentStatusLabel(payment.status) }}</span>
              <span v-if="Number(payment.refunded_amount) > 0" class="admin-sub">Estornado: {{ formatCurrency(payment.refunded_amount) }}</span>
            </td>
            <td class="admin-muted">{{ formatDateTime(payment.paid_at || payment.created_at) }}</td>
            <td class="admin-actions">
              <button class="btn btn-outline btn-sm" type="button" @click="openPayment(payment)">Detalhes</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="admin-pager">
      <button class="btn btn-outline btn-sm" type="button" :disabled="meta.current_page <= 1" @click="go(meta.current_page - 1)">Anterior</button>
      <span class="admin-muted">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      <button class="btn btn-outline btn-sm" type="button" :disabled="meta.current_page >= meta.last_page" @click="go(meta.current_page + 1)">Próxima</button>
    </div>

    <PaymentDetailDrawer
      :open="drawerOpen"
      :payment-id="selectedPaymentId"
      @close="drawerOpen = false"
      @refunded="load"
    />
  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api/http';
import { formatCurrency, formatDateTime } from '../utils/format';
import {
  orderStatusLabel,
  paymentMethodLabel,
  paymentStatusClass,
  paymentStatusLabel,
} from '../utils/orderStatus';
import PaymentDetailDrawer from '../components/admin/PaymentDetailDrawer.vue';

const route = useRoute();
const payments = ref([]);
const gateways = ref([]);
const summary = ref({ received: 0, pending: 0, refunded: 0, failed: 0 });
const meta = ref({ current_page: 1, last_page: 1 });
const loading = ref(true);
const drawerOpen = ref(false);
const selectedPaymentId = ref(null);
const filters = reactive({
  search: '',
  status: '',
  method: '',
  gateway_id: '',
  date_from: '',
  date_to: '',
  order_id: route.query.order_id ? String(route.query.order_id) : '',
});

const hasFilters = computed(() => Boolean(
  filters.search || filters.status || filters.method || filters.gateway_id
  || filters.date_from || filters.date_to || filters.order_id,
));

onMounted(async () => {
  await loadGateways();
  await load();
});

async function loadGateways() {
  try {
    const { data } = await api.get('/admin/gateways', { params: { per_page: 100 } });
    gateways.value = data.data ?? [];
  } catch (e) {
    gateways.value = [];
  }
}

function queryParams() {
  return {
    search: filters.search || undefined,
    status: filters.status || undefined,
    method: filters.method || undefined,
    gateway_id: filters.gateway_id || undefined,
    date_from: filters.date_from || undefined,
    date_to: filters.date_to || undefined,
    order_id: filters.order_id || undefined,
  };
}

async function load() {
  loading.value = true;

  try {
    const [{ data: list }, { data: totals }] = await Promise.all([
      api.get('/admin/payments', {
        params: { ...queryParams(), page: meta.value.current_page, per_page: 20 },
      }),
      api.get('/admin/payments/summary', {
        params: {
          method: filters.method || undefined,
          gateway_id: filters.gateway_id || undefined,
          date_from: filters.date_from || undefined,
          date_to: filters.date_to || undefined,
        },
      }),
    ]);

    payments.value = list.data ?? [];
    meta.value = list.meta ?? meta.value;
    summary.value = { received: 0, pending: 0, refunded: 0, failed: 0, ...(totals.data || {}) };
  } finally {
    loading.value = false;
  }
}

function reload() {
  meta.value.current_page = 1;
  load();
}

function go(page) {
  meta.value.current_page = page;
  load();
}

function clearFilters() {
  filters.search = '';
  filters.status = '';
  filters.method = '';
  filters.gateway_id = '';
  filters.date_from = '';
  filters.date_to = '';
  filters.order_id = '';
  reload();
}

function clearOrderFilter() {
  filters.order_id = '';
  reload();
}

function openPayment(payment) {
  selectedPaymentId.value = payment.id;
  drawerOpen.value = true;
}
</script>

<style scoped>
.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.payment-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:18px;}
.payment-kpis article{display:flex;flex-direction:column;gap:6px;padding:18px;border:1px solid var(--border);border-radius:16px;background:var(--bg-card);}
.payment-kpis span{color:var(--text-muted);font-size:10.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;}
.payment-kpis strong{font-size:24px;letter-spacing:-.03em;}
.payments-filters{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:14px;margin-bottom:18px;border:1px solid var(--border);border-radius:14px;background:var(--bg-card);}
.payments-filters .form-control{min-width:140px;flex:1 1 150px;}
.payments-filters input[type="search"]{flex-basis:240px;}
.payments-filters .btn{flex-shrink:0;}
.order-filter-note{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:-8px 0 14px;color:var(--text-muted);font-size:12px;}
.order-filter-note button{border:0;background:transparent;padding:0;cursor:pointer;font:inherit;}
</style>
