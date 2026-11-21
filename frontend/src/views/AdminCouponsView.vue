<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Cupons</h1>
        <p class="page-subtitle">{{ meta.total ?? coupons.length }} cupom(ns) · descontos com validade para a loja</p>
      </div>
      <button class="btn btn-primary" @click="showForm ? closeForm() : openCreate()">{{ showForm ? 'Cancelar' : '+ Novo cupom' }}</button>
    </div>

    <form v-if="showForm" class="panel" @submit.prevent="save">
      <h2 class="panel-title">{{ editingId ? 'Editar cupom' : 'Novo cupom' }}</h2>
      <p class="panel-desc">Defina o desconto e, se quiser, o período em que o cupom pode ser usado.</p>

      <div class="form-row">
        <div class="form-group">
          <label for="cp-code">Código</label>
          <input id="cp-code" class="form-control" v-model="form.code" required maxlength="50" placeholder="BLACKFRIDAY" @input="form.code = form.code.toUpperCase().replace(/\s/g, '')">
        </div>
        <div class="form-group">
          <label for="cp-status">Status</label>
          <select id="cp-status" class="form-control" v-model="form.status">
            <option value="active">Ativo</option>
            <option value="inactive">Inativo</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="cp-type">Tipo de desconto</label>
          <select id="cp-type" class="form-control" v-model="form.type" @change="form.value = null; form.max_discount = null">
            <option value="percent">Percentual (%)</option>
            <option value="fixed">Valor fixo (R$)</option>
          </select>
        </div>
        <div class="form-group">
          <label for="cp-value">{{ form.type === 'percent' ? 'Desconto (%)' : 'Desconto (R$)' }}</label>
          <input v-if="form.type === 'percent'" id="cp-value" class="form-control" type="number" min="0.01" max="100" step="0.01" v-model.number="form.value" required placeholder="10">
          <CurrencyInput v-else id="cp-value" v-model="form.value" required />
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="cp-starts">Válido a partir de</label>
          <input id="cp-starts" class="form-control" type="datetime-local" v-model="form.starts_at">
          <small class="admin-muted">Vazio = já vale desde a criação.</small>
        </div>
        <div class="form-group">
          <label for="cp-expires">Expira em</label>
          <input id="cp-expires" class="form-control" type="datetime-local" v-model="form.expires_at" :min="form.starts_at || undefined">
          <small class="admin-muted">Vazio = nunca expira.</small>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="cp-limit">Limite total de usos</label>
          <input id="cp-limit" class="form-control" type="number" min="1" step="1" v-model.number="form.usage_limit" placeholder="Ilimitado">
        </div>
        <div class="form-group">
          <label for="cp-user-limit">Limite por aluno</label>
          <input id="cp-user-limit" class="form-control" type="number" min="1" step="1" v-model.number="form.per_user_limit" placeholder="Ilimitado">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="cp-min">Valor mínimo do pedido</label>
          <CurrencyInput id="cp-min" v-model="form.min_order_amount" placeholder="Sem mínimo" />
        </div>
        <div v-if="form.type === 'percent'" class="form-group">
          <label for="cp-max">Desconto máximo</label>
          <CurrencyInput id="cp-max" v-model="form.max_discount" placeholder="Sem teto" />
        </div>
      </div>

      <div class="form-group">
        <label>Produtos válidos</label>
        <small class="admin-muted">Nenhum marcado = vale para todos os produtos da loja.</small>
        <p v-if="!products.length" class="admin-muted">Nenhum produto cadastrado.</p>
        <div v-else class="coupon-products">
          <label v-for="product in products" :key="product.id" class="coupon-product">
            <input class="admin-check" type="checkbox" :value="product.id" v-model="form.store_product_ids">
            <span>{{ product.title }}</span>
          </label>
        </div>
      </div>

      <div>
        <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : (editingId ? 'Salvar alterações' : 'Criar cupom') }}</button>
      </div>
    </form>

    <template v-if="!showForm">

    <div class="catalog-toolbar">
      <div class="filter-tabs">
        <button
          v-for="tab in filters"
          :key="tab.value"
          class="filter-tab"
          :class="{ active: status === tab.value }"
          @click="setStatus(tab.value)"
        >{{ tab.label }}</button>
      </div>
      <input class="form-control admin-search" type="search" v-model="search" @input="onSearchInput" placeholder="Buscar código...">
    </div>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <div v-else-if="!coupons.length" class="empty-state">
      <strong>Nenhum cupom encontrado</strong>
      <span>{{ search || status ? 'Ajuste a busca ou o filtro selecionado.' : 'Crie o primeiro cupom para oferecer descontos na loja.' }}</span>
      <button v-if="!search && !status" class="btn btn-primary" @click="openCreate">+ Novo cupom</button>
    </div>

    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Cupom</th><th>Desconto</th><th>Validade</th><th>Usos</th><th>Situação</th><th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="coupon in coupons" :key="coupon.id">
            <td>
              <strong>{{ coupon.code }}</strong>
              <span v-if="Number(coupon.min_order_amount) > 0" class="admin-sub">mín. {{ formatCurrency(coupon.min_order_amount) }}</span>
            </td>
            <td>{{ discountLabel(coupon) }}</td>
            <td>
              <template v-if="coupon.starts_at || coupon.expires_at">
                <span v-if="coupon.starts_at">de {{ formatDateTime(coupon.starts_at) }}</span>
                <span v-if="coupon.expires_at" class="admin-sub">até {{ formatDateTime(coupon.expires_at) }}</span>
              </template>
              <span v-else class="admin-muted">Sem expiração</span>
            </td>
            <td>
              <span class="status-count">{{ coupon.usage_count ?? 0 }}</span>
              <span class="admin-muted">/ {{ coupon.usage_limit ?? '∞' }}</span>
            </td>
            <td><span class="status-pill" :class="situation(coupon).cls">{{ situation(coupon).label }}</span></td>
            <td class="coupon-actions">
              <button type="button" class="btn btn-outline btn-sm" :disabled="busyId === coupon.id" @click="openEdit(coupon)">Editar</button>
              <button type="button" class="btn btn-danger btn-sm" :disabled="busyId === coupon.id" @click="remove(coupon)">Excluir</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="admin-pager">
      <button class="btn btn-outline btn-sm" :disabled="meta.current_page <= 1" @click="goPage(meta.current_page - 1)">Anterior</button>
      <span class="admin-muted">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      <button class="btn btn-outline btn-sm" :disabled="meta.current_page >= meta.last_page" @click="goPage(meta.current_page + 1)">Próxima</button>
    </div>

    </template>

  </main>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';
