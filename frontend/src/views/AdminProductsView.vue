<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Produtos da loja</h1>
        <p class="page-subtitle">{{ meta.total ?? products.length }} produto(s) — cada compra libera os cursos das turmas selecionadas.</p>
      </div>
      <div class="admin-head-actions">
        <button v-if="publicStoreUrl" class="btn btn-outline" type="button" @click="copyStoreLink">Copiar link da loja</button>
        <button class="btn btn-primary" @click="toggleForm">{{ showForm ? 'Cancelar' : '+ Novo produto' }}</button>
      </div>
    </div>

    <form v-if="showForm" class="panel" @submit.prevent="save">
      <h2 class="panel-title">{{ editingId ? `Editar produto #${editingId}` : 'Novo produto' }}</h2>
      <p class="panel-desc">Defina as turmas liberadas na compra e as condições de venda.</p>

      <div class="form-row">
        <div class="form-group">
          <label for="p-title">Título</label>
          <input id="p-title" class="form-control" v-model="form.title" required placeholder="Curso de PHP">
        </div>
        <div class="form-group">
          <label for="p-status">Status</label>
          <select id="p-status" class="form-control" v-model="form.status">
            <option value="active">Ativo</option>
            <option value="inactive">Inativo</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="p-slug">Slug</label>
          <input id="p-slug" class="form-control" v-model="form.slug" placeholder="gerado pelo título quando vazio">
        </div>
        <div class="form-group">
          <label>Turmas que a compra libera</label>
          <TeamPicker v-model="selectedTeams" />
          <span class="form-hint">Obrigatório. A compra libera todos os cursos ativos das turmas selecionadas. A turma precisa ter ao menos um curso vinculado.</span>
        </div>
      </div>

      <div class="form-group">
        <label for="p-description">Descrição</label>
        <textarea id="p-description" class="form-control" rows="3" v-model="form.description"></textarea>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="p-price">Preço</label>
          <CurrencyInput id="p-price" v-model="form.price" required />
        </div>
        <div class="form-group">
          <label for="p-sale">Promoção</label>
          <CurrencyInput id="p-sale" v-model="form.sale" />
          <span class="form-hint">Deixe vazio ou use R$ 0,00 para vender pelo preço normal.</span>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="p-release">Lançamento</label>
          <input id="p-release" type="datetime-local" class="form-control" v-model="form.release_at">
          <span class="form-hint">Antes desta data o produto não aparece na loja.</span>
        </div>
        <div class="form-group">
          <label for="p-tags">Tags</label>
          <input id="p-tags" class="form-control" v-model="form.tags" placeholder="php, backend, anual">
          <span class="form-hint">Separe as tags por vírgula.</span>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="p-billing">Forma de cobrança</label>
          <select id="p-billing" class="form-control" v-model="form.billing_type">
            <option value="one_time">Pagamento único</option>
            <option value="subscription">Assinatura mensal</option>
          </select>
        </div>
        <div v-if="form.billing_type === 'one_time'" class="form-group">
          <label for="p-access">Duração do acesso</label>
          <select id="p-access" class="form-control" v-model="form.access_type">
            <option value="permanent">Permanente</option>
            <option value="limited">Por período</option>
          </select>
        </div>
        <div v-else class="form-group">
          <label for="p-grace">Tolerância de atraso (dias)</label>
          <input id="p-grace" type="number" min="0" max="90" class="form-control" v-model="form.grace_days">
        </div>
      </div>

      <div v-if="form.billing_type === 'one_time' && form.access_type === 'limited'" class="form-group">
        <label for="p-access-days">Dias de acesso após o pagamento</label>
        <input id="p-access-days" type="number" min="1" max="3650" class="form-control" v-model="form.access_duration_days">
        <span class="form-hint">Ex.: 7 para uma semana, contando da confirmação do pagamento.</span>
      </div>

      <div v-if="form.billing_type === 'one_time'" class="form-group">
        <label for="p-drip">Liberação do conteúdo</label>
        <select id="p-drip" class="form-control" v-model="form.drip_enabled">
          <option :value="false">Acesso total imediato</option>
          <option :value="true">Gradual (módulo por módulo)</option>
        </select>
        <span class="form-hint">
          A liberação gradual vale só para compras novas e conta a partir da confirmação do pagamento.
        </span>
      </div>

      <div v-if="form.billing_type === 'one_time' && form.drip_enabled" class="form-group">
        <label>Prazo de cada módulo</label>
        <div v-if="dripModules.length" class="drip-list">
          <div v-for="(item, index) in dripModules" :key="item.module_id" class="drip-row">
            <span class="drip-name">{{ item.name }}</span>
            <span v-if="index === 0" class="drip-fixed">Libera na hora</span>
            <label v-else class="drip-interval">
              <input
                type="number"
                min="0"
                max="365"
                class="form-control"
                v-model.number="item.interval_days"
              >
              <span>dia(s) após o módulo anterior</span>
            </label>
            <span class="drip-day">dia {{ dripReleaseDay(index) }}</span>
          </div>
        </div>
        <span v-else class="form-hint">
          Salve o produto para listar os módulos do curso. O padrão é 1 dia entre cada módulo.
        </span>
      </div>

      <div v-if="form.billing_type === 'subscription'" class="form-hint" style="margin-bottom:14px;">
        A primeira cobrança ocorre na autorização. O acesso fica ativo enquanto a assinatura estiver paga.
      </div>

      <div class="form-group">
        <label>Imagem do produto</label>
        <div v-if="imagePreviewUrl" class="product-image-preview">
          <img :src="imagePreviewUrl" alt="Pré-visualização do produto">
          <button type="button" class="product-image-remove" @click="clearImage">remover</button>
        </div>
        <FilePicker
          accept="image/png,image/jpeg,image/webp"
          title="Enviar imagem"
          hint="JPG, PNG ou WEBP até 4MB"
          :file-name="imageFile?.name || ''"
          @change="onImagePicked"
        />
        <input class="form-control" v-model="form.image" placeholder="ou cole uma URL https://..." @input="onImageUrlInput">
      </div>

      <div class="admin-head-actions">
        <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar produto' }}</button>
        <button type="button" class="btn btn-outline" :disabled="saving" @click="toggleForm">Cancelar</button>
      </div>
    </form>

    <template v-if="!showForm">

      <div class="catalog-toolbar">
        <div class="filter-tabs">
          <button
            v-for="tab in statusFilters"
            :key="tab.value"
            class="filter-tab"
            :class="{ active: status === tab.value }"
            @click="setStatus(tab.value)"
          >{{ tab.label }}</button>
        </div>
        <input class="form-control admin-search" type="search" v-model="search" placeholder="Buscar produto..." @input="scheduleSearch">
      </div>

      <p v-if="loading" class="page-subtitle">Carregando...</p>

      <div v-else-if="products.length === 0" class="empty-state">
        <strong>Nenhum produto encontrado</strong>
        <span>{{ search || status ? 'Ajuste os filtros selecionados.' : 'Crie o primeiro produto da loja.' }}</span>
        <button v-if="!search && !status" class="btn btn-primary" @click="openCreate">+ Novo produto</button>
      </div>

      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Produto</th><th>Turmas</th><th>Preço</th><th>Cobrança</th><th>Lançamento</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="product in products" :key="product.id">
              <td>
                <div class="product-table-title">
                  <img
                    v-if="product.image_url || product.image"
                    :src="product.image_url || product.image"
                    :alt="product.title"
                    class="product-thumb"
                  >
                  <span v-else class="product-thumb product-thumb-placeholder">{{ product.title.charAt(0).toUpperCase() }}</span>
                  <span>
                    <strong>{{ product.title }}</strong>
                    <span class="admin-sub">{{ product.slug }} · #{{ product.id }}</span>
                  </span>
                </div>
              </td>
              <td class="admin-muted">{{ product.teams?.length ?? 0 }} turma(s)</td>
              <td>
                <span class="product-price">
                  <span v-if="Number(product.sale) > 0" class="price-old">{{ formatCurrency(product.price) }}</span>
                  <span class="price-now">{{ formatCurrency(Number(product.sale) > 0 ? product.sale : product.price) }}</span>
                </span>
              </td>
              <td class="admin-muted">{{ product.is_subscription ? 'Mensal' : product.access_label }}</td>
              <td class="admin-muted">{{ product.release_at ? formatDateTime(product.release_at) : 'Imediato' }}</td>
              <td><span class="status-pill" :class="product.status">{{ statusLabel(product.status) }}</span></td>
              <td class="admin-actions">
                <button class="btn btn-outline btn-sm" :disabled="busyId === product.id" @click="openEdit(product)">Editar</button>
                <button class="btn btn-danger btn-sm" :disabled="busyId === product.id" @click="remove(product)">Excluir</button>
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

    <ImageCropper
      v-if="cropFile"
      :file="cropFile"
      @confirm="onCropConfirm"
      @cancel="onCropCancel"
    />

  </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';
