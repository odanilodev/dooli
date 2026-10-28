<template>
  <article class="payment-card">
    <header class="payment-card-head">
      <div>
        <span class="payment-method">{{ paymentMethodLabel(payment.method) }}</span>
        <strong>{{ formatCurrency(payment.amount) }}</strong>
      </div>
      <span class="status-pill" :class="paymentStatusClass(payment.status)">
        {{ paymentStatusLabel(payment.status) }}
      </span>
    </header>

    <dl class="payment-grid">
      <div v-if="payment.gateway?.name">
        <dt>Gateway</dt>
        <dd>{{ payment.gateway.name }}</dd>
      </div>
      <div v-if="payment.created_at">
        <dt>Iniciado em</dt>
        <dd>{{ formatDateTime(payment.created_at) }}</dd>
      </div>
      <div v-if="payment.paid_at">
        <dt>Pago em</dt>
        <dd>{{ formatDateTime(payment.paid_at) }}</dd>
      </div>
      <div v-if="payment.transaction_id">
        <dt>ID da transação</dt>
        <dd class="mono">{{ payment.transaction_id }}</dd>
      </div>
      <div v-if="details.installments">
        <dt>Parcelas</dt>
        <dd>
          {{ details.installments }}x
          <span v-if="details.installment_amount">de {{ formatCurrency(details.installment_amount) }}</span>
        </dd>
      </div>
      <div v-if="details.card_brand || details.card_last_four">
        <dt>Cartão</dt>
        <dd>
          <span v-if="details.card_brand">{{ details.card_brand }}</span>
          <span v-if="details.card_last_four"> •••• {{ details.card_last_four }}</span>
        </dd>
      </div>
      <div v-if="details.authorization_code">
        <dt>Autorização</dt>
        <dd class="mono">{{ details.authorization_code }}</dd>
      </div>
      <div v-if="details.status_detail">
        <dt>Detalhe do gateway</dt>
        <dd>{{ details.status_detail }}</dd>
      </div>
    </dl>

    <div v-if="payment.pix?.qr_code || payment.pix?.qr_code_base64" class="payment-pix">
      <div class="pix-copy">
        <strong>PIX para pagamento</strong>
        <span v-if="payment.pix.expires_at">Expira em {{ formatDateTime(payment.pix.expires_at) }}</span>
        <a v-if="payment.pix.ticket_url" :href="payment.pix.ticket_url" target="_blank" rel="noopener">Abrir comprovante</a>
      </div>
      <img v-if="payment.pix.qr_code_base64" :src="pixImage" alt="QR Code do PIX">
      <div v-if="payment.pix.qr_code" class="pix-code">
        <code>{{ payment.pix.qr_code }}</code>
        <button class="btn btn-outline btn-sm" type="button" @click="copyPix">Copiar código</button>
      </div>
    </div>

    <a v-if="details.receipt_url" class="payment-receipt" :href="details.receipt_url" target="_blank" rel="noopener">
      Ver comprovante do pagamento
    </a>

    <div v-if="payment.refunds?.length" class="payment-refunds">
      <strong>Estornos</strong>
      <div v-for="refund in payment.refunds" :key="refund.id" class="refund-line">
        <span>{{ formatCurrency(refund.amount) }}</span>
        <span class="status-pill" :class="refund.status">{{ refundStatusLabel(refund.status) }}</span>
        <time>{{ formatDateTime(refund.refunded_at || refund.created_at) }}</time>
        <span v-if="refund.reason" class="refund-reason">{{ refund.reason }}</span>
      </div>
    </div>

    <details v-if="admin && payment.events?.length" class="payment-events">
      <summary>Log técnico do gateway ({{ payment.events.length }})</summary>
      <div v-for="event in payment.events" :key="event.id" class="event-line">
        <span class="mono">{{ event.event }}</span>
        <span class="status-pill" :class="event.status">{{ event.status }}</span>
        <time>{{ formatDateTime(event.created_at) }}</time>
        <span v-if="event.description">{{ event.description }}</span>
        <span v-if="event.external_id" class="mono">{{ event.external_id }}</span>
        <span v-if="event.error_code" class="error-code">{{ event.error_code }}</span>
      </div>
    </details>
  </article>
