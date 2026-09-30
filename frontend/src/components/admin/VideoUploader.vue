<template>
  <div class="video-uploader">
    <div class="filter-tabs">
      <button type="button" class="filter-tab" :class="{ active: mode === 'url' }" :disabled="busy" @click="mode = 'url'">Por URL</button>
      <button type="button" class="filter-tab" :class="{ active: mode === 'file' }" :disabled="busy" @click="mode = 'file'">Arquivo</button>
    </div>

    <template v-if="mode === 'url'">
      <div class="form-group">
        <label :for="uid + '-url'">URL do vídeo</label>
        <input :id="uid + '-url'" class="form-control" v-model="url" placeholder="https://.../video.mp4" :disabled="busy">
        <p class="form-hint">O VdoCipher baixa o arquivo do link e processa em segundo plano.</p>
      </div>
    </template>

    <template v-else>
      <div class="form-group">
        <label>Arquivo</label>
        <FilePicker
          accept="video/*"
          title="Escolher vídeo"
          hint="ou arraste e solte"
          :file-name="file?.name"
          :disabled="busy"
          @change="onFilePicked"
        />
        <p class="form-hint">O envio vai direto para o VdoCipher, sem passar pelo limite de upload do servidor.</p>
      </div>
    </template>

    <div v-if="busy" class="upload-progress">
      <div class="progress-bar"><span :style="{ width: progress + '%' }"></span></div>
      <span class="form-hint">{{ statusText }}</span>
    </div>

    <span class="form-error" :class="{ show: error }">{{ error }}</span>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { api } from '../../api/http';
import FilePicker from './FilePicker.vue';

const props = defineProps({
  title: { type: String, default: '' },
  status: { type: String, default: '' },
});

const emit = defineEmits(['uploaded', 'state']);

const uid = `vu-${Math.random().toString(36).slice(2, 8)}`;

const mode = ref('url');
const url = ref('');
const file = ref(null);
const busy = ref(false);
const progress = ref(0);
const statusText = ref('Enviando...');
const error = ref('');

const canSubmit = computed(() => {
  if (busy.value || !props.title.trim()) {
    return false;
  }

  return mode.value === 'url' ? !!url.value.trim() : !!file.value;
});

watch([busy, canSubmit, mode], () => {
  emit('state', { busy: busy.value, canSubmit: canSubmit.value, mode: mode.value });
}, { immediate: true });

function onFilePicked(selected) {
  file.value = selected;
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
    const payload = { title: props.title.trim(), url: url.value.trim() };

    if (props.status) {
      payload.status = props.status;
    }

    const { data } = await api.post('/admin/videos/import-url', payload);
    url.value = '';
    emit('uploaded', data.data);
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
    const payload = { title: props.title.trim() };

    if (props.status) {
      payload.status = props.status;
    }

    const { data } = await api.post('/admin/videos/upload-credentials', payload);

    const body = data.data ?? {};
    const video = body.video ?? {};
    const upload = body.upload ?? {};

    if (!upload.uploadLink) {
      throw new Error('O provedor não retornou as credenciais de upload.');
    }

    statusText.value = 'Enviando arquivo...';
    await uploadToProvider(upload, file.value);

    statusText.value = 'Processando...';
    const synced = await api.post(`/admin/videos/${video.id}/sync`);
    file.value = null;
    emit('uploaded', synced.data.data ?? video);
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

defineExpose({ submit, busy, canSubmit });
</script>

<style scoped>
.video-uploader{display:flex;flex-direction:column;gap:14px;}
.upload-progress{display:flex;flex-direction:column;gap:6px;}
</style>
