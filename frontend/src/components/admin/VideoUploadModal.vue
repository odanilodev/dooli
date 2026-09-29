<template>
  <div class="admin-modal-backdrop" @mousedown="onBackdropDown" @click.self="backdropDown && close()">
    <div class="panel admin-modal">
      <div class="admin-panel-head">
        <h2 class="panel-title">Enviar vídeo ao VdoCipher</h2>
        <button class="admin-modal-close" :disabled="busy" @click="close">×</button>
      </div>

      <div class="filter-tabs">
        <button class="filter-tab" :class="{ active: mode === 'url' }" :disabled="busy" @click="mode = 'url'">Por URL</button>
        <button class="filter-tab" :class="{ active: mode === 'file' }" :disabled="busy" @click="mode = 'file'">Arquivo</button>
      </div>

      <div class="form-group">
        <label for="vu-title">Título</label>
        <input id="vu-title" class="form-control" v-model="title" :disabled="busy">
      </div>

      <template v-if="mode === 'url'">
        <div class="form-group">
          <label for="vu-url">URL do vídeo</label>
          <input id="vu-url" class="form-control" v-model="url" placeholder="https://.../video.mp4" :disabled="busy">
          <p class="form-hint">O VdoCipher baixa o arquivo do link e processa em segundo plano.</p>
        </div>
      </template>

      <template v-else>
        <div class="form-group">
          <label for="vu-file">Arquivo</label>
          <input id="vu-file" class="form-control" type="file" accept="video/*" :disabled="busy" @change="onFileChange">
          <p class="form-hint">O envio vai direto para o VdoCipher, sem passar pelo limite de upload do servidor.</p>
        </div>
      </template>

      <div v-if="busy" class="upload-progress">
        <div class="progress-bar"><span :style="{ width: progress + '%' }"></span></div>
        <span class="form-hint">{{ statusText }}</span>
      </div>

      <span class="form-error" :class="{ show: error }">{{ error }}</span>

      <div class="upload-actions">
        <button type="button" class="btn btn-outline" :disabled="busy" @click="close">Cancelar</button>
        <button type="button" class="btn btn-primary" :disabled="busy || !canSubmit" @click="submit">
          {{ busy ? statusText : (mode === 'url' ? 'Importar' : 'Enviar') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { api } from '../../api/http';

const emit = defineEmits(['close', 'created']);

const mode = ref('url');
const title = ref('');
const url = ref('');
const file = ref(null);
const busy = ref(false);
const progress = ref(0);
const statusText = ref('Enviando...');
const error = ref('');
const backdropDown = ref(false);

const canSubmit = computed(() => {
  if (!title.value.trim()) {
    return false;
  }

  return mode.value === 'url' ? !!url.value.trim() : !!file.value;
});

function onFileChange(event) {
  file.value = event.target.files?.[0] ?? null;
}

function onBackdropDown(event) {
  backdropDown.value = event.target === event.currentTarget;
}

function close() {
  if (!busy.value) {
    emit('close');
  }
}

async function submit() {
  error.value = '';

  if (mode.value === 'url') {
    await submitUrl();
  } else {
    await submitFile();
  }
}

async function submitUrl() {
  busy.value = true;
  statusText.value = 'Importando...';

  try {
    const { data } = await api.post('/admin/videos/import-url', {
      title: title.value.trim(),
      url: url.value.trim(),
    });
    emit('created', data.data);
  } catch (e) {
    error.value = e?.message || 'Não foi possível importar o vídeo.';
  } finally {
    busy.value = false;
  }
}

async function submitFile() {
  busy.value = true;
  progress.value = 0;
  statusText.value = 'Preparando envio...';

  try {
    const { data } = await api.post('/admin/videos/upload-credentials', {
      title: title.value.trim(),
    });

    const payload = data.data ?? {};
    const video = payload.video ?? {};
    const upload = payload.upload ?? {};

    if (!upload.uploadLink) {
      throw new Error('O provedor não retornou as credenciais de upload.');
    }

    statusText.value = 'Enviando arquivo...';
    await uploadToProvider(upload, file.value);

    statusText.value = 'Processando...';
    const synced = await api.post(`/admin/videos/${video.id}/sync`);
    emit('created', synced.data.data ?? video);
  } catch (e) {
    error.value = e?.message || 'Não foi possível enviar o vídeo.';
  } finally {
    busy.value = false;
  }
}

function uploadToProvider(upload, selected) {
  return new Promise((resolve, reject) => {
    const form = new FormData();

    ['policy', 'key', 'x-amz-signature', 'x-amz-algorithm', 'x-amz-date', 'x-amz-credential'].forEach((field) => {
      if (upload[field] != null) {
        form.append(field, upload[field]);
      }
    });

    form.append('success_action_status', '201');
    form.append('success_action_redirect', '');
    form.append('file', selected);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', upload.uploadLink);
    xhr.upload.onprogress = (event) => {
      if (event.lengthComputable) {
        progress.value = Math.round((event.loaded / event.total) * 100);
      }
    };
    xhr.onload = () => {
      if (xhr.status === 201 || xhr.status === 204) {
        resolve();
      } else {
        reject(new Error('Falha ao enviar o arquivo ao provedor.'));
      }
    };
    xhr.onerror = () => reject(new Error('Falha de rede ao enviar o arquivo ao provedor.'));
    xhr.send(form);
  });
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    close();
  }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<style scoped>
.upload-progress{display:flex;flex-direction:column;gap:6px;margin:4px 0 12px;}
.upload-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:4px;}
</style>
