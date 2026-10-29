<template>
  <div v-if="open" class="admin-modal-backdrop" @click.self="close">
    <div class="panel admin-modal seller-detail-drawer">
      <div class="admin-panel-head">
        <div>
          <h2 class="panel-title">{{ detail?.seller?.name || 'Vendedor' }}</h2>
          <p class="panel-desc">Links, produtos e comissões do vendedor.</p>
        </div>
        <button class="admin-modal-close" type="button" aria-label="Fechar" @click="close">×</button>
      </div>

      <p v-if="loading" class="admin-muted">Carregando vendedor...</p>
      <p v-else-if="error" class="admin-muted">{{ error }}</p>

      <template v-else-if="detail">
        <div class="seller-detail-top">
          <div>
            <span class="admin-sub">Código</span>
            <strong>{{ detail.seller.code }}</strong>
          </div>
          <div>
            <span class="admin-sub">Status</span>
            <span class="status-pill" :class="detail.seller.status">{{ detail.seller.status === 'active' ? 'Ativo' : 'Inativo' }}</span>
          </div>
          <div>
            <span class="admin-sub">Comissão</span>
            <strong>{{ Number(detail.seller.commission_percent).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) }}%</strong>
          </div>
          <div>
            <span class="admin-sub">E-mail</span>
            <strong>{{ detail.seller.email || '—' }}</strong>
          </div>
          <div>
            <span class="admin-sub">Telefone</span>
            <strong>{{ detail.seller.phone || '—' }}</strong>
          </div>
        </div>

        <section class="seller-metrics">
          <article><span>Alunos</span><strong>{{ metrics.students_count }}</strong></article>
          <article><span>Pedidos pagos</span><strong>{{ metrics.paid_orders_count }}</strong></article>
          <article><span>Faturamento pago</span><strong>{{ formatCurrency(metrics.gross_revenue) }}</strong></article>
          <article><span>Comissão gerada</span><strong>{{ formatCurrency(metrics.commission_generated) }}</strong></article>
          <article><span>A pagar</span><strong>{{ formatCurrency(metrics.commission_pending) }}</strong></article>
          <article><span>Paga</span><strong>{{ formatCurrency(metrics.commission_paid) }}</strong></article>
          <article><span>Estornada</span><strong>{{ formatCurrency(metrics.commission_refunded) }}</strong></article>
        </section>

        <section class="seller-kit-box">
          <div>
            <h3>Kit de links</h3>
            <p>Envie este link ao vendedor. Ele copia os links dos produtos sem precisar de login.</p>
            <code>{{ detail.seller.kit_url }}</code>
          </div>
          <div class="seller-kit-box-actions">
            <button class="btn btn-outline btn-sm" type="button" @click="copyKit">Copiar link</button>
            <button class="btn btn-outline btn-sm" type="button" @click="openKit">Abrir kit</button>
            <button class="btn btn-danger btn-sm" type="button" :disabled="rotating" @click="rotateKit">
              {{ rotating ? 'Gerando...' : 'Gerar novo link' }}
            </button>
          </div>
        </section>

        <section class="seller-section">
          <h3>Vendas por produto</h3>
          <div v-if="detail.products.length" class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>Produto</th><th>Alunos</th><th>Pedidos</th><th>Qtd.</th><th>Comissão</th><th>Estornado</th><th>Líquido</th></tr></thead>
              <tbody>
                <tr v-for="product in detail.products" :key="`${product.store_product_id}-${product.product_title}`">
                  <td>{{ product.product_title }}</td>
                  <td>{{ product.students_count }}</td>
                  <td>{{ product.orders_count }}</td>
                  <td>{{ product.quantity }}</td>
                  <td>{{ formatCurrency(product.commission_amount) }}</td>
                  <td>{{ formatCurrency(product.refunded_amount) }}</td>
                  <td>{{ formatCurrency(product.net_amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="admin-muted">Nenhuma venda atribuída ainda.</p>
        </section>

        <section class="seller-section">
          <h3>Pedidos recentes</h3>
          <div v-if="detail.recent_orders.length" class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>#</th><th>Aluno</th><th>Total</th><th>Status</th><th>Data</th></tr></thead>
              <tbody>
                <tr v-for="order in detail.recent_orders" :key="order.id">
                  <td>#{{ order.id }}</td>
                  <td>
                    {{ order.user?.name || 'Aluno' }}
                    <span v-if="order.user?.email" class="admin-sub">{{ order.user.email }}</span>
                  </td>
                  <td>{{ formatCurrency(order.total) }}</td>
                  <td><span class="status-pill" :class="orderStatusClass(order.status)">{{ orderStatusLabel(order.status) }}</span></td>
                  <td class="admin-muted">{{ formatDateTime(order.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="admin-muted">Nenhum pedido atribuído ainda.</p>
        </section>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { api } from '../../api/http';
import { useToast } from '../../composables/useToast';
import { formatCurrency, formatDateTime } from '../../utils/format';
import { orderStatusClass, orderStatusLabel } from '../../utils/orderStatus';

const props = defineProps({
  open: { type: Boolean, default: false },
  sellerId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'updated']);
const toast = useToast();
const detail = ref(null);
const loading = ref(false);
const error = ref(null);
const rotating = ref(false);

const metrics = computed(() => detail.value?.metrics ?? {
  students_count: 0,
  paid_orders_count: 0,
  gross_revenue: 0,
  commission_generated: 0,
  commission_pending: 0,
  commission_paid: 0,
  commission_refunded: 0,
});

watch(() => [props.open, props.sellerId], async ([open, id]) => {
  if (!open || !id) {
    return;
  }

  await load();
}, { immediate: true });

async function load() {
  loading.value = true;
  error.value = null;

  try {
    const { data } = await api.get(`/admin/sellers/${props.sellerId}`);
    detail.value = data.data;
  } catch (e) {
    detail.value = null;
    error.value = e?.message || 'Não foi possível carregar o vendedor.';
  } finally {
    loading.value = false;
  }
}

async function copyKit() {
  try {
    await navigator.clipboard.writeText(detail.value.seller.kit_url);
    toast.success('Link do kit copiado.');
  } catch (e) {
    toast.error('Não foi possível copiar o link.');
  }
}

function openKit() {
  window.open(detail.value.seller.kit_url, '_blank', 'noopener');
}

async function rotateKit() {
  if (!window.confirm('Gerar um novo link de kit? O link atual deixará de funcionar.')) {
    return;
  }

  rotating.value = true;

  try {
    const { data } = await api.post(`/admin/sellers/${props.sellerId}/rotate-kit`);
    detail.value.seller = { ...detail.value.seller, ...data.data };
    toast.success('Novo link do kit gerado.');
    emit('updated');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível gerar o novo link.');
  } finally {
    rotating.value = false;
  }
}

function close() {
  emit('close');
}
</script>

<style scoped>
.seller-detail-drawer{max-width:1040px;width:100%;max-height:90vh;overflow:auto;}
.seller-detail-top{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;padding:16px;border-radius:14px;background:var(--bg-card-alt);}
.seller-detail-top > div{display:flex;flex-direction:column;gap:4px;min-width:0;}
.seller-detail-top strong{font-size:13px;overflow-wrap:anywhere;}
.seller-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-top:18px;}
.seller-metrics article{display:flex;flex-direction:column;gap:5px;padding:14px;border:1px solid var(--border);border-radius:12px;background:var(--bg-card);}
.seller-metrics span{color:var(--text-muted);font-size:10px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;}
.seller-metrics strong{font-size:17px;letter-spacing:-.02em;}
.seller-kit-box{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-top:18px;padding:18px;border:1px solid var(--border);border-radius:14px;background:var(--bg-card-alt);}
.seller-kit-box h3{margin:0 0 4px;font-size:15px;}
.seller-kit-box p{margin:0 0 8px;color:var(--text-muted);font-size:12px;}
.seller-kit-box code{display:block;max-width:100%;overflow-wrap:anywhere;color:var(--text-secondary);font-size:11.5px;}
.seller-kit-box-actions{display:flex;gap:8px;flex-wrap:wrap;}
.seller-section{margin-top:20px;}
.seller-section h3{margin:0 0 10px;font-size:15px;}
</style>
