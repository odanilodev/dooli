<template>
  <main class="content public-content">
    <nav class="public-breadcrumb" aria-label="Você está aqui">
      <router-link :to="publicRoute('public-loja')">Loja</router-link>
      <span>/</span>
      <span class="current">Minhas compras</span>
    </nav>

    <header class="purchases-head">
      <div>
        <p class="public-eyebrow">Histórico</p>
        <h1>Minhas compras</h1>
        <p>Acompanhe pedidos, pagamentos e estornos.</p>
      </div>
      <router-link :to="publicRoute('public-loja')" class="btn btn-outline btn-sm">Continuar comprando</router-link>
    </header>

    <section class="purchases-filters" aria-label="Filtros de pedidos">
      <label>
        <span>Status</span>
        <select v-model="filters.status" class="form-control" @change="reload">
          <option value="">Todos</option>
          <option value="pending">Aguardando pagamento</option>
          <option value="paid">Pago</option>
          <option value="canceled">Cancelado</option>
          <option value="refunded">Estornado</option>
        </select>
      </label>
      <label>
        <span>De</span>
        <input v-model="filters.date_from" type="date" class="form-control" @change="reload">
      </label>
      <label>
        <span>Até</span>
        <input v-model="filters.date_to" type="date" class="form-control" @change="reload">
      </label>
      <button v-if="hasFilters" class="btn btn-outline btn-sm" type="button" @click="clearFilters">Limpar</button>
    </section>

    <div v-if="loading" class="public-empty">
      <strong>Carregando pedidos...</strong>
    </div>

    <div v-else-if="error" class="public-empty">
      <strong>Não foi possível carregar suas compras</strong>
      <span>{{ error }}</span>
    </div>

    <div v-else-if="!orders.length" class="public-empty">
      <strong>Nenhuma compra encontrada</strong>
      <span>Seus pedidos aparecerão aqui após a compra.</span>
      <router-link :to="publicRoute('public-loja')" class="btn btn-primary btn-sm">Explorar a loja</router-link>
    </div>

    <section v-else class="purchases-list">
      <article v-for="order in orders" :key="order.id" class="purchase-card">
        <div class="purchase-main">
          <div class="purchase-top">
            <strong>Pedido #{{ order.id }}</strong>
            <span class="status-pill" :class="orderStatusClass(order.status)">{{ orderStatusLabel(order.status) }}</span>
          </div>
          <p class="purchase-date">{{ formatDateTime(order.created_at) }}</p>
          <div class="purchase-items">
            <span v-for="item in order.items" :key="item.id" class="purchase-item">
              {{ item.product_title || item.store_product?.title || 'Produto' }}
              <small v-if="item.quantity > 1">x{{ item.quantity }}</small>
            </span>
          </div>
          <div class="purchase-payment">
            <span v-if="order.latest_payment">
              {{ paymentMethodLabel(order.latest_payment.method) }}
              · {{ paymentStatusLabel(order.latest_payment.status) }}
            </span>
            <span v-else>Pagamento não iniciado</span>
            <span v-if="order.latest_payment?.paid_at">Pago em {{ formatDateTime(order.latest_payment.paid_at) }}</span>
          </div>
        </div>
        <div class="purchase-side">
          <span class="purchase-type">{{ orderTypeLabel(order.type) }}</span>
          <strong>{{ formatCurrency(order.total) }}</strong>
          <router-link :to="publicRoute('public-pedido', { orderId: order.id })" class="btn btn-outline btn-sm">
            Ver detalhes
          </router-link>
        </div>
      </article>
    </section>

    <div v-if="meta.last_page > 1" class="purchases-pager">
      <button class="btn btn-outline btn-sm" type="button" :disabled="meta.current_page <= 1" @click="go(meta.current_page - 1)">Anterior</button>
      <span>Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      <button class="btn btn-outline btn-sm" type="button" :disabled="meta.current_page >= meta.last_page" @click="go(meta.current_page + 1)">Próxima</button>
    </div>
  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';
import { useTenant } from '../composables/useTenant';
import { formatCurrency, formatDateTime } from '../utils/format';
import {
  orderStatusClass,
  orderStatusLabel,
  orderTypeLabel,
  paymentMethodLabel,
  paymentStatusLabel,
} from '../utils/orderStatus';

const { publicRoute } = useTenant();
const orders = ref([]);
const loading = ref(true);
const error = ref(null);
const meta = ref({ current_page: 1, last_page: 1 });
const filters = reactive({ status: '', date_from: '', date_to: '' });

const hasFilters = computed(() => Boolean(filters.status || filters.date_from || filters.date_to));

onMounted(reload);

async function reload() {
  meta.value.current_page = 1;
  await load();
}

async function load() {
  loading.value = true;
  error.value = null;

  try {
    const { data } = await api.get('/me/orders', {
      params: {
        page: meta.value.current_page,
        per_page: 10,
        status: filters.status || undefined,
        date_from: filters.date_from || undefined,
        date_to: filters.date_to || undefined,
      },
    });

    orders.value = data.data ?? [];
    meta.value = data.meta ?? meta.value;
  } catch (e) {
    error.value = e?.message || 'Tente novamente em instantes.';
    orders.value = [];
  } finally {
    loading.value = false;
  }
}

function go(page) {
  meta.value.current_page = page;
  load();
}

function clearFilters() {
  filters.status = '';
  filters.date_from = '';
  filters.date_to = '';
  reload();
}
</script>

<style scoped>
.purchases-head{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap;margin:26px 0 22px;}
.purchases-head h1{margin:4px 0 6px;font-size:clamp(34px,4vw,48px);letter-spacing:-.04em;}
.purchases-head p{color:var(--text-muted);font-size:13.5px;}
.purchases-filters{display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;padding:16px;margin-bottom:22px;border:1px solid var(--border);border-radius:16px;background:var(--bg-card);}
.purchases-filters label{display:flex;flex-direction:column;gap:6px;min-width:150px;}
.purchases-filters label span{color:var(--text-muted);font-size:10.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;}
.purchases-list{display:flex;flex-direction:column;gap:12px;}
.purchase-card{display:flex;align-items:stretch;justify-content:space-between;gap:24px;padding:20px;border:1px solid var(--border);border-radius:18px;background:var(--bg-card);}
.purchase-main{display:flex;flex-direction:column;gap:7px;min-width:0;flex:1;}
.purchase-top{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.purchase-top strong{font-size:15px;}
.purchase-date{color:var(--text-muted);font-size:11.5px;}
.purchase-items{display:flex;flex-wrap:wrap;gap:7px;}
.purchase-item{padding:4px 9px;border-radius:99px;background:var(--bg-card-alt);font-size:12px;color:var(--text-secondary);}
.purchase-item small{margin-left:4px;color:var(--text-muted);}
.purchase-payment{display:flex;flex-wrap:wrap;gap:4px 14px;color:var(--text-muted);font-size:11.5px;}
.purchase-side{display:flex;flex-direction:column;align-items:flex-end;justify-content:space-between;gap:12px;flex-shrink:0;min-width:130px;}
.purchase-type{color:var(--text-muted);font-size:10.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;}
.purchase-side > strong{font-size:22px;letter-spacing:-.03em;color:var(--accent);}
.purchases-pager{display:flex;align-items:center;justify-content:center;gap:14px;margin-top:20px;color:var(--text-muted);font-size:12px;}
@media(max-width:640px){
  .purchase-card{flex-direction:column;gap:14px;}
  .purchase-side{flex-direction:row;align-items:center;min-width:0;}
  .purchase-type{display:none;}
  .purchases-filters label{min-width:0;flex:1 1 140px;}
}
</style>