import { formatCurrency, formatDateTime, toDateTimeInput } from '../utils/format';
import CurrencyInput from '../components/admin/CurrencyInput.vue';
import FilePicker from '../components/admin/FilePicker.vue';
import ImageCropper from '../components/admin/ImageCropper.vue';
import TeamPicker from '../components/admin/TeamPicker.vue';

const toast = useToast();

const statusFilters = [
  { value: '', label: 'Todos' },
  { value: 'active', label: 'Ativos' },
  { value: 'inactive', label: 'Inativos' },
];

const products = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(true);
const saving = ref(false);
const busyId = ref(null);
const status = ref('');
const search = ref('');

const showForm = ref(false);
const editingId = ref(null);
const selectedTeams = ref([]);
const imageFile = ref(null);
const imagePreview = ref('');
const cropFile = ref(null);
const publicStoreUrl = ref('');

const form = reactive({
  title: '',
  slug: '',
  description: '',
  price: '',
  sale: 0,
  status: 'active',
  release_at: '',
  tags: '',
  image: '',
  billing_type: 'one_time',
  billing_interval: 'month',
  access_type: 'permanent',
  access_duration_days: '',
  grace_days: 3,
  drip_enabled: false,
});

const dripModules = ref([]);

// Dia (contado da compra) em que o módulo libera: soma dos prazos até ele.
function dripReleaseDay(index) {
  return dripModules.value
    .slice(1, index + 1)
    .reduce((total, item) => total + (Number(item.interval_days) || 0), 0);
}

