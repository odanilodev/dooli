<template>
  <div v-if="open" class="admin-modal-backdrop" @click.self="close">
    <div class="panel admin-modal payment-detail-drawer">
      <div class="admin-panel-head">
        <div>
          <h2 class="panel-title">Pagamento #{{ paymentId }}</h2>
          <p class="panel-desc">Detalhes financeiros, eventos e estornos.</p>
        </div>
        <button class="admin-modal-close" type="button" aria-label="Fechar" @click="close">×</button>
      </div>

      <p v-if="loading" class="admin-muted">Carregando pagamento...</p>
      <p v-else-if="error" class="admin-muted">{{ error }}</p>

      <template v-else-if="payment">
        <div class="payment-order-line">
          <span>Pedido #{{ payment.order?.id || payment.order_id }}</span>
          <span v-if="payment.order?.user?.name">{{ payment.order.user.name }}</span>
          <span v-if="payment.order?.user?.email" class="admin-muted">{{ payment.order.user.email }}</span>
          <router-link
            v-if="payment.order_id"
            :to="salesLink"
            class="admin-link"
            @click="close"
          >
            Abrir venda
          </router-link>
        </div>

        <PaymentDetailsCard :payment="payment" admin />

        <section v-if="canRefund" class="refund-panel">
          <div>
            <h3>Estornar pagamento</h3>
            <p>Saldo disponível: <strong>{{ formatCurrency(refundable) }}</strong></p>
          </div>
          <form class="refund-form" @submit.prevent="submitRefund">
            <label>
              <span>Valor</span>
              <input v-model="refund.amount" class="form-control" type="number" min="0.01" :max="refundable" step="0.01" required>
            </label>
            <label>
              <span>Motivo</span>
              <input v-model="refund.reason" class="form-control" type="text" maxlength="255" placeholder="Motivo do estorno">
            </label>
            <button class="btn btn-primary btn-sm" type="submit" :disabled="refunding">
              {{ refunding ? 'Estornando...' : 'Confirmar estorno' }}
            </button>
          </form>
        </section>
        <p v-else-if="payment.status === 'paid'" class="admin-muted">Este pagamento não possui saldo estornável.</p>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { api } from '../../api/http';
import { formatCurrency } from '../../utils/format';
import { useToast } from '../../composables/useToast';
import PaymentDetailsCard from '../PaymentDetailsCard.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  paymentId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'refunded']);
const toast = useToast();
const payment = ref(null);
const loading = ref(false);
const error = ref(null);
const refunding = ref(false);
const refund = reactive({ amount: '', reason: '' });

const refundable = computed(() => {
  const amount = Number(payment.value?.amount || 0);
  const refunded = Number(payment.value?.refunded_amount || 0);
  return Math.max(amount - refunded, 0);
});

const canRefund = computed(() => payment.value?.status === 'paid' && refundable.value > 0);

const salesLink = computed(() => {
  const query = { search: payment.value?.order_id };

  if (payment.value?.order?.status) {
    query.status = payment.value.order.status;
  }

  return { name: 'admin-vendas', query };
});

watch(() => [props.open, props.paymentId], async ([open, id]) => {
  if (!open || !id) {
    return;
  }

  await load();
}, { immediate: true });

async function load() {
  loading.value = true;
  error.value = null;

  try {
    const { data } = await api.get(`/admin/payments/${props.paymentId}`);
    payment.value = data.data;
    refund.amount = refundable.value.toFixed(2);
    refund.reason = '';
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar o pagamento.';
    payment.value = null;
  } finally {
    loading.value = false;
  }
}

async function submitRefund() {
  if (!payment.value) {
    return;
  }

  if (!window.confirm(`Estornar ${formatCurrency(Number(refund.amount))} deste pagamento?`)) {
    return;
  }

  refunding.value = true;

  try {
    await api.post(`/admin/payments/${payment.value.id}/refund`, {
      amount: Number(refund.amount),
      reason: refund.reason || null,
    });
    toast.success('Estorno realizado.');
    emit('refunded');
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível realizar o estorno.');
  } finally {
    refunding.value = false;
  }
}

function close() {
  emit('close');
}
</script>

<style scoped>
.payment-detail-drawer{max-width:900px;width:100%;max-height:88vh;overflow:auto;}
.payment-order-line{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px;padding:12px 14px;border-radius:12px;background:var(--bg-card-alt);font-size:12.5px;color:var(--text-secondary);}
.payment-order-line .admin-link{margin-left:auto;}
.refund-panel{display:flex;flex-direction:column;gap:12px;margin-top:18px;padding:18px;border:1px solid var(--border);border-radius:16px;background:var(--bg-card-alt);}
.refund-panel h3{margin:0 0 4px;font-size:15px;}
.refund-panel p{margin:0;color:var(--text-muted);font-size:12px;}
.refund-panel p strong{color:var(--text-primary);}
.refund-form{display:grid;grid-template-columns:minmax(120px,.5fr) minmax(180px,1fr) auto;align-items:end;gap:10px;}
.refund-form label{display:flex;flex-direction:column;gap:6px;}
.refund-form label span{color:var(--text-muted);font-size:10.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;}
@media(max-width:640px){
  .refund-form{grid-template-columns:1fr;}
  .payment-order-line .admin-link{margin-left:0;}
}
</style>
