<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Documentos</h1>
        <p class="page-subtitle">{{ meta.total ?? documents.length }} documento(s) cadastrado(s)</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">{{ showForm ? 'Cancelar' : 'Novo documento' }}</button>
    </div>

    <form v-if="showForm" class="panel" @submit.prevent="save">
      <h2 class="panel-title">{{ editingId ? `Editar documento #${editingId}` : 'Novo documento' }}</h2>
      <p v-if="!editingId" class="panel-desc">Envie um arquivo (até 20 MB) ou informe um link. O documento fica disponível para vincular nas aulas.</p>
      <p v-else class="panel-desc">Ajuste o caminho, o link ou o status do documento.</p>

      <template v-if="!editingId">
        <DocumentUploader
          ref="documentUploader"
          :status="form.status"
          @state="uploadState = $event"
          @uploaded="onUploaded"
        />
        <div>
          <button type="button" class="btn btn-primary" :disabled="!uploadState.canSubmit" @click="documentUploader?.submit()">
            {{ uploadState.busy ? 'Enviando...' : 'Salvar documento' }}
          </button>
        </div>
      </template>

      <template v-else>
        <div class="form-group">
          <label for="d-path">Caminho (path)</label>
          <input id="d-path" class="form-control" v-model="form.path" placeholder="materiais/aula-01.pdf">
          <p class="form-hint">Preencha apenas se o arquivo já estiver no storage privado da empresa.</p>
        </div>

        <div class="form-group">
          <label for="d-url">Link (url)</label>
          <input id="d-url" class="form-control" v-model="form.url" placeholder="https://.../apostila.pdf">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="d-image">Imagem</label>
            <div v-if="form.imagePreview" class="doc-image-preview">
              <img :src="form.imagePreview" alt="Pré-visualização">
              <button type="button" class="btn btn-outline btn-sm" @click="clearImage">remover</button>
            </div>
            <FilePicker
              accept="image/png,image/jpeg,image/webp"
              title="Enviar imagem"
              hint="JPG, PNG ou WEBP até 4MB"
              :file-name="form.imageFile?.name || ''"
              @change="onImagePicked"
            />
            <input id="d-image" class="form-control" v-model="form.image" placeholder="ou cole uma URL https://...">
          </div>
          <div class="form-group">
            <label for="d-status">Status</label>
            <select id="d-status" class="form-control" v-model="form.status">
              <option value="active">active</option>
              <option value="inactive">inactive</option>
            </select>
          </div>
        </div>

        <div>
          <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar documento' }}</button>
        </div>
      </template>
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
    </div>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <div v-else-if="documents.length === 0" class="empty-state">
      <strong>Nenhum documento encontrado</strong>
      <span>Cadastre o primeiro documento.</span>
    </div>

    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>Documento</th><th>Imagem</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="document in documents" :key="document.id">
            <td>
              <strong>{{ document.path || document.url }}</strong>
              <span class="admin-sub">{{ document.url ? 'Link' : 'Arquivo' }} · #{{ document.id }}</span>
            </td>
            <td>
              <img v-if="document.image_url || document.image" :src="document.image_url || document.image" :alt="document.path || 'Documento'" class="doc-thumb">
              <span v-else class="admin-muted">—</span>
            </td>
            <td><span class="status-pill" :class="document.status">{{ document.status }}</span></td>
            <td class="admin-actions">
              <button class="btn btn-outline btn-sm" @click="openEdit(document)">Editar</button>
              <button class="btn btn-danger btn-sm" :disabled="busyId === document.id" @click="remove(document)">Excluir</button>
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
import DocumentUploader from '../components/admin/DocumentUploader.vue';
import FilePicker from '../components/admin/FilePicker.vue';

const toast = useToast();

const statusFilters = [
  { value: '', label: 'Todos' },
  { value: 'active', label: 'Ativos' },
  { value: 'inactive', label: 'Inativos' },
];

const documents = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const status = ref('');
const loading = ref(true);
const busyId = ref(null);

const showForm = ref(false);
const editingId = ref(null);
const saving = ref(false);
const documentUploader = ref(null);
const uploadState = ref({ busy: false, canSubmit: false });
const form = reactive({ path: '', url: '', image: '', status: 'active', imageFile: null, imagePreview: '' });

function resetForm() {
  form.path = '';
  form.url = '';
  form.image = '';
  form.status = 'active';
  revokePreview();
  form.imageFile = null;
  form.imagePreview = '';
}

function revokePreview() {
  if (form.imagePreview?.startsWith('blob:')) {
    URL.revokeObjectURL(form.imagePreview);
  }
}

function onImagePicked(file) {
  revokePreview();
  form.imageFile = file;
  form.imagePreview = file ? URL.createObjectURL(file) : '';
}

function clearImage() {
  revokePreview();
  form.imageFile = null;
  form.imagePreview = '';
  form.image = '';
}

async function load() {
  loading.value = true;

  try {
    const { data } = await api.get('/admin/documents', {
      params: {
        status: status.value || undefined,
        page: meta.value.current_page || 1,
        per_page: 15,
      },
    });

    documents.value = data.data ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar os documentos.');
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

function openCreate() {
  if (showForm.value) {
    showForm.value = false;
    return;
  }

  editingId.value = null;
  resetForm();
  showForm.value = true;
}

function openEdit(document) {
  editingId.value = document.id;
  form.path = document.path ?? '';
  form.url = document.url ?? '';
  form.image = document.image ?? '';
  form.status = document.status ?? 'active';
  revokePreview();
  form.imageFile = null;
  form.imagePreview = document.image_url || document.image || '';
  showForm.value = true;
}

async function onUploaded() {
  showForm.value = false;
  toast.success('Documento salvo.');
  await load();
}

async function save() {
  if (!editingId.value) {
    return;
  }

  saving.value = true;

  try {
    const url = `/admin/documents/${editingId.value}`;

    if (form.imageFile) {
      const body = new FormData();
      body.append('path', form.path || '');
      body.append('url', form.url || '');
      body.append('image', form.image || '');
      body.append('status', form.status);
      body.append('image_file', form.imageFile);
      body.append('_method', 'PUT');
      await api.post(url, body);
    } else {
      await api.put(url, {
        path: form.path || null,
        url: form.url || null,
        image: form.image || null,
        status: form.status,
      });
    }

    showForm.value = false;
    editingId.value = null;
    toast.success('Documento salvo.');
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível salvar o documento.');
  } finally {
    saving.value = false;
  }
}

async function remove(document) {
  if (!window.confirm(`Excluir o documento "${document.path || document.url}"?`)) {
    return;
  }

  busyId.value = document.id;

  try {
    await api.delete(`/admin/documents/${document.id}`);
    toast.success('Documento excluído.');
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível excluir o documento.');
  } finally {
    busyId.value = null;
  }
}

onMounted(load);
</script>

<style scoped>
.doc-image-preview{display:flex;align-items:center;gap:10px;margin-bottom:8px;}
.doc-image-preview img{width:96px;height:54px;border-radius:8px;object-fit:cover;border:1px solid var(--border);}
.doc-thumb{width:64px;height:36px;border-radius:6px;object-fit:cover;border:1px solid var(--border);}
</style>
