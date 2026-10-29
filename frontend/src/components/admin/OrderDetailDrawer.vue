<template>
  <div v-if="open" class="admin-modal-backdrop" @click.self="close">
    <div class="panel admin-modal order-detail-drawer">
      <div class="admin-panel-head">
        <div>
          <h2 class="panel-title">Pedido #{{ orderId }}</h2>
          <p class="panel-desc">Detalhes da compra, pagamentos e histórico.</p>
        </div>
        <button class="admin-modal-close" type="button" aria-label="Fechar" @click="close">×</button>
      </div>

      <p v-if="loading" class="admin-muted">Carregando pedido...</p>
      <p v-else-if="error" class="admin-muted">{{ error }}</p>

      <template v-else-if="order">
        <div class="order-detail-top">
          <div>
            <span class="admin-sub">Cliente</span>
            <strong>{{ order.user?.name || 'Aluno' }}</strong>
            <span v-if="order.user?.email" class="admin-muted">{{ order.user.email }}</span>
          </div>
          <div>
            <span class="admin-sub">Status</span>
            <span class="status-pill" :class="orderStatusClass(order.status)">{{ orderStatusLabel(order.status) }}</span>
          </div>
          <div>
            <span class="admin-sub">Tipo</span>
            <strong>{{ orderTypeLabel(order.type) }}</strong>
          </div>
          <div>
            <span class="admin-sub">Origem do pedido</span>
            <span class="status-pill" :class="originClass(order.source)">{{ originLabel(order.source) }}</span>
          </div>
          <div>
            <span class="admin-sub">Origem do cadastro</span>
            <span class="status-pill" :class="originClass(order.user?.registration_source)">{{ originLabel(order.user?.registration_source) }}</span>
          </div>
          <div>
            <span class="admin-sub">Criado em</span>
            <strong>{{ formatDateTime(order.created_at) }}</strong>
          </div>
          <div>
            <span class="admin-sub">Total</span>
            <strong>{{ formatCurrency(order.total) }}</strong>
          </div>
          <div v-if="Number(order.refunded_total) > 0">
            <span class="admin-sub">Estornado</span>
            <strong class="refunded-value">{{ formatCurrency(order.refunded_total) }}</strong>
          </div>
          <div v-if="order.seller">
            <span class="admin-sub">Vendedor</span>
            <strong>{{ order.seller.name }}</strong>
            <span class="admin-muted">{{ order.seller.code }}</span>
          </div>
        </div>

        <div v-if="order.period_start || order.period_end" class="order-period">
          Período da assinatura:
          <strong>{{ formatDateTime(order.period_start) }}</strong>
          até
          <strong>{{ formatDateTime(order.period_end) }}</strong>
        </div>

        <section class="order-detail-section">
          <h3>Itens</h3>
          <table class="admin-table">
            <thead><tr><th>Produto</th><th>Qtd.</th><th>Unitário</th><th>Total</th></tr></thead>
            <tbody>
              <tr v-for="item in order.items" :key="item.id">
                <td>
                  {{ item.product_title || item.store_product?.title || 'Produto' }}
                  <span class="admin-sub">{{ item.product_access_label || item.store_product?.access_label }}</span>
                </td>
                <td>{{ item.quantity }}</td>
                <td>{{ formatCurrency(item.unit_price) }}</td>
                <td>{{ formatCurrency(item.total) }}</td>
              </tr>
            </tbody>
          </table>
        </section>

        <div class="order-detail-totals">
          <span>Subtotal <strong>{{ formatCurrency(order.subtotal) }}</strong></span>
          <span v-if="Number(order.discount) > 0">
            Desconto <strong>- {{ formatCurrency(order.discount) }}</strong>
            <small v-if="order.coupon?.code">{{ order.coupon.code }}</small>
          </span>
          <span class="total">Total <strong>{{ formatCurrency(order.total) }}</strong></span>
        </div>

        <section v-if="order.seller || order.commissions?.length" class="order-detail-section">
          <h3>Vendedor e comissão</h3>
          <div v-if="order.seller" class="order-seller-line">
            <span><strong>{{ order.seller.name }}</strong> <span class="admin-muted">{{ order.seller.code }}</span></span>
            <span class="admin-muted">Código do link: {{ order.referral_code || '—' }} · Percentual: {{ Number(order.commission_percent || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) }}%</span>
          </div>
          <div v-if="order.commissions?.length" class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>Produto</th><th>Base</th><th>Comissão</th><th>Estornado</th><th>Líquido</th><th>Status</th></tr></thead>
              <tbody>
                <tr v-for="commission in order.commissions" :key="commission.id">
                  <td>{{ commission.product?.title || 'Produto' }}</td>
                  <td>{{ formatCurrency(commission.base_amount) }}</td>
                  <td>{{ formatCurrency(commission.amount) }}</td>
                  <td>{{ formatCurrency(commission.refunded_amount) }}</td>
                  <td>{{ formatCurrency(commission.net_amount) }}</td>
                  <td><span class="status-pill" :class="commissionStatusClass(commission.status)">{{ commissionStatusLabel(commission.status) }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="order-detail-section">
          <div class="order-detail-section-head">
            <h3>Pagamentos</h3>
            <router-link
              :to="{ name: 'admin-pagamentos', query: { order_id: order.id } }"
              class="btn btn-outline btn-sm"
              @click="close"
            >
              Ver no financeiro
            </router-link>
          </div>
          <div v-if="order.payments?.length" class="order-detail-payments">
            <PaymentDetailsCard v-for="payment in order.payments" :key="payment.id" :payment="payment" admin />
          </div>
          <p v-else class="admin-muted">Nenhum pagamento iniciado.</p>
        </section>

        <section class="order-detail-section">
          <h3>Histórico</h3>
          <OrderTimeline :events="order.timeline || []" admin />
        </section>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { api } from '../../api/http';
import { formatCurrency, formatDateTime } from '../../utils/format';
import { orderStatusClass, orderStatusLabel, orderTypeLabel } from '../../utils/orderStatus';
import { originClass, originLabel } from '../../utils/origin';
import OrderTimeline from '../OrderTimeline.vue';
import PaymentDetailsCard from '../PaymentDetailsCard.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  orderId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close']);
const order = ref(null);
const loading = ref(false);
const error = ref(null);

