<template>
  <main class="content public-content">
    <router-link :to="{ name: 'public-produto', params: { companySlug, productSlug } }" class="section-link">← Voltar ao produto</router-link>

    <div v-if="loading" class="empty-state">
      <strong>Carregando checkout...</strong>
    </div>

    <div v-else-if="error" class="empty-state">
      <strong>Não foi possível abrir o checkout</strong>
      <span>{{ error }}</span>
    </div>

    <div v-else-if="companyMismatch" class="empty-state">
      <strong>Esta conta pertence a outra empresa.</strong>
      <span>Saia da conta atual e entre com uma conta desta loja para continuar.</span>
      <button class="btn btn-primary" type="button" @click="switchCompanyAccount">Trocar de conta</button>
    </div>

    <section v-else-if="product" class="checkout-shell">
      <div class="checkout-card">
        <p class="public-eyebrow">Checkout</p>
        <h1 class="page-title">{{ product.title }}</h1>
        <p class="page-subtitle">{{ product.access_label }}</p>

        <div class="checkout-line">
          <span>Valor</span>
          <strong>{{ formatCurrency(currentPrice) }}<small v-if="product.is_subscription">/mês</small></strong>
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
              <span><strong>PIX</strong><small>Aprovação rápida</small></span>
            </label>
            <label class="payment-option" :class="{ active: method === 'credit_card' }">
              <input v-model="method" type="radio" value="credit_card">
              <span><strong>Cartão</strong><small>{{ product.is_subscription ? 'Cobrança mensal recorrente' : 'Checkout seguro Mercado Pago' }}</small></span>
            </label>
          </div>
        </div>

        <p v-if="!gateway" class="public-warning">Pagamento indisponível: a empresa ainda não configurou um gateway.</p>

        <button class="btn btn-primary btn-block" type="button" :disabled="submitting || !gateway" @click="submit">
          {{ submitting ? 'Processando...' : product.is_subscription ? 'Assinar' : 'Finalizar compra' }}
        </button>
      </div>

      <aside class="checkout-summary">
        <h2>Resumo</h2>
        <p>{{ product.title }}</p>
        <div class="checkout-line"><span>Total</span><strong>{{ formatCurrency(currentPrice) }}<small v-if="product.is_subscription">/mês</small></strong></div>
        <p class="checkout-note" v-if="product.is_subscription">A primeira cobrança ocorre na autorização. Sem período de teste.</p>
        <p class="checkout-note" v-else-if="product.access_duration_days">Acesso por {{ product.access_duration_days }} dias após a confirmação do pagamento.</p>
        <p class="checkout-note" v-else>Acesso permanente após a confirmação do pagamento.</p>
      </aside>
    </section>

    <section v-if="pix" class="checkout-card pix-card">
      <p class="public-eyebrow">Pagamento PIX</p>
      <h1 class="page-title">Escaneie o QR Code</h1>
      <p class="page-subtitle">O pedido será liberado automaticamente após a confirmação.</p>

      <img v-if="pix.qrImage" :src="pix.qrImage" alt="QR Code PIX" class="pix-image">
      <textarea v-if="pix.code" class="form-control pix-code" readonly :value="pix.code"></textarea>
      <button v-if="pix.code" class="btn btn-outline" type="button" @click="copyPix">Copiar código PIX</button>

      <div class="checkout-status">
        <span>Status do pedido:</span>
        <strong>{{ orderStatus }}</strong>
      </div>
      <p class="checkout-note">Você pode fechar esta página. O acesso será liberado quando o pagamento for aprovado.</p>
    </section>
  </main>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../api/http';
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
.public-content{max-width:1100px;margin:0 auto;padding:34px clamp(18px,5vw,64px) 70px;}
.checkout-shell{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(260px,.8fr);gap:24px;margin-top:24px;}
.checkout-card,.checkout-summary{padding:24px;border:1px solid var(--border);border-radius:18px;background:var(--bg-card);}
.checkout-card h1{font-size:26px;margin-bottom:4px;}
.checkout-summary h2{margin:0 0 14px;font-size:16px;}
.checkout-line{display:flex;justify-content:space-between;gap:20px;padding:13px 0;border-bottom:1px solid var(--border);font-size:14px;}
.checkout-line strong{font-size:18px;}
.checkout-line small{font-size:12px;color:var(--text-muted);}
.checkout-note{margin:14px 0 0;font-size:12.5px;color:var(--text-muted);line-height:1.6;}
.payment-options{display:grid;gap:10px;}
.payment-option{display:flex;gap:10px;align-items:flex-start;padding:12px;border:1px solid var(--border);border-radius:12px;cursor:pointer;background:var(--bg-card-alt);}
.payment-option.active{border-color:var(--accent);}
.payment-option span{display:flex;flex-direction:column;gap:2px;}
.payment-option small{color:var(--text-muted);font-size:12px;}
.public-warning{margin:14px 0;font-size:12.5px;color:#eab308;}
.pix-card{max-width:620px;margin:24px auto;text-align:center;}
.pix-image{display:block;width:220px;max-width:100%;margin:20px auto;background:#fff;padding:10px;border-radius:12px;}
.pix-code{margin:12px 0;height:90px;font-size:12px;}
.checkout-status{display:flex;justify-content:space-between;gap:16px;margin:18px 0 4px;padding-top:16px;border-top:1px solid var(--border);font-size:13px;}
@media(max-width:800px){.checkout-shell{grid-template-columns:1fr;}}
</style>
