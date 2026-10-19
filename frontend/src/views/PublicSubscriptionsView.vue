<template>
  <main class="content public-content">
    <div class="page-header">
      <div>
        <p class="public-eyebrow">Área do aluno</p>
        <h1 class="page-title">Minhas assinaturas</h1>
      </div>
    </div>

    <div v-if="loading" class="empty-state"><strong>Carregando assinaturas...</strong></div>
    <div v-else-if="error" class="empty-state"><strong>Não foi possível carregar</strong><span>{{ error }}</span></div>
    <div v-else-if="!subscriptions.length" class="empty-state">
      <strong>Nenhuma assinatura ativa</strong>
      <span>Assine um produto da loja para começar.</span>
      <router-link :to="{ name: 'public-loja', params: { companySlug } }" class="btn btn-primary">Ir para a loja</router-link>
    </div>

    <div v-else class="subscription-grid">
      <article v-for="subscription in subscriptions" :key="subscription.id" class="subscription-card">
        <div class="subscription-head">
          <span class="status-pill" :class="subscription.status">{{ statusLabel(subscription.status) }}</span>
          <strong>{{ formatCurrency(subscription.amount) }}/mês</strong>
        </div>
        <h2>{{ subscription.store_product?.title || 'Assinatura' }}</h2>
        <p v-if="subscription.current_period_end">Próxima cobrança: {{ formatDate(subscription.current_period_end) }}</p>
        <p v-else>Assinatura aguardando autorização.</p>
        <button v-if="canCancel(subscription)" class="btn btn-outline btn-sm" type="button" @click="cancel(subscription)">
          Cancelar assinatura
        </button>
      </article>
    </div>
  </main>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';
import { formatCurrency, formatDate } from '../utils/format';

const route = useRoute();
const toast = useToast();
const companySlug = computed(() => String(route.params.companySlug || ''));
const subscriptions = ref([]);
const loading = ref(true);
const error = ref(null);

load();

async function load() {
  try {
    const { data } = await api.get('/me/subscriptions');
    subscriptions.value = data.data ?? [];
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar as assinaturas.';
  } finally {
    loading.value = false;
  }
}

function statusLabel(status) {
  return {
    pending: 'Pendente',
    active: 'Ativa',
    past_due: 'Pagamento atrasado',
    canceled: 'Cancelada',
    expired: 'Expirada',
  }[status] || status;
}

function canCancel(subscription) {
  return !['canceled', 'expired'].includes(subscription.status);
}

async function cancel(subscription) {
  if (!window.confirm('Cancelar esta assinatura? Você manterá o acesso até o fim do período pago.')) {
    return;
  }

  try {
    await api.post(`/me/subscriptions/${subscription.id}/cancel`);
    toast.success('Assinatura cancelada.');
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível cancelar.');
  }
}
</script>

<style scoped>
.public-content{max-width:1100px;margin:0 auto;padding:34px clamp(18px,5vw,64px) 70px;}
.subscription-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;}
.subscription-card{padding:20px;border:1px solid var(--border);border-radius:16px;background:var(--bg-card);}
.subscription-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;}
.subscription-card h2{margin:0 0 8px;font-size:17px;}
.subscription-card p{margin:0 0 14px;color:var(--text-muted);font-size:13px;}
</style>
