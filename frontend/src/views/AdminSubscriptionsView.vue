<template>
  <main class="content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Assinaturas</h1>
        <p class="page-subtitle">Cobranças recorrentes da empresa.</p>
      </div>
    </div>

    <div v-if="loading" class="empty-state"><strong>Carregando...</strong></div>
    <div v-else-if="!subscriptions.length" class="empty-state"><strong>Nenhuma assinatura</strong></div>
    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>Aluno</th><th>Produto</th><th>Valor</th><th>Status</th><th>Próxima cobrança</th><th></th></tr></thead>
        <tbody>
          <tr v-for="subscription in subscriptions" :key="subscription.id">
            <td>{{ subscription.user?.name || 'Aluno' }}</td>
            <td>{{ subscription.store_product?.title || 'Produto' }}</td>
            <td>{{ formatCurrency(subscription.amount) }}/mês</td>
            <td><span class="status-pill" :class="subscription.status">{{ statusLabel(subscription.status) }}</span></td>
            <td class="admin-muted">{{ subscription.next_billing_at ? formatDate(subscription.next_billing_at) : '-' }}</td>
            <td class="admin-actions">
              <button v-if="!['canceled', 'expired'].includes(subscription.status)" class="btn btn-outline btn-sm" @click="cancel(subscription)">Cancelar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </main>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';
import { formatCurrency, formatDate } from '../utils/format';

const toast = useToast();
const subscriptions = ref([]);
const loading = ref(true);

onMounted(load);

async function load() {
  loading.value = true;

  try {
    const { data } = await api.get('/admin/subscriptions', { params: { per_page: 50 } });
    subscriptions.value = data.data ?? [];
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar as assinaturas.');
  } finally {
    loading.value = false;
  }
}

async function cancel(subscription) {
  if (!window.confirm('Cancelar esta assinatura? O aluno mantém o acesso até o fim do período pago.')) {
    return;
  }

  try {
    await api.post(`/admin/subscriptions/${subscription.id}/cancel`);
    toast.success('Assinatura cancelada.');
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível cancelar.');
  }
}

function statusLabel(status) {
  return {
    pending: 'Pendente',
    active: 'Ativa',
    past_due: 'Atrasada',
    canceled: 'Cancelada',
    expired: 'Expirada',
  }[status] || status;
}
</script>
