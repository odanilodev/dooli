<template>
  <main class="content public-content">
    <nav class="public-breadcrumb" aria-label="Você está aqui">
      <router-link :to="{ name: 'public-loja', params: { companySlug } }">Loja</router-link>
      <span>/</span>
      <router-link :to="{ name: 'public-produto', params: { companySlug, productSlug } }">Produto</router-link>
      <span>/</span>
      <span class="current">Checkout</span>
    </nav>

    <div v-if="loading" class="public-empty">
      <strong>Carregando checkout...</strong>
    </div>

    <div v-else-if="error" class="public-empty">
      <strong>Não foi possível abrir o checkout</strong>
      <span>{{ error }}</span>
      <router-link :to="{ name: 'public-loja', params: { companySlug } }" class="btn btn-outline btn-sm">Voltar para a loja</router-link>
    </div>

    <div v-else-if="companyMismatch" class="public-empty">
      <strong>Esta conta pertence a outra empresa.</strong>
      <span>Saia da conta atual e entre com uma conta desta loja para continuar.</span>
      <button class="btn btn-primary" type="button" @click="switchCompanyAccount">Trocar de conta</button>
    </div>

    <section v-else-if="product && !pix" class="checkout-shell">
      <div class="checkout-main">
        <div class="checkout-steps" aria-label="Etapas do checkout">
          <span class="checkout-step done">1. Produto</span>
          <i></i>
          <span class="checkout-step active">2. Pagamento</span>
          <i></i>
          <span class="checkout-step">3. Confirmação</span>
        </div>

        <div class="checkout-card">
          <div class="checkout-heading">
            <p class="public-eyebrow">Pagamento</p>
            <h1>Finalize sua compra</h1>
            <p>Escolha a forma de pagamento para liberar seu acesso.</p>
          </div>

          <div class="checkout-product">
            <div class="checkout-product-media" :style="{ background: productGrad }">
              <img v-if="product.image_url || product.image" :src="product.image_url || product.image" :alt="product.title">
              <svg v-else class="icon" width="24" height="24" viewBox="0 0 24 24"><path :d="productIcon" /></svg>
            </div>
            <div class="checkout-product-copy">
              <strong>{{ product.title }}</strong>
              <span>{{ product.access_label }}</span>
            </div>
            <span class="checkout-product-price">{{ formatCurrency(currentPrice) }}<small v-if="product.is_subscription">/mês</small></span>
          </div>

          <div v-if="!product.is_subscription" class="form-group">
            <label for="coupon">Cupom de desconto</label>
            <input id="coupon" class="form-control" v-model="coupon" placeholder="Opcional">
          </div>

          <div class="form-group">
            <label>Forma de pagamento</label>
            <div class="payment-options">
              <label v-if="!product.is_subscription" class="payment-option" :class="{ active: method === 'pix' }">
                <input v-model="method" type="radio" value="pix">
                <span class="payment-mark payment-mark-pix">PIX</span>
                <span class="payment-copy">
                  <strong>PIX</strong>
                  <small>Aprovação rápida</small>
                </span>
              </label>

              <label class="payment-option" :class="{ active: method === 'credit_card' }">
                <input v-model="method" type="radio" value="credit_card">
                <span class="payment-mark">
                  <svg class="icon" width="17" height="17" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>
                </span>
                <span class="payment-copy">
                  <strong>Cartão</strong>
                  <small>{{ product.is_subscription ? 'Cobrança mensal recorrente' : 'Checkout seguro Mercado Pago' }}</small>
                </span>
              </label>
            </div>
          </div>

          <p v-if="!gateway" class="public-warning">Pagamento indisponível: a empresa ainda não configurou um gateway.</p>

          <button class="btn btn-primary btn-block checkout-submit" type="button" :disabled="submitting || !gateway" @click="submit">
            {{ submitting ? 'Processando...' : product.is_subscription ? 'Assinar agora' : 'Finalizar compra' }}
            <svg class="icon" width="16" height="16" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </button>

          <p class="checkout-secure">
            <svg class="icon" width="15" height="15" viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
            Seus dados são processados pelo gateway de pagamento da loja.
          </p>
        </div>
      </div>

      <aside class="checkout-summary">
        <div class="checkout-summary-head">
          <p class="public-eyebrow">Resumo</p>
          <h2>Resumo da compra</h2>
        </div>

        <div class="summary-product">
          <div class="summary-product-media" :style="{ background: productGrad }">
            <img v-if="product.image_url || product.image" :src="product.image_url || product.image" :alt="product.title">
            <svg v-else class="icon" width="20" height="20" viewBox="0 0 24 24"><path :d="productIcon" /></svg>
          </div>
          <div>
            <strong>{{ product.title }}</strong>
            <span>{{ product.access_label }}</span>
          </div>
        </div>

        <div class="summary-line">
          <span>Subtotal</span>
          <strong>{{ formatCurrency(currentPrice) }}</strong>
        </div>

        <div class="summary-total">
          <span>Total</span>
          <strong>{{ formatCurrency(currentPrice) }}<small v-if="product.is_subscription">/mês</small></strong>
        </div>

        <p v-if="product.is_subscription" class="summary-note">A primeira cobrança ocorre na autorização. Sem período de teste.</p>
        <p v-else-if="product.access_duration_days" class="summary-note">Acesso por {{ product.access_duration_days }} dias após a confirmação do pagamento.</p>
        <p v-else class="summary-note">Acesso permanente após a confirmação do pagamento.</p>

        <div class="summary-security">
          <svg class="icon" width="17" height="17" viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg>
          <span>Ambiente de pagamento protegido.</span>
        </div>
      </aside>
    </section>

    <section v-if="pix" class="pix-wrap">
      <div class="pix-card">
        <div class="pix-copy">
          <div class="checkout-steps" aria-label="Etapas do checkout">
            <span class="checkout-step done">1. Produto</span>
            <i></i>
            <span class="checkout-step done">2. Pagamento</span>
            <i></i>
            <span class="checkout-step active">3. Confirmação</span>
          </div>

          <p class="public-eyebrow">Pagamento via PIX</p>
          <h1>Escaneie o QR Code</h1>
          <p class="pix-desc">O pedido será liberado automaticamente após a confirmação.</p>

          <div class="pix-status" :class="orderStatus">
            <span>Status do pedido</span>
            <strong>{{ orderStatusLabel }}</strong>
          </div>

          <template v-if="pix.code">
            <textarea class="form-control pix-code" readonly :value="pix.code" aria-label="Código PIX"></textarea>
            <button class="btn btn-primary" type="button" @click="copyPix">Copiar código PIX</button>
          </template>

          <p class="summary-note">Você pode fechar esta página. O acesso será liberado quando o pagamento for aprovado.</p>
        </div>

        <div class="pix-qr">
          <img v-if="pix.qrImage" :src="pix.qrImage" alt="QR Code PIX">
          <div v-else class="pix-qr-placeholder">
            <span>QR Code</span>
            <small>Use o código PIX ao lado.</small>
          </div>
        </div>
      </div>
    </section>
  </main>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../api/http';
