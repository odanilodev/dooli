<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Vídeos</h1>
        <p class="page-subtitle">{{ meta.total ?? videos.length }} vídeo(s) cadastrado(s)</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">{{ showForm ? 'Cancelar' : 'Novo vídeo' }}</button>
    </div>

    <form v-if="showForm" class="panel" @submit.prevent="save">
      <h2 class="panel-title">{{ editingId ? `Editar vídeo #${editingId}` : 'Novo vídeo' }}</h2>

      <div class="form-group">
        <label for="v-title">Título</label>
        <input id="v-title" class="form-control" v-model="form.title" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="v-player">Player</label>
          <select id="v-player" class="form-control" v-model="form.player">
            <option value="">—</option>
            <option value="vdocipher">vdocipher</option>
            <option value="panda">panda</option>
            <option value="vimeo">vimeo</option>
            <option value="fastpix">fastpix</option>
            <option value="external">external</option>
          </select>
        </div>
        <div class="form-group">
          <label for="v-time">Duração (s)</label>
          <input id="v-time" class="form-control" type="number" min="0" v-model.number="form.time">
        </div>
        <div class="form-group">
          <label for="v-status">Status</label>
          <select id="v-status" class="form-control" v-model="form.status">
            <option value="active">active</option>
            <option value="inactive">inactive</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="v-code">Código (code)</label>
          <input id="v-code" class="form-control" v-model="form.code" placeholder="ID no provedor">
        </div>
        <div class="form-group">
          <label for="v-link">Link</label>
          <input id="v-link" class="form-control" v-model="form.link" placeholder="URL (para player external)">
        </div>
      </div>

      <div class="form-group">
        <label for="v-image">Imagem (url)</label>
        <input id="v-image" class="form-control" v-model="form.image">
      </div>

      <span class="form-error" :class="{ show: formError }">{{ formError }}</span>

      <div>
        <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar vídeo' }}</button>
      </div>
    </form>

    <div class="catalog-toolbar">
      <div class="filter-tabs">
        <button
          v-for="tab in playerFilters"
          :key="tab.value"
          class="filter-tab"
          :class="{ active: player === tab.value }"
          @click="setPlayer(tab.value)"
        >{{ tab.label }}</button>
      </div>
      <input class="form-control admin-search" type="search" v-model="search" placeholder="Buscar vídeo...">
    </div>

    <span class="form-error" :class="{ show: error }">{{ error }}</span>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <div v-else-if="videos.length === 0" class="empty-state">
      <strong>Nenhum vídeo encontrado</strong>
      <span>Cadastre o primeiro vídeo para vinculá-lo às aulas.</span>
    </div>

    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>Vídeo</th><th>Player</th><th>Duração</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="video in videos" :key="video.id">
            <td>
              <strong>{{ video.title }}</strong>
              <span class="admin-sub">#{{ video.id }}<template v-if="video.code"> · {{ video.code }}</template></span>
            </td>
            <td>{{ video.player || '—' }}</td>
            <td class="admin-muted">{{ formatDuration(video.time) || '—' }}</td>
            <td><span class="status-pill" :class="video.status">{{ video.status }}</span></td>
            <td class="admin-actions">
              <button class="btn btn-outline btn-sm" @click="openEdit(video)">Editar</button>
              <button class="btn btn-outline btn-sm" :disabled="busyId === video.id" @click="testPlayback(video)">Testar</button>
              <button class="btn btn-danger btn-sm" :disabled="busyId === video.id" @click="remove(video)">Excluir</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="playback" class="panel">
      <div class="section-head">
        <h2 class="panel-title">Playback · vídeo #{{ playback.id }}</h2>
        <button class="btn btn-outline btn-sm" @click="playback = null">Fechar</button>
      </div>
      <pre class="admin-json">{{ JSON.stringify(playback.data, null, 2) }}</pre>
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
import { formatDuration } from '../utils/format';

const playerFilters = [
  { value: '', label: 'Todos' },
  { value: 'vdocipher', label: 'VdoCipher' },
  { value: 'panda', label: 'Panda' },
  { value: 'vimeo', label: 'Vimeo' },
  { value: 'fastpix', label: 'FastPix' },
  { value: 'external', label: 'External' },
];

const videos = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const search = ref('');
const player = ref('');
const loading = ref(true);
const error = ref('');
const busyId = ref(null);
const playback = ref(null);

const showForm = ref(false);
const editingId = ref(null);
const saving = ref(false);
const formError = ref('');
const form = reactive({ title: '', image: '', time: 0, status: 'active', player: '', code: '', link: '' });

let debounce;

function resetForm() {
  form.title = '';
  form.image = '';
  form.time = 0;
  form.status = 'active';
  form.player = '';
  form.code = '';
  form.link = '';
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get('/admin/videos', {
      params: {
        search: search.value || undefined,
        player: player.value || undefined,
        page: meta.value.current_page || 1,
        per_page: 15,
      },
    });

    videos.value = data.data ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar os vídeos.';
  } finally {
    loading.value = false;
  }
}

function setPlayer(value) {
  player.value = value;
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

function openEdit(video) {
  editingId.value = video.id;
  form.title = video.title ?? '';
  form.image = video.image ?? '';
  form.time = video.time ?? 0;
  form.status = video.status ?? 'active';
  form.player = video.player ?? '';
  form.code = video.code ?? '';
  form.link = video.link ?? '';
  formError.value = '';
  showForm.value = true;
}

async function save() {
  saving.value = true;
  formError.value = '';

  try {
    const payload = {
      title: form.title,
      image: form.image || null,
      time: form.time || 0,
      status: form.status,
      player: form.player || null,
      code: form.code || null,
      link: form.link || null,
    };

    if (editingId.value) {
      await api.put(`/admin/videos/${editingId.value}`, payload);
    } else {
      await api.post('/admin/videos', payload);
    }

    showForm.value = false;
    editingId.value = null;
    await load();
  } catch (e) {
    formError.value = e?.message || 'Não foi possível salvar o vídeo.';
  } finally {
    saving.value = false;
  }
}

async function testPlayback(video) {
  busyId.value = video.id;
  error.value = '';

  try {
    const { data } = await api.get(`/admin/videos/${video.id}/playback`);
    playback.value = { id: video.id, data: data.data ?? data };
  } catch (e) {
    error.value = e?.message || 'Não foi possível testar o playback.';
  } finally {
    busyId.value = null;
  }
}

async function remove(video) {
  if (!window.confirm(`Excluir o vídeo "${video.title}"?`)) {
    return;
  }

  busyId.value = video.id;

  try {
    await api.delete(`/admin/videos/${video.id}`);
    await load();
  } catch (e) {
    error.value = e?.message || 'Não foi possível excluir o vídeo.';
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
