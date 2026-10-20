<template>
  <main class="content public-content">
    <nav class="public-breadcrumb" aria-label="Você está aqui">
      <router-link :to="{ name: 'public-loja', params: { companySlug } }">Loja</router-link>
      <span>/</span>
      <span class="current">Pedido</span>
    </nav>

    <div v-if="loading" class="public-empty">
      <strong>Carregando pedido...</strong>
    </div>

    <div v-else-if="error" class="public-empty">
      <svg class="icon" width="38" height="38" viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>
      <strong>Pedido não encontrado</strong>
      <span>{{ error }}</span>
      <router-link :to="{ name: 'public-loja', params: { companySlug } }" class="btn btn-outline btn-sm">Voltar para a loja</router-link>
    </div>

    <section v-else-if="order" class="order-layout">
      <div class="order-card">
        <div class="order-status-head">
          <span class="order-status-icon" :class="order.status">
            <svg v-if="order.status === 'paid'" class="icon" width="22" height="22" viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/></svg>
            <svg v-else-if="['canceled', 'refunded'].includes(order.status)" class="icon" width="22" height="22" viewBox="0 0 24 24"><path d="M7 7l10 10M17 7L7 17"/></svg>
            <svg v-else class="icon" width="22" height="22" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
          </span>

          <div class="order-status-copy">
            <p class="public-eyebrow">Pedido #{{ order.id }}</p>
            <h1>{{ statusLabel(order.status) }}</h1>
            <p>{{ statusDescription(order.status) }}</p>
          </div>

          <span class="status-pill order-pill" :class="order.status">{{ statusLabel(order.status) }}</span>
        </div>

        <div class="order-section">
          <div class="order-section-head">
            <h2>Itens do pedido</h2>
            <span v-if="order.created_at">Criado em {{ formatDate(order.created_at) }}</span>
          </div>

          <div class="order-items">
            <div v-for="(item, index) in order.items" :key="item.id" class="order-item">
              <div class="order-item-media" :style="{ background: itemGrad(index) }">
                <img v-if="item.store_product?.image_url || item.store_product?.image" :src="item.store_product.image_url || item.store_product.image" :alt="item.store_product?.title">
                <svg v-else class="icon" width="20" height="20" viewBox="0 0 24 24"><path :d="itemIcon(index)" /></svg>
              </div>
              <div class="order-item-copy">
                <strong>{{ item.store_product?.title || 'Produto' }}</strong>
                <span>{{ item.store_product?.access_label || 'Conteúdo digital' }}</span>
              </div>
              <strong class="order-item-price">{{ formatCurrency(item.total) }}</strong>
            </div>
          </div>
        </div>

        <div class="order-totals">
          <div class="order-total-line">
            <span>Subtotal</span>
            <strong>{{ formatCurrency(order.subtotal) }}</strong>
          </div>
          <div v-if="Number(order.discount) > 0" class="order-total-line discount">
            <span>Desconto</span>
            <strong>- {{ formatCurrency(order.discount) }}</strong>
          </div>
          <div class="order-total-line total">
            <span>Total</span>
            <strong>{{ formatCurrency(order.total) }}</strong>
          </div>
        </div>

        <p v-if="order.status === 'paid'" class="order-note success">
          Pagamento confirmado. Seu acesso já está disponível na área de cursos.
        </p>
        <p v-else-if="['canceled', 'refunded'].includes(order.status)" class="order-note">
          Este pedido não está mais ativo. Se precisar de ajuda, fale com a empresa.
        </p>
        <p v-else class="order-note">
          Assim que o pagamento for confirmado, seu acesso será liberado automaticamente.
        </p>

        <router-link v-if="order.status === 'paid'" to="/" class="btn btn-primary order-cta">
          Ir para meus cursos
          <svg class="icon" width="16" height="16" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </router-link>
      </div>

      <aside class="order-aside">
        <p class="public-eyebrow">Acompanhe</p>
        <h2>O que acontece agora?</h2>

        <ol class="order-steps">
          <li class="done">
            <span class="order-step-dot"></span>
            <div>
              <strong>Pedido criado</strong>
              <span>Recebemos os dados da sua compra.</span>
            </div>
          </li>
          <li :class="{ done: order.status === 'paid', current: order.status === 'pending', danger: ['canceled', 'refunded'].includes(order.status) }">
            <span class="order-step-dot"></span>
            <div>
              <strong>Confirmação do pagamento</strong>
              <span v-if="order.status === 'pending'">Estamos aguardando a confirmação do gateway.</span>
              <span v-else-if="order.status === 'paid'">Pagamento aprovado com sucesso.</span>
              <span v-else>O pagamento não foi concluído.</span>
            </div>
          </li>
          <li :class="{ done: order.status === 'paid' }">
            <span class="order-step-dot"></span>
            <div>
              <strong>Liberação do acesso</strong>
              <span>O conteúdo aparece na sua área de cursos.</span>
            </div>
          </li>
        </ol>

        <p v-if="order.status === 'pending'" class="order-aside-note">
          Esta página atualiza o status automaticamente.
        </p>
      </aside>
    </section>
  </main>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api/http';
import { paletteAt } from '../data/palette';
import { formatCurrency, formatDate } from '../utils/format';

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

function statusDescription(status) {
  return {
    pending: 'Falta pouco. Estamos aguardando a confirmação do pagamento.',
    paid: 'Tudo certo! Seu acesso já foi liberado.',
    canceled: 'O pagamento não foi concluído e o pedido foi encerrado.',
    refunded: 'O valor foi devolvido e o pedido foi encerrado.',
  }[status] || 'Acompanhe os detalhes do seu pedido.';
}