import { paletteAt } from '../data/palette';
import { useAuthStore } from '../stores/auth';
import { useStorefrontStore } from '../stores/storefront';
import { useToast } from '../composables/useToast';
import { formatCurrency } from '../utils/format';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const storefront = useStorefrontStore();
const toast = useToast();

const product = ref(null);
const loading = ref(true);
const error = ref(null);
const submitting = ref(false);
const method = ref('pix');
const coupon = ref('');
const pix = ref(null);
const orderId = ref(null);
const orderStatus = ref('pending');
let pollTimer = null;

const companySlug = computed(() => String(route.params.companySlug || ''));
const productSlug = computed(() => String(route.params.productSlug || ''));
const gateway = computed(() => storefront.defaultGateway);
const currentPrice = computed(() => Number(product.value?.sale) > 0 ? product.value.sale : product.value?.price);
const productPalette = computed(() => paletteAt(Number(product.value?.id) || 0));
const productIcon = computed(() => productPalette.value.icon);
const productGrad = computed(() => productPalette.value.grad);
const orderStatusLabel = computed(() => ({
  pending: 'Aguardando pagamento',
  paid: 'Pagamento aprovado',
  canceled: 'Pedido cancelado',
  refunded: 'Pagamento estornado',
}[orderStatus.value] || orderStatus.value));
const companyMismatch = computed(() => auth.isAuthenticated
  && storefront.company?.id
  && Number(auth.user?.company_id) !== Number(storefront.company.id));

