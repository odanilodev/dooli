<template>
  <main class="content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Vendas</h1>
        <p class="page-subtitle">Vendas pagas e histórico de pedidos da empresa.</p>
      </div>
      <div class="admin-head-actions">
        <router-link :to="{ name: 'admin-pagamentos' }" class="btn btn-outline btn-sm">Pagamentos</router-link>
        <button class="btn btn-outline btn-sm" type="button" :disabled="exporting" @click="exportFile">
          {{ exporting ? 'Exportando...' : 'Exportar Excel' }}
        </button>
      </div>
    </div>

    <section class="sales-kpis">
      <article><span>Vendas pagas</span><strong>{{ summary.paid_orders }}</strong></article>
      <article><span>Faturamento</span><strong>{{ formatCurrency(summary.revenue) }}</strong></article>
      <article><span>Ticket médio</span><strong>{{ formatCurrency(summary.avg_ticket) }}</strong></article>
      <article><span>Estornado</span><strong>{{ formatCurrency(summary.refunded) }}</strong></article>
    </section>

    <form class="sales-filters" @submit.prevent="reload">
      <input v-model="filters.search" class="form-control" type="search" placeholder="Buscar por aluno, e-mail ou #pedido">
      <select v-model="filters.status" class="form-control">
        <option value="">Todos os status</option>
        <option value="paid">Pago</option>
        <option value="pending">Pendente</option>
        <option value="canceled">Cancelado</option>
        <option value="refunded">Estornado</option>
      </select>
      <SearchSelect
        v-model="filters.seller_id"
        :options="sellerOptions"
        :loading="sellersLoading"
        remote
        allow-clear
        clear-label="Todos os vendedores"
        placeholder="Todos os vendedores"
        @search="loadSellers"
      />
      <select v-model="filters.source" class="form-control">
        <option value="">Todas as origens</option>
        <option value="store">Loja</option>
        <option value="manual">Manual</option>
        <option value="unknown">Desconhecida</option>
      </select>
      <input v-model="filters.date_from" class="form-control" type="date" aria-label="Data inicial">
      <input v-model="filters.date_to" class="form-control" type="date" aria-label="Data final">
      <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
      <button v-if="hasFilters" class="btn btn-outline btn-sm" type="button" @click="clearFilters">Limpar</button>
    </form>

    <div v-if="loading" class="empty-state"><strong>Carregando...</strong></div>
    <div v-else-if="!orders.length" class="empty-state"><strong>Nenhuma venda encontrada</strong></div>
    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>#</th><th>Cliente</th><th>Produto</th><th>Tipo</th><th>Origem</th><th>Vendedor</th><th>Valor</th><th>Pagamento</th><th>Status</th><th>Data</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="order in orders" :key="order.id">
            <td>#{{ order.id }}</td>
            <td>
              {{ order.user?.name || 'Aluno' }}
              <span v-if="order.user?.email" class="admin-sub">{{ order.user.email }}</span>
            </td>
            <td class="admin-muted">
              <template v-if="order.items?.length">
                {{ order.items[0].product_title || order.items[0].store_product?.title || 'Produto' }}
                <span v-if="order.items.length > 1" class="admin-sub">+{{ order.items.length - 1 }} item(ns)</span>
              </template>
              <span v-else>—</span>
            </td>
            <td class="admin-muted">{{ orderTypeLabel(order.type) }}</td>
            <td><span class="status-pill" :class="originClass(order.source)">{{ originLabel(order.source) }}</span></td>
            <td>
              <span v-if="order.seller">{{ order.seller.name }}</span>
              <span v-else class="admin-muted">—</span>
            </td>
            <td>{{ formatCurrency(order.total) }}</td>
            <td class="admin-muted">
              <span v-if="order.latest_payment">
                {{ paymentMethodLabel(order.latest_payment.method) }}
                · {{ paymentStatusLabel(order.latest_payment.status) }}
              </span>
              <span v-else>Não iniciado</span>
            </td>
            <td><span class="status-pill" :class="orderStatusClass(order.status)">{{ statusLabel(order.status) }}</span></td>
            <td class="admin-muted">{{ formatDateTime(order.created_at) }}</td>
            <td class="admin-actions">
              <button class="btn btn-outline btn-sm" type="button" @click="openOrder(order)">Detalhes</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="admin-pager">
      <button class="btn btn-outline btn-sm" :disabled="meta.current_page <= 1" @click="go(meta.current_page - 1)">Anterior</button>
      <span class="admin-muted">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      <button class="btn btn-outline btn-sm" :disabled="meta.current_page >= meta.last_page" @click="go(meta.current_page + 1)">Próxima</button>
    </div>

    <OrderDetailDrawer :open="drawerOpen" :order-id="selectedOrderId" @close="drawerOpen = false" />
  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';
