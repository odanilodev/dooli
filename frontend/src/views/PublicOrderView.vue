<template>
  <main class="content public-content">
    <nav class="public-breadcrumb" aria-label="Você está aqui">
      <router-link :to="publicRoute('public-loja')">Loja</router-link>
      <span>/</span>
      <router-link :to="publicRoute('public-compras')">Minhas compras</router-link>
      <span>/</span>
      <span class="current">Pedido #{{ orderId }}</span>
    </nav>

    <div v-if="loading" class="public-empty">
      <strong>Carregando pedido...</strong>
    </div>

    <div v-else-if="error" class="public-empty">
      <svg class="icon" width="38" height="38" viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>
      <strong>Pedido não encontrado</strong>
      <span>{{ error }}</span>
      <router-link :to="publicRoute('public-compras')" class="btn btn-outline btn-sm">Ver minhas compras</router-link>
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

          <span class="status-pill order-pill" :class="orderStatusClass(order.status)">{{ orderStatusLabel(order.status) }}</span>
        </div>

        <div class="order-section">
          <div class="order-section-head">
            <h2>Itens do pedido</h2>
            <span v-if="order.created_at">Pedido feito em {{ formatDateTime(order.created_at) }}</span>
          </div>

          <div class="order-items">
            <div v-for="(item, index) in order.items" :key="item.id" class="order-item">
              <div class="order-item-media" :style="{ background: itemGrad(index) }">
                <img v-if="item.store_product?.image_url || item.store_product?.image" :src="item.store_product.image_url || item.store_product.image" :alt="item.product_title || item.store_product?.title" loading="lazy" decoding="async">
                <svg v-else class="icon" width="20" height="20" viewBox="0 0 24 24"><path :d="itemIcon(index)" /></svg>
              </div>
              <div class="order-item-copy">
                <strong>{{ item.product_title || item.store_product?.title || 'Produto' }}</strong>
                <span>{{ item.product_access_label || item.store_product?.access_label || 'Conteúdo digital' }}</span>
                <small v-if="item.quantity > 1 || item.unit_price">
                  {{ item.quantity }} x {{ formatCurrency(item.unit_price) }}
                </small>
              </div>
              <strong class="order-item-price">{{ formatCurrency(item.total) }}</strong>
            </div>
          </div>
        </div>

        <div v-if="order.payments?.length" class="order-section">
          <div class="order-section-head">
            <h2>Pagamentos</h2>
            <span>{{ order.payments.length }} {{ order.payments.length === 1 ? 'tentativa' : 'tentativas' }}</span>
          </div>
          <div class="order-payments">
            <PaymentDetailsCard v-for="payment in order.payments" :key="payment.id" :payment="payment" />
          </div>
        </div>

        <div class="order-totals">
          <div class="order-total-line">
            <span>Subtotal</span>
            <strong>{{ formatCurrency(order.subtotal) }}</strong>
          </div>
          <div v-if="Number(order.discount) > 0" class="order-total-line discount">
            <span>Desconto<span v-if="order.coupon?.code"> · {{ order.coupon.code }}</span></span>
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

        <div class="order-actions">
          <router-link v-if="order.status === 'paid'" :to="{ name: 'dashboard' }" class="btn btn-primary order-cta">
            Ir para meus cursos
            <svg class="icon" width="16" height="16" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </router-link>
          <router-link :to="publicRoute('public-compras')" class="btn btn-outline">Minhas compras</router-link>
        </div>
      </div>

      <aside class="order-aside">
        <p class="public-eyebrow">Acompanhe</p>
        <h2>Histórico do pedido</h2>
        <OrderTimeline :events="order.timeline || []" />
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
import { useTenant } from '../composables/useTenant';
import { formatCurrency, formatDateTime } from '../utils/format';
import { orderStatusClass, orderStatusLabel } from '../utils/orderStatus';
import OrderTimeline from '../components/OrderTimeline.vue';
import PaymentDetailsCard from '../components/PaymentDetailsCard.vue';

const route = useRoute();
const { publicRoute } = useTenant();
const order = ref(null);
const loading = ref(true);
const error = ref(null);
let timer = null;

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
  if (order.value?.status === 'pending') {
    load();
  }
}, 8000);

onBeforeUnmount(() => clearInterval(timer));
</script>

