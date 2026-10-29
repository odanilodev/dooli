<template>
  <main class="content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Comissões</h1>
        <p class="page-subtitle">Valores gerados, pendentes, pagos e estornados por vendedor.</p>
      </div>
      <div class="admin-head-actions">
        <router-link :to="{ name: 'admin-vendedores' }" class="btn btn-outline">Vendedores</router-link>
        <button class="btn btn-outline" type="button" :disabled="exporting" @click="exportFile">
          {{ exporting ? 'Exportando...' : 'Exportar Excel' }}
        </button>
      </div>
    </div>

    <section class="commission-kpis">
      <article><span>A pagar</span><strong>{{ formatCurrency(summary.approved) }}</strong></article>
      <article><span>Paga</span><strong>{{ formatCurrency(summary.paid) }}</strong></article>
      <article><span>Estornada</span><strong>{{ formatCurrency(summary.reversed) }}</strong></article>
      <article><span>Gerada</span><strong>{{ formatCurrency(summary.generated) }}</strong></article>
    </section>

    <form class="commissions-filters" @submit.prevent="reload">
      <input v-model="filters.search" class="form-control" type="search" placeholder="Buscar vendedor, aluno, produto ou #pedido">
      <SearchSelect
        v-model="filters.seller_id"
        :options="sellerOptions"
        :loading="sellersLoading"
        remote
        allow-clear
        clear-label="Todos os vendedores"
        placeholder="Todos os vendedores"
        @search="loadSellers"
      />
      <select v-model="filters.status" class="form-control">
        <option value="">Todos os status</option>
        <option value="approved">A pagar</option>
        <option value="paid">Paga</option>
        <option value="reversed">Estornada</option>
      </select>
      <SearchSelect
        v-model="filters.product_id"
        :options="productOptions"
        :loading="productsLoading"
        remote
        allow-clear
        clear-label="Todos os produtos"
        placeholder="Todos os produtos"
        @search="loadProducts"
      />
      <input v-model="filters.date_from" class="form-control" type="date" aria-label="Data inicial">
      <input v-model="filters.date_to" class="form-control" type="date" aria-label="Data final">
      <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
      <button v-if="hasFilters" class="btn btn-outline btn-sm" type="button" @click="clearFilters">Limpar</button>
    </form>

    <div v-if="selectedCount" class="commission-bulk">
      <span>{{ selectedCount }} comissão(ões) selecionada(s)</span>
      <button class="btn btn-primary btn-sm" type="button" @click="openBulkPay">Marcar selecionadas como pagas</button>
    </div>

    <div v-if="loading" class="empty-state"><strong>Carregando...</strong></div>
    <div v-else-if="!commissions.length" class="empty-state"><strong>Nenhuma comissão</strong></div>
    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th><input type="checkbox" :checked="selection.isAllSelected(payableIds)" :indeterminate.prop="selection.isPartial(payableIds)" :disabled="!payableIds.length" @change="toggleAll"></th>
            <th>Vendedor</th>
            <th>Pedido / Aluno</th>
            <th>Produto</th>
            <th>Base</th>
            <th>%</th>
            <th>Comissão</th>
            <th>Estornado</th>
            <th>Líquido</th>
            <th>Status</th>
            <th>Data</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="commission in commissions" :key="commission.id">
            <td>
              <input
                type="checkbox"
                :checked="selection.isSelected(commission.id)"
                :disabled="!isPayable(commission)"
                @change="selection.toggle(commission.id)"
              >
            </td>
            <td>
              <strong>{{ commission.seller?.name || 'Vendedor' }}</strong>
              <span class="admin-sub">{{ commission.seller?.code }}</span>
            </td>
            <td>
              #{{ commission.order_id }}
              <span class="admin-sub">{{ commission.order?.user?.name || 'Aluno' }}</span>
            </td>
            <td class="admin-muted">{{ commission.product?.title || 'Produto' }}</td>
            <td>{{ formatCurrency(commission.base_amount) }}</td>
            <td>{{ Number(commission.commission_percent).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) }}%</td>
            <td>{{ formatCurrency(commission.amount) }}</td>
            <td>{{ formatCurrency(commission.refunded_amount) }}</td>
            <td><strong>{{ formatCurrency(commission.net_amount) }}</strong></td>
            <td><span class="status-pill" :class="statusClass(commission.status)">{{ statusLabel(commission.status) }}</span></td>
            <td class="admin-muted">{{ formatDateTime(commission.created_at) }}</td>
            <td class="admin-actions">
              <button
                v-if="isPayable(commission)"
                class="btn btn-outline btn-sm"
                type="button"
                @click="openPay(commission)"
              >Marcar paga</button>
              <span v-else-if="commission.paid_at" class="admin-sub">{{ formatDateTime(commission.paid_at) }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="admin-pager">
      <button class="btn btn-outline btn-sm" type="button" :disabled="meta.current_page <= 1" @click="go(meta.current_page - 1)">Anterior</button>
      <span class="admin-muted">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      <button class="btn btn-outline btn-sm" type="button" :disabled="meta.current_page >= meta.last_page" @click="go(meta.current_page + 1)">Próxima</button>
    </div>

    <ConfirmModal
      v-if="payModalOpen"
      :title="payTarget ? `Pagar comissão #${payTarget.id}` : 'Pagar comissões selecionadas'"
      :message="payTarget ? `${payTarget.seller?.name} · ${formatCurrency(payTarget.net_amount)}` : `${selectedCount} comissão(ões) selecionada(s).`"
      confirm-label="Marcar como paga"
      variant="primary"
      :busy="paying"
      @cancel="payModalOpen = false"
      @confirm="confirmPay"
    >
      <div class="pay-form">
        <div class="form-group">
          <label for="pay-date">Data do pagamento</label>
          <input id="pay-date" v-model="payForm.paid_at" class="form-control" type="datetime-local">
        </div>
        <div class="form-group">
          <label for="pay-reference">Referência</label>
          <input id="pay-reference" v-model="payForm.payment_reference" class="form-control" placeholder="PIX, transferência, recibo...">
        </div>
        <div class="form-group">
          <label for="pay-notes">Observação</label>
          <textarea id="pay-notes" v-model="payForm.payment_notes" class="form-control" rows="2"></textarea>
        </div>
      </div>
    </ConfirmModal>
  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';
import { useBulkSelection } from '../composables/useBulkSelection';
import { useToast } from '../composables/useToast';
import { formatCurrency, formatDateTime } from '../utils/format';
import ConfirmModal from '../components/admin/ConfirmModal.vue';
import SearchSelect from '../components/admin/SearchSelect.vue';

const toast = useToast();
const selection = useBulkSelection();
const selectedCount = selection.count;
const commissions = ref([]);
const meta = ref({ current_page: 1, last_page: 1 });
const summary = ref({ approved: 0, paid: 0, reversed: 0, generated: 0 });
const loading = ref(true);
const exporting = ref(false);
const sellersLoading = ref(false);
const productsLoading = ref(false);
const sellerOptions = ref([]);
const productOptions = ref([]);
const payModalOpen = ref(false);
const payTarget = ref(null);
const paying = ref(false);
const filters = reactive({
  search: '',
  seller_id: '',
  status: '',
  product_id: '',
  date_from: '',
  date_to: '',
});
const payForm = reactive({
  paid_at: '',
  payment_reference: '',
  payment_notes: '',
});

const hasFilters = computed(() => Boolean(
  filters.search || filters.seller_id || filters.status || filters.product_id
  || filters.date_from || filters.date_to,
));

const payableIds = computed(() => commissions.value.filter(isPayable).map((commission) => commission.id));

onMounted(() => {
  loadSellers('');
  loadProducts('');
  load();
});

function params() {
  return {
    search: filters.search || undefined,
    seller_id: filters.seller_id || undefined,
    status: filters.status || undefined,
    product_id: filters.product_id || undefined,
    date_from: filters.date_from || undefined,
    date_to: filters.date_to || undefined,
  };
}

async function load() {
  loading.value = true;

  try {
    const [{ data: list }, { data: totals }] = await Promise.all([
      api.get('/admin/commissions', {
        params: { ...params(), page: meta.value.current_page, per_page: 20 },
      }),
      api.get('/admin/commissions/summary', { params: params() }),
    ]);

    commissions.value = list.data ?? [];
    meta.value = list.meta ?? meta.value;
    summary.value = { approved: 0, paid: 0, reversed: 0, generated: 0, ...(totals.data || {}) };
    selection.clear();
  } finally {
    loading.value = false;
  }
}

function reload() {
  meta.value.current_page = 1;
  load();
}

function go(page) {
  meta.value.current_page = page;
  load();
}

function clearFilters() {
  Object.assign(filters, {
    search: '',
    seller_id: '',
    status: '',
    product_id: '',
    date_from: '',
    date_to: '',
  });
  reload();
}

async function loadSellers(search = '') {
  sellersLoading.value = true;

  try {
    const { data } = await api.get('/admin/sellers', {
      params: { search: search || undefined, per_page: 30 },
    });
    sellerOptions.value = (data.data ?? []).map((seller) => ({
      value: seller.id,
      label: seller.name,
      sub: seller.code,
    }));
  } catch (e) {
    sellerOptions.value = [];
  } finally {
    sellersLoading.value = false;
  }
}

async function loadProducts(search = '') {
  productsLoading.value = true;

  try {
    const { data } = await api.get('/admin/store-products', {
      params: { search: search || undefined, per_page: 30 },
    });
    productOptions.value = (data.data ?? []).map((product) => ({
      value: product.id,
      label: product.title,
      sub: product.slug,
    }));
  } catch (e) {
    productOptions.value = [];
  } finally {
    productsLoading.value = false;
  }
}

function isPayable(commission) {
  return commission.status === 'approved' && Number(commission.net_amount) > 0;
}

function toggleAll(event) {
  selection.toggleAll(payableIds.value, event.target.checked);
}

function openPay(commission) {
  payTarget.value = commission;
  payForm.paid_at = '';
  payForm.payment_reference = '';
  payForm.payment_notes = '';
  payModalOpen.value = true;
}

function openBulkPay() {
  if (!selectedCount.value) {
    return;
  }

  payTarget.value = null;
  payForm.paid_at = '';
  payForm.payment_reference = '';
  payForm.payment_notes = '';
  payModalOpen.value = true;
}

async function confirmPay() {
  paying.value = true;

  try {
    const payload = {
      paid_at: payForm.paid_at || null,
      payment_reference: payForm.payment_reference || null,
      payment_notes: payForm.payment_notes || null,
    };

    if (payTarget.value) {
      await api.post(`/admin/commissions/${payTarget.value.id}/pay`, payload);
      toast.success('Comissão marcada como paga.');
    } else {
      await api.post('/admin/commissions/bulk-pay', {
        ids: selection.selectedIds.value,
        ...payload,
      });
      toast.success('Comissões marcadas como pagas.');
    }

    payModalOpen.value = false;
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível marcar a comissão como paga.');
  } finally {
    paying.value = false;
  }
}

async function exportFile() {
  exporting.value = true;

  try {
    const { data } = await api.get('/admin/commissions/export', {
      params: params(),
      responseType: 'blob',
      headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
    });

    const url = URL.createObjectURL(new Blob([data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `comissoes-${new Date().toISOString().slice(0, 10)}.xlsx`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  } catch (e) {
    toast.error(e?.message || 'Não foi possível exportar as comissões.');
  } finally {
    exporting.value = false;
  }
}

function statusLabel(value) {
  return { approved: 'A pagar', paid: 'Paga', reversed: 'Estornada' }[value] || value;
}

function statusClass(value) {
  return { approved: 'pending', paid: 'paid', reversed: 'refunded' }[value] || value;
}
</script>

<style scoped>
.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.commission-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:18px;}
.commission-kpis article{display:flex;flex-direction:column;gap:6px;padding:18px;border:1px solid var(--border);border-radius:16px;background:var(--bg-card);}
.commission-kpis span{color:var(--text-muted);font-size:10.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;}
.commission-kpis strong{font-size:24px;letter-spacing:-.03em;}
.commissions-filters{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:14px;margin-bottom:18px;border:1px solid var(--border);border-radius:14px;background:var(--bg-card);}
.commissions-filters .form-control{min-width:150px;flex:1 1 160px;}
.commissions-filters input[type="search"]{flex-basis:260px;}
.commission-bulk{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:var(--accent-soft);color:var(--accent);font-size:12.5px;font-weight:700;}
.pay-form{display:flex;flex-direction:column;gap:14px;padding-top:10px;}
</style>