import { formatCurrency, formatDateTime, toDateTimeInput } from '../utils/format';
import CurrencyInput from '../components/admin/CurrencyInput.vue';

const toast = useToast();

const filters = [
  { value: '', label: 'Todos' },
  { value: 'active', label: 'Ativos' },
  { value: 'inactive', label: 'Inativos' },
];

const coupons = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const products = ref([]);
const status = ref('');
const search = ref('');
const loading = ref(true);
const busyId = ref(null);

const showForm = ref(false);
const saving = ref(false);
const editingId = ref(null);

const emptyForm = () => ({
  code: '',
  status: 'active',
  type: 'percent',
  value: null,
  starts_at: '',
  expires_at: '',
  usage_limit: null,
  per_user_limit: null,
  min_order_amount: null,
  max_discount: null,
  store_product_ids: [],
});

const form = reactive(emptyForm());

let searchTimer = null;

function discountLabel(coupon) {
  if (coupon.type === 'percent') {
    const cap = Number(coupon.max_discount) > 0 ? ` (até ${formatCurrency(coupon.max_discount)})` : '';

    return `${Number(coupon.value)}%${cap}`;
  }

  return formatCurrency(coupon.value);
}

function situation(coupon) {
  if (coupon.status !== 'active') {
    return { label: 'Inativo', cls: 'inactive' };
  }

  if (coupon.expires_at && new Date(coupon.expires_at) < new Date()) {
    return { label: 'Expirado', cls: 'inactive' };
  }

  if (coupon.starts_at && new Date(coupon.starts_at) > new Date()) {
    return { label: 'Agendado', cls: 'scheduled' };
  }

  if (coupon.usage_limit !== null && coupon.usage_count >= coupon.usage_limit) {
    return { label: 'Esgotado', cls: 'inactive' };
  }

  return { label: 'Ativo', cls: 'active' };
}

async function load() {
  loading.value = true;

  try {
    const { data } = await api.get('/admin/coupons', {
      params: {
        search: search.value || undefined,
        status: status.value || undefined,
        page: meta.value.current_page || 1,
        per_page: 15,
      },
    });

    coupons.value = data.data ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar os cupons.');
  } finally {
    loading.value = false;
  }
}