load();

async function load() {
  loading.value = true;
  error.value = null;

  try {
    await storefront.loadCompany(companySlug.value);
    product.value = await storefront.fetchProduct(companySlug.value, productSlug.value);

    if (product.value?.is_subscription) {
      method.value = 'credit_card';
    }
  } catch (e) {
    error.value = e?.message || 'Produto indisponível.';
  } finally {
    loading.value = false;
  }
}

async function submit() {
  if (!gateway.value) {
    toast.error('Pagamento indisponível para esta empresa.');
    return;
  }

  submitting.value = true;

  try {
    if (product.value.is_subscription) {
      const { data } = await api.post('/me/subscriptions', {
        store_product_id: product.value.id,
        gateway_id: gateway.value.id,
      });

      if (data.data?.checkout_url) {
        window.location.href = data.data.checkout_url;
        return;
      }

      toast.success('Assinatura criada. Aguardando confirmação.');
      await router.push({ name: 'public-assinaturas', params: { companySlug: companySlug.value } });
      return;
    }

    const order = await api.post('/me/orders', {
      items: [{ store_product_id: product.value.id, quantity: 1 }],
      coupon_code: coupon.value.trim() || null,
    });

    orderId.value = order.data.data.id;

    const payment = await api.post(`/me/orders/${orderId.value}/pay`, {
      gateway_id: gateway.value.id,
      method: method.value,
    });

    if (payment.data.data?.checkout_url) {
      window.location.href = payment.data.data.checkout_url;
      return;
    }

    const paymentData = payment.data.data;
    const transaction = paymentData?.metadata?.point_of_interaction?.transaction_data ?? {};

    pix.value = {
      code: transaction.qr_code ?? null,
      qrImage: transaction.qr_code_base64
        ? `data:image/png;base64,${transaction.qr_code_base64}`
        : null,
    };

    startPolling();
  } catch (e) {
    const firstError = e?.errors ? Object.values(e.errors).flat()[0] : null;
    toast.error(firstError || e?.message || 'Não foi possível concluir a compra.');
  } finally {
    submitting.value = false;
  }
}