let searchTimer = null;

const imagePreviewUrl = computed(() => imagePreview.value || form.image || '');

async function load() {
  loading.value = true;

  try {
    const { data } = await api.get('/admin/store-products', {
      params: {
        search: search.value || undefined,
        status: status.value || undefined,
        page: meta.value.current_page || 1,
        per_page: 15,
      },
    });

    products.value = data.data ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar os produtos.');
  } finally {
    loading.value = false;
  }
}

async function loadPublicLink() {
  try {
    const { data } = await api.get('/admin/company/public-link');
    publicStoreUrl.value = data.data?.public_store_url || '';
  } catch (e) {
    /* company link is optional for this screen */
  }
}

async function copyStoreLink() {
  try {
    await navigator.clipboard.writeText(publicStoreUrl.value);
    toast.success('Link da loja copiado.');
  } catch (e) {
    toast.error('Não foi possível copiar o link.');
  }
}

function setStatus(value) {
  status.value = value;
  meta.value.current_page = 1;
  load();
}

function statusLabel(value) {
  return value === 'active' ? 'Ativo' : 'Inativo';
}

function scheduleSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    meta.value.current_page = 1;
    load();
  }, 350);
}

function goPage(page) {
  meta.value.current_page = page;
  load();
}

function resetForm() {
  form.title = '';
  form.slug = '';
  form.description = '';
  form.price = '';
  form.sale = 0;
  form.status = 'active';
  form.release_at = '';
  form.tags = '';
  form.image = '';
  form.billing_type = 'one_time';
  form.billing_interval = 'month';
  form.access_type = 'permanent';
  form.access_duration_days = '';
  form.grace_days = 3;
  form.drip_enabled = false;
  dripModules.value = [];
  selectedTeams.value = [];
  imageFile.value = null;
  cropFile.value = null;

  if (imagePreview.value.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreview.value);
  }

  imagePreview.value = '';
}

