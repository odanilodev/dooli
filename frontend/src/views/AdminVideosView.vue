<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Vídeos</h1>
        <p class="page-subtitle">{{ meta.total ?? videos.length }} vídeo(s) cadastrado(s)</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" :disabled="syncingAll" @click="refreshStatuses">
          {{ syncingAll ? 'Atualizando...' : 'Atualizar' }}
        </button>
        <button class="btn btn-primary" @click="openCreate">{{ showForm ? 'Cancelar' : 'Novo vídeo' }}</button>
      </div>
    </div>

    <span v-if="notice" class="form-success show">{{ notice }}</span>

    <form v-if="showForm" class="panel" @submit.prevent="onSubmit">
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
          <p v-if="form.player && !isVdoCipher" class="form-hint">Upload automático indisponível nesta integração; informe o código/link.</p>
        </div>
        <div v-if="!isVdoCipherNew" class="form-group">
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

      <div v-if="isVdoCipherNew" class="form-group">
        <label>Fonte do vídeo</label>
        <VideoUploader
          ref="uploader"
          :title="form.title"
          :status="form.status"
          @state="uploadState = $event"
          @uploaded="onUploaded"
        />
      </div>

      <template v-else>
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

        <div v-if="isVdoCipher && form.code" class="form-group">
          <label>Status no provedor</label>
          <div class="provider-status-row">
            <span class="provider-pill" :class="providerStatusClass(editingVideo?.provider_status)">
              {{ providerStatusLabel(editingVideo?.provider_status) || '—' }}
            </span>
            <button
              type="button"
              class="btn btn-outline btn-sm"
              :disabled="busyId === editingVideo?.id"
              @click="syncEditing"
            >{{ busyId === editingVideo?.id ? 'Sincronizando...' : 'Sincronizar' }}</button>
          </div>
        </div>
      </template>

      <span class="form-error" :class="{ show: formError }">{{ formError }}</span>

      <div>
        <button
          v-if="isVdoCipherNew"
          type="button"
          class="btn btn-primary"
          :disabled="!uploadState.canSubmit"
          @click="uploader?.submit()"
        >{{ uploadState.busy ? 'Enviando...' : 'Enviar vídeo' }}</button>
        <button v-else class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar vídeo' }}</button>
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
              <span
                v-if="video.provider_status"
                class="provider-pill"
                :class="providerStatusClass(video.provider_status)"
              >{{ providerStatusLabel(video.provider_status) }}</span>
            </td>
            <td>{{ video.player || '—' }}</td>
            <td class="admin-muted">{{ formatDuration(video.time) || '—' }}</td>
            <td><span class="status-pill" :class="video.status">{{ video.status }}</span></td>
            <td class="admin-actions">
              <button class="btn btn-outline btn-sm" @click="openEdit(video)">Editar</button>
              <button
                v-if="video.player === 'vdocipher' && video.code"
                class="btn btn-outline btn-sm"
                :disabled="busyId === video.id"
                @click="syncVideo(video)"
              >Sincronizar</button>
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
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { api } from '../api/http';
import { formatDuration } from '../utils/format';
import VideoUploader from '../components/admin/VideoUploader.vue';

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
const syncingAll = ref(false);
const notice = ref('');

const uploader = ref(null);
const uploadState = ref({ busy: false, canSubmit: false });

const providerStatusLabels = {
  'pre-upload': 'Aguardando envio',
  preupload: 'Aguardando envio',
  queued: 'Na fila',
  processing: 'Processando',
  ready: 'Pronto',
};

function providerStatusLabel(status) {
  if (!status) {
    return '';
  }

  return providerStatusLabels[String(status).toLowerCase().trim()] || status;
}

function providerStatusClass(status) {
  return String(status).toLowerCase().trim() === 'ready' ? 'is-ready' : 'is-pending';
}

async function refreshStatuses() {
  syncingAll.value = true;
  error.value = '';
  notice.value = '';

  try {
    const { data } = await api.post('/admin/videos/sync');
    const summary = data.data ?? {};

    notice.value = `Verificados: ${summary.checked ?? 0} · prontos: ${summary.ready ?? 0} · processando: ${summary.pending ?? 0}`
      + (summary.failed ? ` · falhas: ${summary.failed}` : '') + '.';

    await load();
  } catch (e) {
    error.value = e?.message || 'Não foi possível atualizar os status.';
  } finally {
    syncingAll.value = false;
  }
}

function onUploaded(video) {
  showForm.value = false;
  editingId.value = null;
  editingVideo.value = null;
  error.value = '';
  notice.value = `Vídeo "${video.title}" enviado. O processamento continua em segundo plano.`;
  load();
}

async function syncVideo(video) {
  busyId.value = video.id;
  error.value = '';
  notice.value = '';

  try {
    const { data } = await api.post(`/admin/videos/${video.id}/sync`);
    const updated = data.data ?? {};

    if (editingVideo.value && editingVideo.value.id === video.id) {
      editingVideo.value = { ...editingVideo.value, ...updated };
    }

    notice.value = updated.provider_status === 'ready'
      ? `Vídeo pronto${updated.time ? ` (${formatDuration(updated.time)})` : ''}.`
      : `Status: ${providerStatusLabel(updated.provider_status)}.`;

    await load();

    return updated;
  } catch (e) {
    error.value = e?.message || 'Não foi possível sincronizar o vídeo.';
  } finally {
    busyId.value = null;
  }
}

const showForm = ref(false);
const editingId = ref(null);
const editingVideo = ref(null);
const saving = ref(false);
const formError = ref('');
const form = reactive({ title: '', image: '', time: 0, status: 'active', player: '', code: '', link: '' });

const isVdoCipher = computed(() => form.player === 'vdocipher');
const isVdoCipherNew = computed(() => !editingId.value && isVdoCipher.value);

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
  editingVideo.value = null;
  resetForm();
  formError.value = '';
  showForm.value = true;
}

function openEdit(video) {
  editingId.value = video.id;
  editingVideo.value = video;
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

function onSubmit() {
  if (isVdoCipherNew.value) {
    if (uploadState.value.canSubmit) {
      uploader.value?.submit();
    }

    return;
  }

  save();
}

function syncEditing() {
  if (editingVideo.value) {
    syncVideo(editingVideo.value);
  }
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
    editingVideo.value = null;
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

<style scoped>
.header-actions{display:flex;gap:10px;flex-wrap:wrap;}
.provider-pill{display:inline-block;margin-top:5px;font-size:10.5px;font-weight:700;letter-spacing:.02em;padding:1px 8px;border-radius:999px;border:1px solid var(--border);color:var(--text-secondary);}
.provider-pill.is-pending{color:#eab308;border-color:rgba(234,179,8,.45);}
.provider-pill.is-ready{color:#17c993;border-color:rgba(23,201,147,.45);}
.provider-status-row{display:flex;align-items:center;gap:10px;}
</style>