function startPolling() {
  stopPolling();
  pollTimer = setInterval(async () => {
    if (!orderId.value) {
      return;
    }

    try {
      const { data } = await api.get(`/me/orders/${orderId.value}`);
      orderStatus.value = data.data.status;

      if (data.data.status === 'paid') {
        stopPolling();
        toast.success('Pagamento aprovado! Seu acesso foi liberado.');
        await router.push('/');
      }
    } catch (e) {
      /* keep polling */
    }
  }, 5000);
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

async function copyPix() {
  try {
    await navigator.clipboard.writeText(pix.value.code);
    toast.success('Código PIX copiado.');
  } catch (e) {
    toast.error('Não foi possível copiar o código.');
  }
}

async function switchCompanyAccount() {
  await auth.logout();
  await router.push({
    name: 'public-login',
    params: { companySlug: companySlug.value },
    query: { redirect: route.fullPath },
  });
}

onBeforeUnmount(stopPolling);
</script>

<style scoped>
.checkout-shell{
  display:grid;grid-template-columns:minmax(0,1.35fr) minmax(280px,.65fr);
  gap:24px;align-items:start;
}
.checkout-main{display:flex;flex-direction:column;gap:16px;min-width:0;}
.checkout-steps{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.checkout-step{
  padding:6px 10px;border:1px solid var(--border);border-radius:999px;
  color:var(--text-muted);font-size:10.5px;font-weight:800;white-space:nowrap;
}
.checkout-step.done{border-color:color-mix(in srgb,var(--accent) 34%,var(--border));color:var(--accent);background:var(--accent-soft);}
.checkout-step.active{border-color:transparent;background:var(--accent-grad);color:var(--accent-ink);}
.checkout-steps i{width:18px;height:1px;background:var(--border);}
.checkout-card,.checkout-summary{
  padding:24px;border:1px solid var(--border);border-radius:20px;background:var(--bg-card);box-shadow:var(--shadow-sm);
}
.checkout-card{display:flex;flex-direction:column;gap:20px;}
.checkout-heading{display:flex;flex-direction:column;gap:6px;}
.checkout-heading h1{font-size:clamp(24px,3vw,32px);font-weight:800;letter-spacing:-.02em;}
.checkout-heading > p:last-child{color:var(--text-secondary);font-size:13px;line-height:1.5;}
.checkout-product{
  display:grid;grid-template-columns:68px minmax(0,1fr) auto;gap:13px;align-items:center;
  padding:13px;border:1px solid var(--border);border-radius:14px;background:var(--bg-card-alt);
}
.checkout-product-media,.summary-product-media{
  position:relative;display:flex;align-items:center;justify-content:center;overflow:hidden;
  border-radius:10px;color:#fff;
}
.checkout-product-media{width:68px;height:58px;}
.checkout-product-media img,.summary-product-media img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
.checkout-product-copy{display:flex;flex-direction:column;gap:3px;min-width:0;}
.checkout-product-copy strong{font-size:13px;line-height:1.35;}
.checkout-product-copy span{color:var(--text-muted);font-size:11px;}
.checkout-product-price{font-size:15px;font-weight:800;color:var(--accent);white-space:nowrap;}
.checkout-product-price small{font-size:10px;color:var(--text-muted);}
.payment-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;}
.payment-option{
  display:flex;align-items:flex-start;gap:10px;padding:13px;
  border:1px solid var(--border);border-radius:13px;background:var(--bg-card-alt);cursor:pointer;
  transition:border-color .15s ease,box-shadow .15s ease;
}
.payment-option:hover{border-color:color-mix(in srgb,var(--accent) 45%,var(--border));}
.payment-option.active{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft);}
.payment-option input{width:15px;height:15px;margin:2px 0 0;accent-color:var(--accent);flex-shrink:0;}
.payment-mark{
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
  width:35px;height:35px;border-radius:9px;background:var(--bg-card);
  border:1px solid var(--border);color:var(--accent);
}
.payment-mark-pix{font-size:9px;font-weight:900;letter-spacing:.03em;}
.payment-copy{display:flex;flex-direction:column;gap:2px;min-width:0;}
.payment-copy strong{font-size:12.5px;color:var(--text-primary);}
.payment-copy small{color:var(--text-muted);font-size:10.5px;line-height:1.35;}
.checkout-submit{margin-top:2px;padding:14px 20px;}
.checkout-secure{
  display:flex;align-items:center;justify-content:center;gap:7px;
  color:var(--text-muted);font-size:10.5px;text-align:center;line-height:1.4;
}
.checkout-secure svg{flex-shrink:0;color:var(--accent);}
.checkout-summary{position:sticky;top:102px;display:flex;flex-direction:column;gap:14px;}
.checkout-summary-head h2{margin-top:4px;font-size:19px;font-weight:800;}
.summary-product{display:flex;align-items:center;gap:11px;padding-bottom:14px;border-bottom:1px solid var(--border);}
.summary-product-media{width:49px;height:45px;flex-shrink:0;}
.summary-product > div:last-child{display:flex;flex-direction:column;gap:3px;min-width:0;}
.summary-product strong{font-size:12.5px;line-height:1.35;}
.summary-product span{color:var(--text-muted);font-size:10.5px;}
.summary-line,.summary-total{display:flex;align-items:center;justify-content:space-between;gap:16px;}
.summary-line{color:var(--text-secondary);font-size:12px;}
.summary-line strong{font-size:13px;color:var(--text-primary);}
.summary-total{padding-top:13px;border-top:1px solid var(--border);font-size:13px;font-weight:800;}
.summary-total strong{font-size:21px;color:var(--accent);}
.summary-total small{font-size:10.5px;color:var(--text-muted);}
.summary-note{margin:0;color:var(--text-muted);font-size:11px;line-height:1.5;}
.summary-security{
  display:flex;align-items:flex-start;gap:8px;padding:11px;border-radius:10px;
  background:var(--accent-soft);color:var(--accent);font-size:10.5px;line-height:1.4;font-weight:700;
}
.summary-security svg{flex-shrink:0;}
.pix-wrap{display:flex;justify-content:center;}
.pix-card{
  display:grid;grid-template-columns:minmax(0,1fr) minmax(230px,.65fr);gap:clamp(22px,4vw,40px);
  width:100%;max-width:880px;padding:clamp(22px,4vw,34px);
  border:1px solid var(--border);border-radius:22px;background:var(--bg-card);box-shadow:var(--shadow-sm);
}
.pix-copy{display:flex;flex-direction:column;align-items:flex-start;gap:14px;min-width:0;}
.pix-copy h1{font-size:clamp(25px,3.5vw,34px);font-weight:800;line-height:1.12;letter-spacing:-.02em;}
.pix-desc{color:var(--text-secondary);font-size:13px;line-height:1.6;}
.pix-status{
  display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;
  padding:11px 13px;border:1px solid var(--border);border-radius:11px;
  color:var(--text-secondary);font-size:11.5px;background:var(--bg-card-alt);
}
.pix-status strong{font-size:11px;text-transform:uppercase;letter-spacing:.03em;}
.pix-status.pending strong{color:#d9a514;}
.pix-status.paid strong{color:#22c55e;}
.pix-status.canceled strong,.pix-status.refunded strong{color:#ff6b6b;}
.pix-code{height:88px;font-size:11px;resize:none;}
.pix-copy .btn-primary{width:100%;}
.pix-qr{
  display:flex;align-items:center;justify-content:center;align-self:start;
  padding:16px;border:1px solid var(--border);border-radius:17px;background:#fff;
}
.pix-qr img{display:block;width:100%;max-width:260px;height:auto;}
.pix-qr-placeholder{
  display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;
  width:100%;min-height:220px;color:#52627a;text-align:center;
}
.pix-qr-placeholder span{font-size:15px;font-weight:800;}
.pix-qr-placeholder small{font-size:11px;}
@media(max-width:900px){
  .checkout-shell{grid-template-columns:1fr;}
  .checkout-summary{position:relative;top:0;}
}
@media(max-width:760px){
  .pix-card{grid-template-columns:1fr;}
  .pix-qr{order:-1;max-width:320px;width:100%;justify-self:center;}
}
@media(max-width:560px){
  .checkout-card,.checkout-summary{padding:18px;border-radius:17px;}
  .checkout-product{grid-template-columns:56px minmax(0,1fr);}
  .checkout-product-media{width:56px;height:50px;}
  .checkout-product-price{grid-column:1/-1;padding-top:2px;}
  .payment-options{grid-template-columns:1fr;}
  .checkout-steps{gap:5px;}
  .checkout-steps i{width:9px;}
  .checkout-step{font-size:9.5px;padding:5px 8px;}
}
</style>
