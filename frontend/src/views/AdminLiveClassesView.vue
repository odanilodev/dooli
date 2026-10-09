<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Aulas ao vivo</h1>
        <p class="page-subtitle">{{ meta.total ?? items.length }} aula(s) — acesso liberado apenas para as turmas selecionadas.</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">{{ showForm ? 'Cancelar' : 'Nova aula ao vivo' }}</button>
    </div>

    <form v-if="showForm" class="panel" @submit.prevent="save">
      <h2 class="panel-title">{{ editingId ? `Editar aula #${editingId}` : 'Nova aula ao vivo' }}</h2>
      <p class="panel-desc">Defina o link da transmissão, data/hora e quais turmas poderão assistir.</p>

      <div class="form-row">
        <div class="form-group">
          <label for="lc-name">Título</label>
          <input id="lc-name" class="form-control" v-model="form.name" required placeholder="Mentoria ao vivo">
        </div>
        <div class="form-group">
          <label for="lc-status">Status</label>
          <select id="lc-status" class="form-control" v-model="form.status">
            <option value="active">Ativa</option>
            <option value="inactive">Inativa</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label for="lc-link">Link da transmissão</label>
        <input id="lc-link" class="form-control" v-model="form.link" required placeholder="https://meet.example.com/sala">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="lc-starts">Início</label>
          <input id="lc-starts" type="datetime-local" class="form-control" v-model="form.starts_at" required>
        </div>
        <div class="form-group">
          <label for="lc-duration">Duração (min)</label>
          <input id="lc-duration" type="number" min="0" class="form-control" v-model="form.duration">
        </div>
      </div>

      <div class="form-group">
        <label for="lc-description">Descrição</label>
        <textarea id="lc-description" class="form-control" rows="3" v-model="form.description"></textarea>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Imagem de capa</label>
          <FilePicker
            accept="image/png,image/jpeg,image/webp"
            title="Escolher imagem"
            hint="PNG/JPG/WebP · até 4 MB"
            :file-name="form.imageFile?.name"
            @change="onImagePicked"
          />
        </div>
        <div class="form-group">
          <label>Turmas com acesso</label>
          <div class="live-teams">
            <label v-for="team in teams" :key="team.id" class="live-team-option">
              <input type="checkbox" :value="team.id" v-model="form.team_ids">
              <span>{{ team.name }}</span>
            </label>
            <span v-if="!teams.length" class="admin-muted">Nenhuma turma cadastrada.</span>
          </div>
        </div>
      </div>

      <div v-if="imagePreview" class="live-preview">
        <img :src="imagePreview" alt="Pré-visualização">
      </div>

      <div>
        <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar aula ao vivo' }}</button>
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

      <div v-else-if="items.length === 0" class="empty-state">
        <strong>Nenhuma aula ao vivo encontrada</strong>
        <span>Cadastre a primeira transmissão.</span>
      </div>

      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Aula</th><th>Início</th><th>Duração</th><th>Turmas</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>
                <strong>{{ item.name }}</strong>
                <span class="admin-sub">#{{ item.id }}</span>
              </td>
              <td class="admin-muted">{{ formatDateTime(item.starts_at) }}</td>
              <td class="admin-muted">{{ item.duration ? `${item.duration} min` : '—' }}</td>
              <td class="admin-muted">{{ item.teams_count ?? 0 }} turma(s)</td>
              <td><span class="status-pill" :class="item.status">{{ item.status }}</span></td>
              <td class="admin-actions">
                <button class="btn btn-outline btn-sm" @click="openEdit(item)">Editar</button>
                <button class="btn btn-danger btn-sm" :disabled="busyId === item.id" @click="remove(item)">Excluir</button>
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
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';
import { formatDateTime, toDateTimeInput } from '../utils/format';
import FilePicker from '../components/admin/FilePicker.vue';

const toast = useToast();

const statusFilters = [
  { value: '', label: 'Todas' },
  { value: 'active', label: 'Ativas' },
  { value: 'inactive', label: 'Inativas' },
];

