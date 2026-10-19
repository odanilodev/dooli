<template>
  <main class="content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Pedidos</h1>
        <p class="page-subtitle">Compras e cobranças da empresa.</p>
      </div>
    </div>

    <div v-if="loading" class="empty-state"><strong>Carregando...</strong></div>
    <div v-else-if="!orders.length" class="empty-state"><strong>Nenhum pedido</strong></div>
    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>#</th><th>Cliente</th><th>Tipo</th><th>Valor</th><th>Status</th><th>Data</th></tr></thead>
        <tbody>
          <tr v-for="order in orders" :key="order.id">
            <td>#{{ order.id }}</td>
            <td>{{ order.user?.name || 'Aluno' }}</td>
            <td class="admin-muted">{{ order.subscription_id ? 'Assinatura' : 'Compra única' }}</td>
            <td>{{ formatCurrency(order.total) }}</td>
            <td><span class="status-pill" :class="order.status">{{ statusLabel(order.status) }}</span></td>
            <td class="admin-muted">{{ formatDateTime(order.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="admin-pager">
      <button class="btn btn-outline btn-sm" :disabled="meta.current_page <= 1" @click="go(meta.current_page - 1)">Anterior</button>
      <span class="admin-muted">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      <button class="btn btn-outline btn-sm" :disabled="meta.current_page >= meta.last_page" @click="go(meta.current_page + 1)">Próxima</button>
    </div>
  </main>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api/http';
import { formatCurrency, formatDateTime } from '../utils/format';

const orders = ref([]);
const meta = ref({ current_page: 1, last_page: 1 });
const loading = ref(true);

onMounted(load);

async function load() {
  loading.value = true;

  try {
    const { data } = await api.get('/admin/orders', { params: { page: meta.value.current_page, per_page: 20 } });
    orders.value = data.data ?? [];
    meta.value = data.meta ?? meta.value;
  } finally {
    loading.value = false;
  }
}

function go(page) {
  meta.value.current_page = page;
  load();
}

function statusLabel(status) {
  return {
    pending: 'Pendente',
    paid: 'Pago',
    canceled: 'Cancelado',
    refunded: 'Estornado',
  }[status] || status;
}
</script>