watch(() => [props.open, props.orderId], async ([open, id]) => {
  if (!open || !id) {
    return;
  }

  loading.value = true;
  error.value = null;

  try {
    const { data } = await api.get(`/admin/orders/${id}`);
    order.value = data.data;
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar o pedido.';
    order.value = null;
  } finally {
    loading.value = false;
  }
}, { immediate: true });

function commissionStatusLabel(status) {
  return { approved: 'A pagar', paid: 'Paga', reversed: 'Estornada' }[status] || status;
}

function commissionStatusClass(status) {
  return { approved: 'pending', paid: 'paid', reversed: 'refunded' }[status] || status;
}

function close() {
  emit('close');
}
</script>

<style scoped>
.order-detail-drawer{max-width:980px;width:100%;max-height:88vh;overflow:auto;}
.order-detail-top{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;padding:16px;border-radius:14px;background:var(--bg-card-alt);}
.order-detail-top > div{display:flex;flex-direction:column;gap:4px;min-width:0;}
.order-detail-top strong{font-size:13px;overflow-wrap:anywhere;}
.refunded-value{color:#ff6b6b;}
.order-period{margin-top:10px;color:var(--text-muted);font-size:12px;}
.order-period strong{color:var(--text-secondary);}
.order-detail-section{margin-top:20px;}
.order-detail-section h3{margin:0 0 10px;font-size:15px;}
.order-detail-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;}
.order-detail-section-head h3{margin:0;}
.order-detail-totals{display:flex;flex-wrap:wrap;gap:10px 22px;justify-content:flex-end;margin-top:18px;font-size:12.5px;color:var(--text-muted);}
.order-detail-totals span{display:flex;align-items:center;gap:8px;}
.order-detail-totals strong{color:var(--text-primary);}
.order-detail-totals .total strong{font-size:19px;color:var(--accent);}
.order-detail-totals small{padding:2px 6px;border-radius:6px;background:var(--bg-card-alt);}
.order-detail-payments{display:flex;flex-direction:column;gap:12px;}
.order-seller-line{display:flex;flex-direction:column;gap:4px;margin-bottom:10px;padding:12px 14px;border-radius:12px;background:var(--bg-card-alt);font-size:12.5px;}
@media(max-width:560px){
  .order-detail-totals{justify-content:flex-start;}
}
</style>