const items = ref([]);
const teams = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const status = ref('');
const loading = ref(true);
const busyId = ref(null);

const showForm = ref(false);
const editingId = ref(null);
const saving = ref(false);
const form = reactive({
  name: '',
  link: '',
  starts_at: '',
  duration: 60,
  status: 'active',
  description: '',
  team_ids: [],
  imageFile: null,
  imagePreview: '',
});

const imagePreview = computed(() => form.imagePreview);

function resetForm() {
  form.name = '';
  form.link = '';
  form.starts_at = '';
  form.duration = 60;
  form.status = 'active';
  form.description = '';
  form.team_ids = [];
  form.imageFile = null;

  if (form.imagePreview.startsWith('blob:')) {
    URL.revokeObjectURL(form.imagePreview);
  }

  form.imagePreview = '';
}

async function loadTeams() {
  try {
    const { data } = await api.get('/admin/teams', { params: { per_page: 100 } });
    teams.value = data.data ?? [];
  } catch (e) {
    teams.value = [];
  }
}

async function load() {
  loading.value = true;

  try {
    const { data } = await api.get('/admin/live-classes', {
      params: {
        status: status.value || undefined,
        page: meta.value.current_page || 1,
        per_page: 15,
      },
    });

    items.value = data.data ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar as aulas ao vivo.');
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

function openEdit(item) {
  editingId.value = item.id;
  form.name = item.name ?? '';
  form.link = item.link ?? '';
  form.starts_at = toDateTimeInput(item.starts_at);
  form.duration = item.duration ?? 0;
  form.status = item.status ?? 'active';
  form.description = item.description ?? '';
  form.team_ids = (item.teams ?? []).map((team) => team.id);
  form.imageFile = null;

  if (form.imagePreview.startsWith('blob:')) {
    URL.revokeObjectURL(form.imagePreview);
  }

  form.imagePreview = item.image_url ?? '';
  showForm.value = true;
}

async function save() {
  saving.value = true;

  try {
    const payload = {
      name: form.name,
      link: form.link,
      starts_at: form.starts_at,
      duration: Number(form.duration) || 0,
      status: form.status,
      description: form.description || null,
    };

    const url = editingId.value ? `/admin/live-classes/${editingId.value}` : '/admin/live-classes';

    if (form.imageFile) {
      const data = new FormData();
      Object.entries(payload).forEach(([key, value]) => {
        if (value !== null && value !== undefined) {
          data.append(key, value);
        }
      });
      form.team_ids.forEach((id) => data.append('team_ids[]', id));
      data.append('image', form.imageFile);

      if (editingId.value) {
        data.append('_method', 'PUT');
      }

      await api.post(url, data);
    } else {
      const body = { ...payload, team_ids: form.team_ids };

      if (editingId.value) {
        await api.put(url, body);
      } else {
        await api.post(url, body);
      }
    }

    showForm.value = false;
    editingId.value = null;
    resetForm();
    toast.success('Aula ao vivo salva.');
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível salvar a aula ao vivo.');
  } finally {
    saving.value = false;
  }
}

async function remove(item) {
  if (!window.confirm(`Excluir a aula ao vivo "${item.name}"?`)) {
    return;
  }

  busyId.value = item.id;

  try {
    await api.delete(`/admin/live-classes/${item.id}`);
    toast.success('Aula ao vivo excluída.');
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível excluir a aula ao vivo.');
  } finally {
    busyId.value = null;
  }
}

onMounted(() => {
  load();
  loadTeams();
});
</script>

<style scoped>
.live-teams{
  display:flex;flex-direction:column;gap:6px;max-height:150px;overflow-y:auto;
  border:1px solid var(--border);border-radius:10px;background:var(--bg-input);padding:10px 12px;
}
.live-team-option{display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--text-primary);cursor:pointer;}
.live-team-option input{accent-color:var(--accent);}
.live-preview{margin:4px 0 14px;}
.live-preview img{max-width:360px;width:100%;border-radius:12px;border:1px solid var(--border);object-fit:cover;}
</style>