function openCreate() {
  editingId.value = null;
  resetForm();
  showForm.value = true;
}

function toggleForm() {
  if (showForm.value) {
    showForm.value = false;
    return;
  }

  openCreate();
}

async function openEdit(product) {
  busyId.value = product.id;

  try {
    const { data } = await api.get(`/admin/store-products/${product.id}`);
    const item = data.data;

    editingId.value = item.id;
    form.title = item.title ?? '';
    form.slug = item.slug ?? '';
    form.description = item.description ?? '';
    form.price = item.price ?? '';
    form.sale = item.sale ?? 0;
    form.status = item.status ?? 'active';
    form.release_at = toDateTimeInput(item.release_at);
    form.tags = (item.tags ?? []).join(', ');
    form.image = item.image ?? '';
    form.billing_type = item.billing_type || 'one_time';
    form.billing_interval = item.billing_interval || 'month';
    form.access_type = item.access_duration_days ? 'limited' : 'permanent';
    form.access_duration_days = item.access_duration_days ?? '';
    form.grace_days = item.grace_days ?? 3;
    form.drip_enabled = Boolean(item.drip_enabled);
    dripModules.value = (item.drip_modules ?? []).map((module) => ({ ...module }));
    selectedTeams.value =(item.teams ?? []).map((team) => ({ id: team.id, name: team.name }));
    imageFile.value = null;

    if (imagePreview.value.startsWith('blob:')) {
      URL.revokeObjectURL(imagePreview.value);
    }

    imagePreview.value = item.image_url || item.image || '';
    showForm.value = true;
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar o produto.');
  } finally {
    busyId.value = null;
  }
}

function onImagePicked(file) {
  if (file) {
    cropFile.value = file;
  }
}

function onCropConfirm(file) {
  if (imagePreview.value.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreview.value);
  }

  cropFile.value = null;
  imageFile.value = file;
  form.image = '';
  imagePreview.value = URL.createObjectURL(file);
}

function onCropCancel() {
  cropFile.value = null;
}

function onImageUrlInput() {
  if (imagePreview.value.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreview.value);
  }

  imageFile.value = null;
  imagePreview.value = form.image.trim();
}

function clearImage() {
  if (imagePreview.value.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreview.value);
  }

  imageFile.value = null;
  imagePreview.value = '';
  form.image = '';
}

function parseTags(value) {
  return value
    .split(',')
    .map((tag) => tag.trim())
    .filter(Boolean);
}

function payload() {
  const data = {
    title: form.title.trim(),
    description: form.description || null,
    price: Number(form.price) || 0,
    sale: Number(form.sale) || 0,
    status: form.status,
    release_at: form.release_at || null,
    tags: parseTags(form.tags),
    team_ids: selectedTeams.value.map((team) => team.id),
    billing_type: form.billing_type,
    billing_interval: form.billing_type === 'subscription' ? form.billing_interval : '',
    access_duration_days: form.billing_type === 'one_time' && form.access_type === 'limited'
      ? Number(form.access_duration_days) || ''
      : '',
    grace_days: Number(form.grace_days) || 0,
    drip_enabled: form.billing_type === 'one_time' && form.drip_enabled,
  };

  // O primeiro módulo sempre libera na hora; só os seguintes têm prazo.
  if (data.drip_enabled && dripModules.value.length) {
    data.module_drips = dripModules.value.slice(1).map((item) => ({
      module_id: item.module_id,
      interval_days: Math.max(0, Number(item.interval_days) || 0),
    }));
  }

  if (form.slug.trim()) {
    data.slug = form.slug.trim();
  }

  return data;
}