function itemGrad(index) {
  return paletteAt(index).grad;
}

function itemIcon(index) {
  return paletteAt(index).icon;
}

timer = setInterval(() => {
  if (order.value?.status !== 'paid') {
    load();
  }
}, 8000);

onBeforeUnmount(() => clearInterval(timer));
</script>

<style scoped>
.order-layout{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(270px,.7fr);gap:24px;align-items:start;}
.order-card{
  display:flex;flex-direction:column;gap:22px;padding:clamp(20px,3vw,28px);
  border:1px solid var(--border);border-radius:20px;background:var(--bg-card);box-shadow:var(--shadow-sm);
}
.order-status-head{display:flex;align-items:flex-start;gap:14px;}
.order-status-icon{
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
  width:47px;height:47px;border-radius:14px;background:var(--accent-soft);color:var(--accent);
}
.order-status-icon.pending{background:rgba(234,179,8,.12);color:#d9a514;}
.order-status-icon.canceled,.order-status-icon.refunded{background:rgba(255,107,107,.12);color:#ff6b6b;}
.order-status-copy{display:flex;flex-direction:column;gap:4px;min-width:0;}
.order-status-copy h1{font-size:clamp(23px,3vw,30px);font-weight:800;line-height:1.15;letter-spacing:-.02em;}
.order-status-copy p:last-child{color:var(--text-secondary);font-size:12.5px;line-height:1.5;}
.order-pill{margin-left:auto;flex-shrink:0;background:var(--bg-card-alt);color:var(--text-secondary);}
.order-pill.paid{background:rgba(34,197,94,.14);color:#22c55e;}
.order-pill.pending{background:rgba(234,179,8,.12);color:#d9a514;}
.order-pill.canceled,.order-pill.refunded{background:rgba(255,107,107,.12);color:#ff6b6b;}
.order-section{display:flex;flex-direction:column;gap:11px;}
.order-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.order-section-head h2{font-size:15px;font-weight:800;}
.order-section-head span{color:var(--text-muted);font-size:10.5px;}
.order-items{display:flex;flex-direction:column;border:1px solid var(--border);border-radius:14px;overflow:hidden;}
.order-item{display:flex;align-items:center;gap:12px;padding:13px;background:var(--bg-card-alt);}
.order-item + .order-item{border-top:1px solid var(--border);}
.order-item-media{
  position:relative;display:flex;align-items:center;justify-content:center;flex-shrink:0;
  width:48px;height:44px;border-radius:10px;overflow:hidden;color:#fff;
}
.order-item-media img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
.order-item-copy{display:flex;flex-direction:column;gap:3px;min-width:0;flex:1;}
.order-item-copy strong{font-size:12.5px;line-height:1.35;}
.order-item-copy span{color:var(--text-muted);font-size:10.5px;}
.order-item-price{font-size:13px;white-space:nowrap;}
.order-totals{display:flex;flex-direction:column;gap:8px;padding-top:4px;}
.order-total-line{display:flex;align-items:center;justify-content:space-between;gap:16px;color:var(--text-secondary);font-size:12px;}
.order-total-line strong{color:var(--text-primary);font-size:12.5px;}
.order-total-line.discount strong{color:#22c55e;}
.order-total-line.total{padding-top:12px;border-top:1px solid var(--border);font-size:13px;font-weight:800;}
.order-total-line.total strong{font-size:21px;color:var(--accent);}
.order-note{
  padding:12px 13px;border:1px solid var(--border);border-radius:11px;
  background:var(--bg-card-alt);color:var(--text-secondary);font-size:11.5px;line-height:1.5;
}
.order-note.success{border-color:rgba(34,197,94,.28);background:rgba(34,197,94,.07);color:#22c55e;}
.order-cta{align-self:flex-start;}
.order-aside{
  position:sticky;top:102px;display:flex;flex-direction:column;gap:10px;padding:22px;
  border:1px solid var(--border);border-radius:20px;background:var(--bg-card);box-shadow:var(--shadow-sm);
}
.order-aside h2{margin:2px 0 6px;font-size:18px;font-weight:800;}
.order-steps{display:flex;flex-direction:column;gap:0;margin:0;padding:0;list-style:none;}
.order-steps li{position:relative;display:flex;gap:11px;padding-bottom:19px;}
.order-steps li:last-child{padding-bottom:0;}
.order-steps li::before{content:'';position:absolute;left:6px;top:14px;bottom:0;width:1px;background:var(--border);}
.order-steps li:last-child::before{display:none;}
.order-step-dot{
  position:relative;z-index:1;width:13px;height:13px;border-radius:50%;flex-shrink:0;
  margin-top:2px;border:2px solid var(--border);background:var(--bg-card);
}
.order-steps li.done .order-step-dot{border-color:var(--accent);background:var(--accent);}
.order-steps li.current .order-step-dot{border-color:#d9a514;background:#d9a514;box-shadow:0 0 0 4px rgba(234,179,8,.14);}
.order-steps li.danger .order-step-dot{border-color:#ff6b6b;background:#ff6b6b;}
.order-steps li > div{display:flex;flex-direction:column;gap:3px;}
.order-steps strong{font-size:12px;}
.order-steps span:not(.order-step-dot){color:var(--text-muted);font-size:10.5px;line-height:1.4;}
.order-aside-note{margin-top:2px;color:var(--text-muted);font-size:10.5px;line-height:1.45;}
@media(max-width:900px){
  .order-layout{grid-template-columns:1fr;}
  .order-aside{position:relative;top:0;}
}
@media(max-width:560px){
  .order-status-head{flex-wrap:wrap;}
  .order-pill{margin-left:61px;}
  .order-item-price{font-size:12px;}
}
</style>