</template>

<script setup>
import { computed } from 'vue';
import { formatCurrency, formatDateTime } from '../utils/format';
import {
  paymentMethodLabel,
  paymentStatusClass,
  paymentStatusLabel,
  refundStatusLabel,
} from '../utils/orderStatus';
import { useToast } from '../composables/useToast';

const props = defineProps({
  payment: { type: Object, required: true },
  admin: { type: Boolean, default: false },
});

const toast = useToast();
const details = computed(() => props.payment.details || {});
const pixImage = computed(() => {
  const raw = props.payment.pix?.qr_code_base64;
  return raw ? (raw.startsWith('data:') ? raw : `data:image/png;base64,${raw}`) : null;
});

async function copyPix() {
  try {
    await navigator.clipboard.writeText(props.payment.pix?.qr_code || '');
    toast.success('Código PIX copiado.');
  } catch (e) {
    toast.error('Não foi possível copiar o código.');
  }
}
</script>

<style scoped>
.payment-card{display:flex;flex-direction:column;gap:16px;padding:20px;border:1px solid var(--border);border-radius:18px;background:var(--bg-card-alt);}
.payment-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;}
.payment-card-head > div{display:flex;flex-direction:column;gap:4px;}
.payment-method{color:var(--text-muted);font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;}
.payment-card-head strong{font-size:22px;letter-spacing:-.03em;}
.payment-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin:0;}
.payment-grid div{display:flex;flex-direction:column;gap:3px;min-width:0;}
.payment-grid dt{color:var(--text-muted);font-size:10.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;}
.payment-grid dd{margin:0;font-size:12.5px;color:var(--text-secondary);overflow-wrap:anywhere;}
.mono{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:11.5px;}
.payment-pix{display:grid;grid-template-columns:1fr auto;gap:14px;padding:16px;border-radius:14px;background:var(--bg-card);border:1px solid var(--border);}
.pix-copy{display:flex;flex-direction:column;gap:4px;font-size:12px;color:var(--text-muted);}
.pix-copy strong{font-size:13.5px;color:var(--text-primary);}
.pix-copy a{font-weight:700;}
.payment-pix img{width:132px;height:132px;border-radius:10px;background:#fff;padding:6px;grid-row:span 2;}
.pix-code{display:flex;flex-direction:column;gap:8px;min-width:0;grid-column:1;}
.pix-code code{padding:8px 10px;border-radius:8px;background:var(--bg-card-alt);font-size:11px;overflow-wrap:anywhere;}
.pix-code button{align-self:flex-start;}
.payment-receipt{font-size:12px;font-weight:700;color:var(--accent);}
.payment-refunds{display:flex;flex-direction:column;gap:8px;padding-top:14px;border-top:1px solid var(--border);font-size:12.5px;}
.refund-line{display:flex;flex-wrap:wrap;align-items:center;gap:8px;color:var(--text-secondary);}
.refund-line time{color:var(--text-muted);font-size:11px;}
.refund-reason{flex-basis:100%;color:var(--text-muted);font-size:11.5px;}
.payment-events{font-size:12px;color:var(--text-secondary);}
.payment-events summary{cursor:pointer;font-weight:700;color:var(--text-primary);}
.event-line{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:9px 0;border-bottom:1px solid var(--border);}
.event-line time{color:var(--text-muted);font-size:11px;}
.event-line > span:last-child{flex-basis:100%;color:var(--text-muted);font-size:11.5px;}
.error-code{color:#ff6b6b!important;}
@media(max-width:560px){
  .payment-pix{grid-template-columns:1fr;}
  .payment-pix img{grid-row:auto;grid-column:1;justify-self:start;}
  .pix-code{grid-column:1;}
}
</style>
