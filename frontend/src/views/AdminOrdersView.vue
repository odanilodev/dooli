<template>
  <main class="content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Pedidos</h1>
        <p class="page-subtitle">Compras e cobranças da empresa.</p>
      </div>
      <router-link :to="{ name: 'admin-pagamentos' }" class="btn btn-outline btn-sm">Pagamentos</router-link>
    </div>

    <form class="orders-filters" @submit.prevent="reload">
      <input v-model="filters.search" class="form-control" type="search" placeholder="Buscar por aluno, e-mail ou #pedido">
      <select v-model="filters.status" class="form-control">
        <option value="">Todos os status</option>
        <option value="pending">Pendente</option>
        <option value="paid">Pago</option>
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
      <input v-model="filters.date_from" class="form-control" type="date" aria-label="Data inicial">
      <input v-model="filters.date_to" class="form-control" type="date" aria-label="Data final">
      <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
      <button v-if="hasFilters" class="btn btn-outline btn-sm" type="button" @click="clearFilters">Limpar</button>
    </form>

    <div v-if="loading" class="empty-state"><strong>Carregando...</strong></div>
    <div v-else-if="!orders.length" class="empty-state"><strong>Nenhum pedido</strong></div>
    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>#</th><th>Cliente</th><th>Tipo</th><th>Origem</th><th>Vendedor</th><th>Valor</th><th>Pagamento</th><th>Status</th><th>Data</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="order in orders" :key="order.id">
            <td>#{{ order.id }}</td>
            <td>
              {{ order.user?.name || 'Aluno' }}
              <span v-if="order.user?.email" class="admin-sub">{{ order.user.email }}</span>
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
const orders = ref([]);
const meta = ref({ current_page: 1, last_page: 1 });
const loading = ref(true);
const drawerOpen = ref(false);
const selectedOrderId = ref(null);
const sellerOptions = ref([]);
const sellersLoading = ref(false);
const filters = reactive({
  search: route.query.search ? String(route.query.search) : '',
  status: '',
  seller_id: '',
  date_from: '',
  date_to: '',
});

const hasFilters = computed(() => Boolean(
  filters.search || filters.status || filters.seller_id || filters.date_from || filters.date_to,
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

async function load() {
  loading.value = true;

  try {
    const { data } = await api.get('/admin/orders', {
      params: {
        page: meta.value.current_page,
        per_page: 20,
        search: filters.search || undefined,
        status: filters.status || undefined,
        seller_id: filters.seller_id || undefined,
        date_from: filters.date_from || undefined,
        date_to: filters.date_to || undefined,
      },
    });
    orders.value = data.data ?? [];
    meta.value = data.meta ?? meta.value;
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
  filters.seller_id = '';
  filters.date_from = '';
  filters.date_to = '';
  reload();
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
.orders-filters{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:14px;margin-bottom:18px;border:1px solid var(--border);border-radius:14px;background:var(--bg-card);}
.orders-filters .form-control{min-width:150px;flex:1 1 160px;}
.orders-filters input[type="search"]{flex-basis:260px;}
.orders-filters .btn{flex-shrink:0;}
</style>
