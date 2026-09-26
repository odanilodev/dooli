<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Cursos</h1>
        <p class="page-subtitle">{{ meta.total ?? courses.length }} curso(s) cadastrado(s)</p>
      </div>
      <button class="btn btn-primary" @click="toggleCreate">
        {{ showCreate ? 'Cancelar' : 'Novo curso' }}
      </button>
    </div>

    <form v-if="showCreate" class="panel" @submit.prevent="create">
      <div>
        <h2 class="panel-title">Novo curso</h2>
        <p class="panel-desc">Depois de criar, você adiciona módulos, tópicos e aulas.</p>
      </div>

      <div class="form-group">
        <label for="nc-name">Nome</label>
        <input id="nc-name" class="form-control" v-model="form.name" required>
      </div>

      <div class="form-group">
        <label for="nc-description">Descrição</label>
        <textarea id="nc-description" class="form-control" rows="2" v-model="form.description"></textarea>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="nc-image">Imagem (url)</label>
          <input id="nc-image" class="form-control" v-model="form.image">
        </div>
        <div class="form-group">
          <label for="nc-status">Status</label>
          <select id="nc-status" class="form-control" v-model="form.status">
            <option value="active">active</option>
            <option value="inactive">inactive</option>
          </select>
        </div>
        <div class="form-group">
          <label for="nc-sort">Ordem</label>
          <input id="nc-sort" class="form-control" type="number" min="0" v-model.number="form.sort_order">
        </div>
      </div>

      <span class="form-error" :class="{ show: createError }">{{ createError }}</span>

      <div>
        <button class="btn btn-primary" :disabled="creating">
          {{ creating ? 'Criando...' : 'Criar curso' }}
        </button>
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

      <input
        class="form-control admin-search"
        type="search"
        v-model="search"
        placeholder="Buscar curso..."
      >
    </div>

    <span class="form-error" :class="{ show: error }">{{ error }}</span>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <div v-else-if="courses.length === 0" class="empty-state">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20v3H6.5A2.5 2.5 0 0 1 4 20.5z"/></svg>
      <strong>Nenhum curso encontrado</strong>
      <span>Crie o primeiro curso para começar.</span>
    </div>

    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Curso</th>
            <th>Status</th>
            <th>Módulos</th>
            <th>Criado</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="course in courses" :key="course.id">
            <td>
              <router-link class="admin-link" :to="{ name: 'admin-curso', params: { courseId: course.id } }">
                {{ course.name }}
              </router-link>
              <span class="admin-sub">#{{ course.id }}</span>
            </td>
            <td><span class="status-pill" :class="course.status">{{ course.status }}</span></td>
            <td>{{ course.modules?.length ?? 0 }}</td>
            <td class="admin-muted">{{ formatDate(course.created_at) }}</td>
            <td class="admin-actions">
              <router-link class="btn btn-outline btn-sm" :to="{ name: 'admin-curso', params: { courseId: course.id } }">
                Editar
              </router-link>
              <button class="btn btn-outline btn-sm" :disabled="busyId === course.id" @click="togglePublish(course)">
                {{ course.status === 'active' ? 'Despublicar' : 'Publicar' }}
              </button>
              <button class="btn btn-danger btn-sm" :disabled="busyId === course.id" @click="remove(course)">
                Excluir
              </button>
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
import { onMounted, reactive, ref, watch } from 'vue';
import { api } from '../api/http';
import { formatDate } from '../utils/format';

const statusFilters = [
  { value: '', label: 'Todos' },
  { value: 'active', label: 'Ativos' },
  { value: 'inactive', label: 'Inativos' },
];

const courses = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const search = ref('');
const status = ref('');
const loading = ref(true);
const error = ref('');
const busyId = ref(null);

const showCreate = ref(false);
const creating = ref(false);
const createError = ref('');
const form = reactive({ name: '', description: '', image: '', status: 'active', sort_order: 0 });

let debounce;

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get('/admin/courses', {
      params: {
        search: search.value || undefined,
        status: status.value || undefined,
        page: meta.value.current_page || 1,
        per_page: 15,
      },
    });

    courses.value = data.data ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar os cursos.';
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

async function create() {
  creating.value = true;
  createError.value = '';

  try {
    await api.post('/admin/courses', { ...form });
    showCreate.value = false;
    form.name = '';
    form.description = '';
    form.image = '';
    form.status = 'active';
    form.sort_order = 0;
    meta.value.current_page = 1;
    await load();
  } catch (e) {
    createError.value = e?.message || 'Não foi possível criar o curso.';
  } finally {
    creating.value = false;
  }
}

function toggleCreate() {
  showCreate.value = !showCreate.value;
  createError.value = '';
}

async function togglePublish(course) {
  busyId.value = course.id;

  try {
    const action = course.status === 'active' ? 'unpublish' : 'publish';
    await api.post(`/admin/courses/${course.id}/${action}`);
    course.status = course.status === 'active' ? 'inactive' : 'active';
  } catch (e) {
    error.value = e?.message || 'Não foi possível alterar o status.';
  } finally {
    busyId.value = null;
  }
}

async function remove(course) {
  if (!window.confirm(`Excluir o curso "${course.name}"?`)) {
    return;
  }

  busyId.value = course.id;

  try {
    await api.delete(`/admin/courses/${course.id}`);
    await load();
  } catch (e) {
    error.value = e?.message || 'Não foi possível excluir o curso.';
  } finally {
    busyId.value = null;
  }
}

watch(search, () => {
  clearTimeout(debounce);
  debounce = setTimeout(() => {
    meta.value.current_page = 1;
    load();
  }, 350);
});

onMounted(load);
</script>

<style scoped>
.admin-search{max-width:280px;}
.admin-table-wrap{overflow-x:auto;border:1px solid var(--border);border-radius:14px;background:var(--bg-card);box-shadow:var(--shadow-sm);}
.admin-table{width:100%;border-collapse:collapse;font-size:13.5px;}
.admin-table th{
  text-align:left;font-size:11px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;
  color:var(--text-muted);padding:12px 16px;border-bottom:1px solid var(--border);
}
.admin-table td{padding:14px 16px;border-bottom:1px solid var(--border);vertical-align:middle;}
.admin-table tbody tr:last-child td{border-bottom:none;}
.admin-table tbody tr:hover{background:var(--bg-card-alt);}
.admin-link{font-weight:700;color:var(--text-primary);}
.admin-link:hover{color:var(--accent);}
.admin-sub{display:block;font-size:11px;color:var(--text-muted);margin-top:2px;}
.admin-muted{color:var(--text-muted);font-size:12.5px;}
.admin-actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;}
.admin-pager{display:flex;align-items:center;justify-content:center;gap:14px;}
.btn-sm{padding:7px 12px;font-size:12px;border-radius:9px;}
.status-pill{font-size:11px;font-weight:800;padding:3px 9px;border-radius:999px;text-transform:uppercase;letter-spacing:.4px;}
.status-pill.active{background:rgba(34,197,94,.15);color:#22c55e;}
.status-pill.inactive{background:rgba(148,163,184,.18);color:#94a3b8;}
@media (max-width:640px){
  .admin-search{max-width:none;width:100%;}
  .admin-actions{justify-content:flex-start;}
}
</style>
