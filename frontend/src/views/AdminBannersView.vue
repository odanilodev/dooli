<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Banners</h1>
        <p class="page-subtitle">{{ meta.total ?? banners.length }} banner(s) cadastrado(s) — controlam o destaque da home.</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">{{ showForm ? 'Cancelar' : 'Novo banner' }}</button>
    </div>

    <form v-if="showForm" class="panel" @submit.prevent="save">
      <h2 class="panel-title">{{ editingId ? `Editar banner #${editingId}` : 'Novo banner' }}</h2>
      <p class="panel-desc">A imagem enviada ocupa o destaque da home. Os textos abaixo compõem o hero.</p>

      <div class="form-row">
        <div class="form-group">
          <label for="b-name">Nome interno</label>
          <input id="b-name" class="form-control" v-model="form.name" required placeholder="Banner Principal">
        </div>
        <div class="form-group">
          <label for="b-status">Status</label>
          <select id="b-status" class="form-control" v-model="form.status">
            <option value="active">active</option>
            <option value="inactive">inactive</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="b-eyebrow">Eyebrow</label>
          <input id="b-eyebrow" class="form-control" v-model="form.eyebrow" placeholder="CURSO EM DESTAQUE">
        </div>
        <div class="form-group">
          <label for="b-quote">Frase</label>
          <input id="b-quote" class="form-control" v-model="form.quote" placeholder="Disciplina hoje, liberdade amanhã.">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="b-title">Título</label>
          <input id="b-title" class="form-control" v-model="form.title" placeholder="Do Zero ao Trader">
        </div>
        <div class="form-group">
          <label for="b-title-highlight">Destaque do título</label>
          <input id="b-title-highlight" class="form-control" v-model="form.title_highlight" placeholder="Consistente">
        </div>
      </div>

      <div class="form-group">
        <label for="b-description">Descrição</label>
        <textarea id="b-description" class="form-control" rows="3" v-model="form.description"></textarea>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="b-cta-label">Botão principal (texto)</label>
          <input id="b-cta-label" class="form-control" v-model="form.cta_label" placeholder="Continuar assistindo">
        </div>
        <div class="form-group">
          <label for="b-cta-link">Botão principal (link)</label>
          <input id="b-cta-link" class="form-control" v-model="form.cta_link" placeholder="/cursos">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="b-secondary-label">Botão secundário (texto)</label>
          <input id="b-secondary-label" class="form-control" v-model="form.secondary_label" placeholder="Ver detalhes">
        </div>
        <div class="form-group">
          <label for="b-secondary-link">Botão secundário (link)</label>
          <input id="b-secondary-link" class="form-control" v-model="form.secondary_link" placeholder="/cursos">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="b-sort">Ordem</label>
          <input id="b-sort" type="number" min="0" class="form-control" v-model="form.sort_order">
        </div>
        <div class="form-group">
          <label>Imagem</label>
          <FilePicker
            accept="image/png,image/jpeg,image/webp"
            title="Escolher imagem"
            hint="ou arraste e solte · 2560×800 (16:5) · PNG/JPG/WebP · até 4 MB"
            :file-name="form.imageFile?.name"
            @change="onImagePicked"
          />
        </div>
      </div>

      <div v-if="imagePreview" class="banner-preview">
        <img :src="imagePreview" alt="Pré-visualização do banner">
      </div>

      <div>
        <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar banner' }}</button>
      </div>
    </form>

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
    </div>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <div v-else-if="banners.length === 0" class="empty-state">
      <strong>Nenhum banner encontrado</strong>
      <span>Cadastre o primeiro destaque da home.</span>
    </div>

    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>Banner</th><th>Imagem</th><th>Ordem</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="banner in banners" :key="banner.id">
            <td>
              <strong>{{ banner.title || banner.name }}</strong>
              <span class="admin-sub">{{ banner.eyebrow || banner.name }} · #{{ banner.id }}</span>
            </td>
            <td>
              <img v-if="banner.image_url" :src="banner.image_url" :alt="banner.name" class="banner-thumb">
              <span v-else class="admin-muted">—</span>
            </td>
            <td class="admin-muted">{{ banner.sort_order }}</td>
            <td><span class="status-pill" :class="banner.status">{{ banner.status }}</span></td>
            <td class="admin-actions">
              <button class="btn btn-outline btn-sm" @click="openEdit(banner)">Editar</button>
              <button class="btn btn-danger btn-sm" :disabled="busyId === banner.id" @click="remove(banner)">Excluir</button>
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

  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';
import FilePicker from '../components/admin/FilePicker.vue';

const toast = useToast();

const statusFilters = [
  { value: '', label: 'Todos' },
  { value: 'active', label: 'Ativos' },
  { value: 'inactive', label: 'Inativos' },
];