import { formatCurrency, formatDateTime } from '../utils/format';
import { originClass, originLabel } from '../utils/origin';
import {
  orderStatusClass,
  orderStatusLabel,
  orderTypeLabel,
  paymentMethodLabel,
  paymentStatusLabel,
} from '../utils/orderStatus';
import OrderDetailDrawer from '../components/admin/OrderDetailDrawer.vue';
import SearchSelect from '../components/admin/SearchSelect.vue';

const route = useRoute();
const toast = useToast();
const orders = ref([]);
const meta = ref({ current_page: 1, last_page: 1 });
const summary = ref({ paid_orders: 0, revenue: 0, avg_ticket: 0, refunded: 0 });
const loading = ref(true);
const exporting = ref(false);
const drawerOpen = ref(false);
const selectedOrderId = ref(null);
const sellerOptions = ref([]);
const sellersLoading = ref(false);
const filters = reactive({
  search: route.query.search ? String(route.query.search) : '',
  status: route.query.status ? String(route.query.status) : 'paid',
  seller_id: route.query.seller_id ? String(route.query.seller_id) : '',
  source: route.query.source ? String(route.query.source) : '',
  date_from: route.query.date_from ? String(route.query.date_from) : '',
  date_to: route.query.date_to ? String(route.query.date_to) : '',
});

const hasFilters = computed(() => Boolean(
  filters.search
  || filters.seller_id
  || filters.source
  || filters.date_from
  || filters.date_to
  || (filters.status && filters.status !== 'paid'),
));

onMounted(() => {
  loadSellers('');
  load();
});

async function loadSellers(search = '') {
  sellersLoading.value = true;

  try {
    const { data } = await api.get('/admin/sellers', {
      params: { search: search || undefined, per_page: 30 },
    });
    sellerOptions.value = (data.data ?? []).map((seller) => ({
      value: seller.id,
      label: seller.name,
      sub: seller.code,
    }));
  } catch (e) {
    sellerOptions.value = [];
  } finally {
    sellersLoading.value = false;
  }
}

function summaryParams() {
  return {
    search: filters.search || undefined,
    seller_id: filters.seller_id || undefined,
    source: filters.source || undefined,
    date_from: filters.date_from || undefined,
    date_to: filters.date_to || undefined,
  };
}

function listParams() {
  return {
    ...summaryParams(),
    status: filters.status || undefined,
  };
}

async function load() {
  loading.value = true;

  try {
    const [{ data: list }, { data: totals }] = await Promise.all([
      api.get('/admin/orders', {
        params: { ...listParams(), page: meta.value.current_page, per_page: 20 },
      }),
      api.get('/admin/orders/summary', { params: summaryParams() }),
    ]);

    orders.value = list.data ?? [];
    meta.value = list.meta ?? meta.value;
    summary.value = {
      paid_orders: 0,
      revenue: 0,
      avg_ticket: 0,
      refunded: 0,
      ...(totals.data || {}),
    };
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
  filters.status = 'paid';
  filters.seller_id = '';
  filters.source = '';
  filters.date_from = '';
  filters.date_to = '';
  reload();
}

async function exportFile() {
  exporting.value = true;

  try {
    const { data } = await api.get('/admin/orders/export', {
      params: listParams(),
      responseType: 'blob',
      headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
    });

    const url = URL.createObjectURL(new Blob([data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `vendas-${new Date().toISOString().slice(0, 10)}.xlsx`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  } catch (e) {
    toast.error(e?.message || 'Não foi possível exportar as vendas.');
  } finally {
    exporting.value = false;
  }
}

function openOrder(order) {
  selectedOrderId.value = order.id;
  drawerOpen.value = true;
}

function statusLabel(status) {
  return orderStatusLabel(status);
}
</script>

<style scoped>
.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.sales-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:18px;}
.sales-kpis article{display:flex;flex-direction:column;gap:6px;padding:18px;border:1px solid var(--border);border-radius:16px;background:var(--bg-card);}
.sales-kpis span{color:var(--text-muted);font-size:10.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;}
.sales-kpis strong{font-size:24px;letter-spacing:-.03em;}
.sales-filters{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:14px;margin-bottom:18px;border:1px solid var(--border);border-radius:14px;background:var(--bg-card);}
.sales-filters .form-control{min-width:150px;flex:1 1 160px;}
.sales-filters input[type="search"]{flex-basis:260px;}
.sales-filters .btn{flex-shrink:0;}
</style>