<style scoped>
.order-layout{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(320px,.55fr);gap:clamp(24px,3vw,40px);align-items:start;}
.order-card{display:flex;flex-direction:column;gap:32px;padding:clamp(24px,3.4vw,40px);border:0;border-radius:28px;background:var(--bg-card);box-shadow:0 18px 48px rgba(15,23,42,.07);}
.order-status-head{display:flex;align-items:flex-start;gap:18px;}
.order-status-icon{display:flex;align-items:center;justify-content:center;flex-shrink:0;width:58px;height:58px;border-radius:18px;background:var(--accent-soft);color:var(--accent);}
.order-status-icon.pending{background:rgba(234,179,8,.12);color:#d9a514;}
.order-status-icon.canceled,.order-status-icon.refunded{background:rgba(255,107,107,.12);color:#ff6b6b;}
.order-status-copy{display:flex;flex-direction:column;gap:6px;min-width:0;}
.order-status-copy h1{font-size:clamp(34px,3.8vw,46px);font-weight:800;line-height:1.04;letter-spacing:-.04em;}
.order-status-copy p:last-child{max-width:620px;color:var(--text-secondary);font-size:14px;line-height:1.6;}
.order-pill{margin-left:auto;flex-shrink:0;}
.order-section{display:flex;flex-direction:column;gap:16px;}
.order-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.order-section-head h2{font-size:21px;font-weight:800;letter-spacing:-.02em;}
.order-section-head span{color:var(--text-muted);font-size:11.5px;}
.order-items{display:flex;flex-direction:column;border-radius:20px;overflow:hidden;background:var(--bg-card-alt);}
.order-item{display:flex;align-items:center;gap:16px;padding:18px;}
.order-item + .order-item{border-top:1px solid var(--border);}
.order-item-media{position:relative;display:flex;align-items:center;justify-content:center;flex-shrink:0;width:64px;height:56px;border-radius:13px;overflow:hidden;color:#fff;}
.order-item-media img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
.order-item-copy{display:flex;flex-direction:column;gap:4px;min-width:0;flex:1;}
.order-item-copy strong{font-size:14.5px;line-height:1.35;}
.order-item-copy span,.order-item-copy small{color:var(--text-muted);font-size:12px;}
.order-item-price{font-size:16px;white-space:nowrap;letter-spacing:-.02em;}
.order-payments{display:flex;flex-direction:column;gap:12px;}
.order-totals{display:flex;flex-direction:column;gap:10px;width:100%;max-width:440px;margin-left:auto;padding-top:4px;}
.order-total-line{display:flex;align-items:center;justify-content:space-between;gap:16px;color:var(--text-secondary);font-size:13px;}
.order-total-line strong{color:var(--text-primary);font-size:13.5px;}
.order-total-line.discount strong{color:#22c55e;}
.order-total-line.total{padding-top:16px;border-top:1px solid var(--border);font-size:14px;font-weight:800;}
.order-total-line.total strong{font-size:29px;color:var(--accent);letter-spacing:-.035em;}
.order-note{padding:16px 18px;border-radius:14px;background:var(--bg-card-alt);color:var(--text-secondary);font-size:13px;line-height:1.6;}
.order-note.success{background:rgba(34,197,94,.08);color:#22c55e;}
.order-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.order-cta{padding:14px 22px;border-radius:13px;}
.order-aside{position:sticky;top:108px;display:flex;flex-direction:column;gap:12px;padding:clamp(24px,2.6vw,32px);border:0;border-radius:26px;background:var(--bg-card);box-shadow:0 18px 48px rgba(15,23,42,.07);}
.order-aside h2{margin:2px 0 10px;font-size:24px;font-weight:800;letter-spacing:-.025em;}
.order-aside-note{margin-top:2px;color:var(--text-muted);font-size:12px;line-height:1.55;}
@media(max-width:1000px){
  .order-layout{grid-template-columns:1fr;}
  .order-aside{position:relative;top:0;}
}
@media(max-width:560px){
  .order-card{border-radius:20px;}
  .order-status-head{flex-wrap:wrap;}
  .order-status-icon{width:50px;height:50px;border-radius:15px;}
  .order-status-copy h1{font-size:34px;}
  .order-pill{margin-left:68px;}
  .order-item{padding:14px;gap:12px;}
  .order-item-media{width:56px;height:50px;}
  .order-item-price{font-size:14px;}
  .order-total-line.total strong{font-size:25px;}
}
</style>