async function save() {
  if (!form.title.trim()) {
    toast.error('Informe o título do produto.');
    return;
  }

  if (!selectedTeams.value.length) {
    toast.error('Vincule pelo menos uma turma ao produto.');
    return;
  }

  if (form.billing_type === 'one_time' && form.access_type === 'limited' && Number(form.access_duration_days) < 1) {
    toast.error('Informe a quantidade de dias de acesso.');
    return;
  }

  saving.value = true;

  try {
    const url = editingId.value ? `/admin/store-products/${editingId.value}` : '/admin/store-products';
    const data = payload();

    if (imageFile.value) {
      const body = new FormData();

      Object.entries(data).forEach(([key, value]) => {
        if (key === 'team_ids' || key === 'tags') {
          value.forEach((item) => body.append(`${key}[]`, item));
        } else if (key === 'module_drips') {
          value.forEach((item, index) => {
            body.append(`module_drips[${index}][module_id]`, item.module_id);
            body.append(`module_drips[${index}][interval_days]`, item.interval_days);
          });
        } else if (typeof value === 'boolean') {
          body.append(key, value ? '1' : '0');
        } else if (value !== null && value !== undefined) {
          body.append(key, value);
        }
      });

      body.append('image_file', imageFile.value);

      if (editingId.value) {
        body.append('_method', 'PUT');
      }

      await api.post(url, body);
    } else {
      data.image = form.image.trim();

      if (editingId.value) {
        await api.put(url, data);
      } else {
        await api.post(url, data);
      }
    }

    showForm.value = false;
    editingId.value = null;
    resetForm();
    toast.success('Produto salvo.');
    await load();
  } catch (e) {
    // Mostra o motivo real da validação (primeiro erro de campo) em vez da mensagem genérica
    const primeiroErro = e?.errors ? Object.values(e.errors).flat()[0] : null;
    toast.error(primeiroErro || e?.message || 'Não foi possível salvar o produto.');
  } finally {
    saving.value = false;
  }
}

async function remove(product) {
  if (!window.confirm(`Excluir o produto "${product.title}"?`)) {
    return;
  }

  busyId.value = product.id;

  try {
    await api.delete(`/admin/store-products/${product.id}`);
    toast.success('Produto excluído.');
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível excluir o produto.');
  } finally {
    busyId.value = null;
  }
}

onMounted(() => {
  loadPublicLink();
  load();
});

onBeforeUnmount(() => {
  clearTimeout(searchTimer);

  if (imagePreview.value.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreview.value);
  }
});
</script>

<style scoped>
.product-table-title{display:flex;align-items:center;gap:10px;min-width:0;}
.product-thumb{width:48px;height:48px;border-radius:9px;object-fit:cover;border:1px solid var(--border);flex-shrink:0;}
.product-thumb-placeholder{display:flex;align-items:center;justify-content:center;background:var(--accent-soft);color:var(--accent);font-weight:800;font-size:16px;}
.product-image-preview{position:relative;max-width:360px;aspect-ratio:16/9;border-radius:12px;overflow:hidden;border:1px solid var(--border);margin-bottom:8px;}
.product-image-preview img{display:block;width:100%;height:100%;object-fit:cover;}
.product-image-remove{
  position:absolute;top:8px;right:8px;background:rgba(0,0,0,.62);color:#fff;border:none;border-radius:8px;
  padding:4px 9px;font-size:11.5px;font-weight:700;cursor:pointer;font-family:inherit;
}
.drip-list{display:flex;flex-direction:column;gap:8px;}
.drip-row{display:flex;align-items:center;gap:12px;padding:8px 12px;border:1px solid var(--border);border-radius:10px;}
.drip-name{flex:1;min-width:0;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.drip-fixed{font-size:12.5px;color:var(--text-muted);}
.drip-interval{display:flex;align-items:center;gap:8px;margin:0;font-size:12.5px;font-weight:400;color:var(--text-muted);}
.drip-interval .form-control{width:72px;}
.drip-day{font-size:12px;font-weight:700;color:var(--accent);white-space:nowrap;}
</style>
