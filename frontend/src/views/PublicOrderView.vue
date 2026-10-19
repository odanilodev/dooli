<template>
  <main class="content public-content">
    <router-link :to="{ name: 'public-loja', params: { companySlug } }" class="section-link">← Voltar para a loja</router-link>

    <div v-if="loading" class="empty-state"><strong>Carregando pedido...</strong></div>
    <div v-else-if="error" class="empty-state"><strong>Pedido não encontrado</strong><span>{{ error }}</span></div>

    <section v-else-if="order" class="checkout-card order-card">
      <p class="public-eyebrow">Pedido #{{ order.id }}</p>
      <h1 class="page-title">{{ statusLabel(order.status) }}</h1>
      <p class="page-subtitle">Total: {{ formatCurrency(order.total) }}</p>

      <div v-for="item in order.items" :key="item.id" class="order-item">
        <span>{{ item.store_product?.title || 'Produto' }}</span>
        <strong>{{ formatCurrency(item.total) }}</strong>
      </div>

      <p class="checkout-note" v-if="order.status === 'paid'">Pagamento confirmado. Seu acesso já está disponível na área de cursos.</p>
      <p class="checkout-note" v-else>Assim que o pagamento for confirmado, seu acesso será liberado automaticamente.</p>

      <router-link v-if="order.status === 'paid'" to="/" class="btn btn-primary">Ir para meus cursos</router-link>
    </section>
  </main>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api/http';
import { formatCurrency } from '../utils/format';

const route = useRoute();
const order = ref(null);
const loading = ref(true);
const error = ref(null);
let timer = null;

const companySlug = computed(() => String(route.params.companySlug || ''));
const orderId = computed(() => String(route.params.orderId || ''));

load();

async function load() {
  try {
    const { data } = await api.get(`/me/orders/${orderId.value}`);
    order.value = data.data;
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar o pedido.';
  } finally {
    loading.value = false;
  }
}

function statusLabel(status) {
  return {
    pending: 'Aguardando pagamento',
    paid: 'Pagamento aprovado',
    canceled: 'Pedido cancelado',
    refunded: 'Pagamento estornado',
  }[status] || 'Pedido';
}

timer = setInterval(() => {
  if (order.value?.status !== 'paid') {
    load();
  }
}, 8000);

onBeforeUnmount(() => clearInterval(timer));
</script>

<style scoped>
.public-content{max-width:820px;margin:0 auto;padding:34px clamp(18px,5vw,64px) 70px;}
.order-card{margin-top:24px;}
.order-item{display:flex;justify-content:space-between;gap:16px;padding:14px 0;border-bottom:1px solid var(--border);font-size:14px;}
</style>
