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
      <p class="panel-desc">Informe o caminho do arquivo relativo ao storage privado da empresa.</p>

      <div class="form-group">
        <label for="d-path">Caminho (path)</label>
        <input id="d-path" class="form-control" v-model="form.path" required placeholder="materiais/aula-01.pdf">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="d-image">Imagem (url)</label>
          <input id="d-image" class="form-control" v-model="form.image">
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
              <strong>{{ document.path }}</strong>
              <span class="admin-sub">#{{ document.id }}</span>
            </td>
            <td class="admin-muted">{{ document.image || '—' }}</td>
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
const form = reactive({ path: '', image: '', status: 'active' });

function resetForm() {
  form.path = '';
  form.image = '';
  form.status = 'active';
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
  form.image = document.image ?? '';
  form.status = document.status ?? 'active';
  showForm.value = true;
}

async function save() {
  saving.value = true;

  try {
    const payload = { path: form.path, image: form.image || null, status: form.status };

    if (editingId.value) {
      await api.put(`/admin/documents/${editingId.value}`, payload);
    } else {
      await api.post('/admin/documents', payload);
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
  if (!window.confirm(`Excluir o documento "${document.path}"?`)) {
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