const banners = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const status = ref('');
const loading = ref(true);
const busyId = ref(null);

const showForm = ref(false);
const editingId = ref(null);
const saving = ref(false);
const form = reactive({
  name: '',
  eyebrow: '',
  title: '',
  title_highlight: '',
  description: '',
  quote: '',
  cta_label: '',
  cta_link: '',
  secondary_label: '',
  secondary_link: '',
  sort_order: 0,
  status: 'active',
  imageFile: null,
  imagePreview: '',
});

const imagePreview = computed(() => form.imagePreview);

function resetForm() {
  form.name = '';
  form.eyebrow = '';
  form.title = '';
  form.title_highlight = '';
  form.description = '';
  form.quote = '';
  form.cta_label = '';
  form.cta_link = '';
  form.secondary_label = '';
  form.secondary_link = '';
  form.sort_order = 0;
  form.status = 'active';
  form.imageFile = null;

  if (form.imagePreview.startsWith('blob:')) {
    URL.revokeObjectURL(form.imagePreview);
  }

  form.imagePreview = '';
}

async function load() {
  loading.value = true;

  try {
    const { data } = await api.get('/admin/banners', {
      params: {
        status: status.value || undefined,
        page: meta.value.current_page || 1,
        per_page: 15,
      },
    });

    banners.value = data.data ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar os banners.');
  } finally {
    loading.value = false;
  }
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

function onImagePicked(file) {
  if (form.imagePreview.startsWith('blob:')) {
    URL.revokeObjectURL(form.imagePreview);
  }

  form.imageFile = file;
  form.imagePreview = file ? URL.createObjectURL(file) : '';
}

function openCreate() {
  if (showForm.value) {
    showForm.value = false;
    return;
  }

  editingId.value = null;
  resetForm();
  showForm.value = true;
}

function openEdit(banner) {
  editingId.value = banner.id;
  form.name = banner.name ?? '';
  form.eyebrow = banner.eyebrow ?? '';
  form.title = banner.title ?? '';
  form.title_highlight = banner.title_highlight ?? '';
  form.description = banner.description ?? '';
  form.quote = banner.quote ?? '';
  form.cta_label = banner.cta_label ?? '';
  form.cta_link = banner.cta_link ?? '';
  form.secondary_label = banner.secondary_label ?? '';
  form.secondary_link = banner.secondary_link ?? '';
  form.sort_order = banner.sort_order ?? 0;
  form.status = banner.status ?? 'active';
  form.imageFile = null;

  if (form.imagePreview.startsWith('blob:')) {
    URL.revokeObjectURL(form.imagePreview);
  }

  form.imagePreview = banner.image_url ?? '';
  showForm.value = true;
}

async function save() {
  saving.value = true;

  try {
    const payload = {
      name: form.name,
      eyebrow: form.eyebrow || null,
      title: form.title || null,
      title_highlight: form.title_highlight || null,
      description: form.description || null,
      quote: form.quote || null,
      cta_label: form.cta_label || null,
      cta_link: form.cta_link || null,
      secondary_label: form.secondary_label || null,
      secondary_link: form.secondary_link || null,
      sort_order: Number(form.sort_order) || 0,
      status: form.status,
    };

    if (form.imageFile) {
      const data = new FormData();
      Object.entries(payload).forEach(([key, value]) => {
        if (value !== null && value !== undefined) {
          data.append(key, value);
        }
      });
      data.append('image', form.imageFile);

      if (editingId.value) {
        data.append('_method', 'PUT');
      }

      await api.post(editingId.value ? `/admin/banners/${editingId.value}` : '/admin/banners', data);
    } else if (editingId.value) {
      await api.put(`/admin/banners/${editingId.value}`, payload);
    } else {
      await api.post('/admin/banners', payload);
    }

    showForm.value = false;
    editingId.value = null;
    resetForm();
    toast.success('Banner salvo.');
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível salvar o banner.');
  } finally {
    saving.value = false;
  }
}

async function remove(banner) {
  if (!window.confirm(`Excluir o banner "${banner.name}"?`)) {
    return;
  }

  busyId.value = banner.id;

  try {
    await api.delete(`/admin/banners/${banner.id}`);
    toast.success('Banner excluído.');
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível excluir o banner.');
  } finally {
    busyId.value = null;
  }
}

onMounted(load);
</script>

<style scoped>
.banner-preview{
  margin:4px 0 14px;
}
.banner-preview img{
  max-width:360px;width:100%;border-radius:12px;border:1px solid var(--border);
  background:#0a1e2a;object-fit:cover;
}
.banner-thumb{
  width:88px;height:50px;object-fit:cover;border-radius:8px;border:1px solid var(--border);
  background:#0a1e2a;display:block;
}
</style>
