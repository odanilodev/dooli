<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Anexos</h1>
        <p class="page-subtitle">{{ meta.total ?? attachments.length }} anexo(s) cadastrado(s)</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">{{ showForm ? 'Cancelar' : 'Novo anexo' }}</button>
    </div>

    <form v-if="showForm" class="panel" @submit.prevent="save">
      <h2 class="panel-title">{{ editingId ? `Editar anexo #${editingId}` : 'Novo anexo' }}</h2>
      <p class="panel-desc">Informe o caminho do arquivo relativo ao storage privado da empresa.</p>

      <div class="form-group">
        <label for="a-path">Caminho (path)</label>
        <input id="a-path" class="form-control" v-model="form.path" required placeholder="anexos/material.zip">
      </div>

      <div class="form-group">
        <label for="a-status">Status</label>
        <select id="a-status" class="form-control" v-model="form.status">
          <option value="active">active</option>
          <option value="inactive">inactive</option>
        </select>
      </div>

      <span class="form-error" :class="{ show: formError }">{{ formError }}</span>

      <div>
        <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar anexo' }}</button>
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

    <span class="form-error" :class="{ show: error }">{{ error }}</span>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <div v-else-if="attachments.length === 0" class="empty-state">
      <strong>Nenhum anexo encontrado</strong>
      <span>Cadastre o primeiro anexo.</span>
    </div>

    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>Anexo</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="attachment in attachments" :key="attachment.id">
            <td>
              <strong>{{ attachment.path }}</strong>
              <span class="admin-sub">#{{ attachment.id }}</span>
            </td>
            <td><span class="status-pill" :class="attachment.status">{{ attachment.status }}</span></td>
            <td class="admin-actions">
              <button class="btn btn-outline btn-sm" @click="openEdit(attachment)">Editar</button>
              <button class="btn btn-danger btn-sm" :disabled="busyId === attachment.id" @click="remove(attachment)">Excluir</button>
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
import { onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';

const statusFilters = [
  { value: '', label: 'Todos' },
  { value: 'active', label: 'Ativos' },
  { value: 'inactive', label: 'Inativos' },
];

const attachments = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const status = ref('');
const loading = ref(true);
const error = ref('');
const busyId = ref(null);

const showForm = ref(false);
const editingId = ref(null);
const saving = ref(false);
const formError = ref('');
const form = reactive({ path: '', status: 'active' });

function resetForm() {
  form.path = '';
  form.status = 'active';
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get('/admin/attachments', {
      params: {
        status: status.value || undefined,
        page: meta.value.current_page || 1,
        per_page: 15,
      },
    });

    attachments.value = data.data ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar os anexos.';
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
  formError.value = '';
  showForm.value = true;
}

function openEdit(attachment) {
  editingId.value = attachment.id;
  form.path = attachment.path ?? '';
  form.status = attachment.status ?? 'active';
  formError.value = '';
  showForm.value = true;
}

async function save() {
  saving.value = true;
  formError.value = '';

  try {
    const payload = { path: form.path, status: form.status };

    if (editingId.value) {
      await api.put(`/admin/attachments/${editingId.value}`, payload);
    } else {
      await api.post('/admin/attachments', payload);
    }

    showForm.value = false;
    editingId.value = null;
    await load();
  } catch (e) {
    formError.value = e?.message || 'Não foi possível salvar o anexo.';
  } finally {
    saving.value = false;
  }
}

async function remove(attachment) {
  if (!window.confirm(`Excluir o anexo "${attachment.path}"?`)) {
    return;
  }

  busyId.value = attachment.id;

  try {
    await api.delete(`/admin/attachments/${attachment.id}`);
    await load();
  } catch (e) {
    error.value = e?.message || 'Não foi possível excluir o anexo.';
  } finally {
    busyId.value = null;
  }
}

onMounted(load);
</script>
