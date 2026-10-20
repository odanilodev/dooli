<template>
  <main class="content public-content">
    <header class="subscriptions-head">
      <div>
        <p class="public-eyebrow">Área do aluno</p>
        <h1>Minhas assinaturas</h1>
        <p v-if="subscriptions.length">{{ subscriptions.length }} assinatura(s) nesta loja.</p>
      </div>
      <router-link :to="{ name: 'public-loja', params: { companySlug } }" class="btn btn-outline btn-sm">Voltar para a loja</router-link>
    </header>

    <div v-if="loading" class="public-empty">
      <strong>Carregando assinaturas...</strong>
    </div>

    <div v-else-if="error" class="public-empty">
      <svg class="icon" width="38" height="38" viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>
      <strong>Não foi possível carregar</strong>
      <span>{{ error }}</span>
      <button class="btn btn-outline btn-sm" type="button" @click="load">Tentar novamente</button>
    </div>

    <div v-else-if="!subscriptions.length" class="public-empty">
      <svg class="icon" width="38" height="38" viewBox="0 0 24 24"><path d="M20 7h-6a4 4 0 0 0 0 8h2a4 4 0 0 1 0 8H9"/><path d="M16 3l4 4-4 4M8 21l-4-4 4-4"/></svg>
      <strong>Nenhuma assinatura ativa</strong>
      <span>Assine um produto da loja para começar a estudar.</span>
      <router-link :to="{ name: 'public-loja', params: { companySlug } }" class="btn btn-primary btn-sm">Ir para a loja</router-link>
    </div>

    <div v-else class="subscription-grid">
      <article v-for="(subscription, index) in subscriptions" :key="subscription.id" class="subscription-card" :class="subscription.status">
        <div class="subscription-media" :style="{ background: itemGrad(index) }">
          <img
            v-if="subscription.store_product?.image_url || subscription.store_product?.image"
            :src="subscription.store_product.image_url || subscription.store_product.image"
            :alt="subscription.store_product?.title"
          >
          <svg v-else class="icon" width="30" height="30" viewBox="0 0 24 24"><path :d="itemIcon(index)" /></svg>
          <span class="status-pill subscription-status" :class="subscription.status">{{ statusLabel(subscription.status) }}</span>
        </div>

        <div class="subscription-body">
          <div class="subscription-title">
            <p class="public-eyebrow">Assinatura</p>
            <h2>{{ subscription.store_product?.title || 'Assinatura' }}</h2>
            <span>{{ subscription.store_product?.access_label || 'Acesso mensal' }}</span>
          </div>

          <div class="subscription-price">
            <strong>{{ formatCurrency(subscription.amount) }}</strong>
            <span>/mês</span>
          </div>

          <p class="subscription-note">{{ billingNote(subscription) }}</p>

          <button v-if="canCancel(subscription)" class="btn btn-outline btn-block btn-sm" type="button" @click="cancel(subscription)">
            Cancelar assinatura
          </button>
        </div>
      </article>
    </div>
  </main>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api/http';
import { paletteAt } from '../data/palette';
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
  loading.value = true;
  error.value = null;

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

function billingNote(subscription) {
  const nextDate = subscription.next_billing_at || subscription.current_period_end;

  if (subscription.status === 'pending') {
    return 'Assinatura aguardando autorização do pagamento.';
  }

  if (subscription.status === 'past_due') {
    return 'Não conseguimos confirmar a última cobrança. Verifique seu meio de pagamento.';
  }

  if (['canceled', 'expired'].includes(subscription.status)) {
    return nextDate
      ? `Acesso disponível até ${formatDate(nextDate)}.`
      : 'Esta assinatura não está mais ativa.';
  }

  return nextDate
    ? `Próxima cobrança em ${formatDate(nextDate)}.`
    : 'Assinatura ativa.';
}

function canCancel(subscription) {
  return !['canceled', 'expired'].includes(subscription.status);
}

function itemGrad(index) {
  return paletteAt(index).grad;
}

function itemIcon(index) {
  return paletteAt(index).icon;
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
.subscriptions-head{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;flex-wrap:wrap;}
.subscriptions-head h1{margin-top:4px;font-size:clamp(26px,3.3vw,36px);font-weight:800;letter-spacing:-.02em;}
.subscriptions-head p:last-child{margin-top:5px;color:var(--text-muted);font-size:12.5px;}
.subscription-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;}
.subscription-card{
  display:flex;flex-direction:column;overflow:hidden;
  border:1px solid var(--border);border-radius:18px;background:var(--bg-card);box-shadow:var(--shadow-sm);
}
.subscription-card.active{border-color:color-mix(in srgb,var(--accent) 30%,var(--border));}
.subscription-media{
  position:relative;display:flex;align-items:center;justify-content:center;
  aspect-ratio:16/9;overflow:hidden;color:#fff;
}
.subscription-media::after{content:'';position:absolute;inset:0;background:linear-gradient(180deg,transparent 45%,rgba(4,10,18,.36));}
.subscription-media img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
.subscription-media > .icon{position:relative;z-index:1;opacity:.65;}
.subscription-status{position:absolute;top:12px;right:12px;z-index:2;background:rgba(5,14,23,.74);color:#fff;backdrop-filter:blur(5px);}
.subscription-status.active{background:rgba(34,197,94,.92);color:#fff;}
.subscription-status.pending{background:rgba(234,179,8,.92);color:#2a2104;}
.subscription-status.past_due{background:rgba(255,107,107,.92);color:#fff;}
.subscription-status.canceled,.subscription-status.expired{background:rgba(148,163,184,.9);color:#fff;}
.subscription-body{display:flex;flex:1;flex-direction:column;gap:13px;padding:17px;}
.subscription-title{display:flex;flex-direction:column;gap:3px;}
.subscription-title h2{margin:2px 0 0;font-size:17px;font-weight:800;line-height:1.3;}
.subscription-title > span{color:var(--text-muted);font-size:11px;}
.subscription-price{display:flex;align-items:baseline;gap:3px;}
.subscription-price strong{font-size:23px;color:var(--accent);letter-spacing:-.02em;}
.subscription-price span{color:var(--text-muted);font-size:10.5px;font-weight:700;}
.subscription-note{margin:0;color:var(--text-secondary);font-size:11.5px;line-height:1.5;}
.subscription-body .btn{margin-top:auto;}
@media(max-width:560px){
  .subscription-grid{grid-template-columns:1fr;}
}
</style>