async function loadProducts() {
  try {
    const { data } = await api.get('/admin/store-products', { params: { per_page: 100 } });

    products.value = data.data ?? [];
  } catch {
    products.value = [];
  }
}

function onSearchInput() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    meta.value.current_page = 1;
    load();
  }, 350);
}

function setStatus(value) {
  status.value = value;
  meta.value.current_page = 1;
  load();
}

function goPage(page) {
  meta.value.current_page = page;
  load();
}

function openCreate() {
  editingId.value = null;
  Object.assign(form, emptyForm());
  showForm.value = true;
}

function closeForm() {
  showForm.value = false;
  editingId.value = null;
}

async function openEdit(coupon) {
  busyId.value = coupon.id;

  try {
    const { data } = await api.get(`/admin/coupons/${coupon.id}`);
    const full = data.data;

    Object.assign(form, {
      code: full.code,
      status: full.status,
      type: full.type,
      value: Number(full.value),
      starts_at: toDateTimeInput(full.starts_at),
      expires_at: toDateTimeInput(full.expires_at),
      usage_limit: full.usage_limit,
      per_user_limit: full.per_user_limit,
      min_order_amount: Number(full.min_order_amount) > 0 ? Number(full.min_order_amount) : null,
      max_discount: Number(full.max_discount) > 0 ? Number(full.max_discount) : null,
      store_product_ids: (full.store_products ?? []).map((product) => product.id),
    });
    editingId.value = full.id;
    showForm.value = true;
  } catch (e) {
    toast.error(e?.message || 'Não foi possível abrir o cupom.');
  } finally {
    busyId.value = null;
  }
}

function toIso(local) {
  if (!local) {
    return null;
  }

  const date = new Date(local);

  if (Number.isNaN(date.getTime())) {
    return null;
  }

  // Horário "de parede" sem "Z": o Laravel interpreta no timezone da aplicação.
  // toISOString() enviava UTC e o servidor gravava o valor cru (+3h).
  const pad = (n) => String(n).padStart(2, '0');

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:00`;
}

function blankToNull(value) {
  return value === '' || value === undefined ? null : value;
}

async function save() {
  if (form.starts_at && form.expires_at && new Date(form.expires_at) < new Date(form.starts_at)) {
    toast.error('A data de expiração deve ser depois do início.');

    return;
  }

  saving.value = true;

  const payload = {
    code: form.code.trim(),
    status: form.status,
    type: form.type,
    value: form.value,
    starts_at: toIso(form.starts_at),
    expires_at: toIso(form.expires_at),
    usage_limit: blankToNull(form.usage_limit),
    per_user_limit: blankToNull(form.per_user_limit),
    min_order_amount: blankToNull(form.min_order_amount),
    max_discount: form.type === 'percent' ? blankToNull(form.max_discount) : null,
    store_product_ids: form.store_product_ids,
  };

  try {
    if (editingId.value) {
      await api.put(`/admin/coupons/${editingId.value}`, payload);
      toast.success('Cupom atualizado.');
    } else {
      await api.post('/admin/coupons', payload);
      toast.success('Cupom criado.');
    }

    closeForm();
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível salvar o cupom.');
  } finally {
    saving.value = false;
  }
}

async function remove(coupon) {
  if (!window.confirm(`Excluir o cupom "${coupon.code}"? Pedidos que já usaram o cupom não são alterados.`)) {
    return;
  }

  busyId.value = coupon.id;

  try {
    await api.delete(`/admin/coupons/${coupon.id}`);
    toast.success('Cupom excluído.');
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível excluir o cupom.');
  } finally {
    busyId.value = null;
  }
}

onMounted(() => {
  load();
  loadProducts();
});
</script>

<style scoped>
.coupon-products {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 6px 16px;
  max-height: 220px;
  overflow-y: auto;
  margin-top: 8px;
  padding: 10px 12px;
  border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
  border-radius: 10px;
}

.coupon-product {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 0.9rem;
}

.coupon-actions {
  display: flex;
  gap: 6px;
  justify-content: flex-end;
}

.status-pill.scheduled {
  background: rgba(245, 166, 35, 0.15);
  color: #f5a623;
}
</style>
