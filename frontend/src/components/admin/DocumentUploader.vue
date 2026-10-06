<template>
  <div class="document-uploader">
    <div class="filter-tabs">
      <button type="button" class="filter-tab" :class="{ active: mode === 'file' }" :disabled="busy" @click="mode = 'file'">Enviar arquivo</button>
      <button type="button" class="filter-tab" :class="{ active: mode === 'link' }" :disabled="busy" @click="mode = 'link'">Link</button>
    </div>

    <template v-if="mode === 'file'">
      <div class="form-group">
        <label>Arquivo</label>
        <FilePicker
          accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.csv,.zip"
          title="Escolher documento"
          hint="ou arraste e solte · até 20&nbsp;MB"
          :file-name="file?.name"
          :disabled="busy"
          @change="onFilePicked"
        />
      </div>
    </template>

    <template v-else>
      <div class="form-group">
        <label :for="uid + '-url'">Link do documento</label>
        <input :id="uid + '-url'" class="form-control" v-model="url" placeholder="https://.../apostila.pdf" :disabled="busy">
        <p class="form-hint">PDFs são exibidos no player da aula; outros formatos ficam disponíveis para abrir/baixar.</p>
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
  status: { type: String, default: '' },
});

const emit = defineEmits(['uploaded', 'state']);

const uid = `du-${Math.random().toString(36).slice(2, 8)}`;

const mode = ref('file');
const file = ref(null);
const url = ref('');
const busy = ref(false);
const progress = ref(0);
const statusText = ref('Enviando...');
const error = ref('');

const canSubmit = computed(() => {
  if (busy.value) {
    return false;
  }

  return mode.value === 'file' ? !!file.value : !!url.value.trim();
});

watch([busy, canSubmit, mode], () => {
  emit('state', { busy: busy.value, canSubmit: canSubmit.value, mode: mode.value });
}, { immediate: true });

function onFilePicked(selected) {
  file.value = selected;
}

async function submit() {
  error.value = '';

  if (mode.value === 'file') {
    await submitFile();
  } else {
    await submitLink();
  }
}

async function submitFile() {
  busy.value = true;
  progress.value = 0;
  statusText.value = 'Enviando arquivo...';

  try {
    const body = new FormData();
    body.append('file', file.value);

    if (props.status) {
      body.append('status', props.status);
    }

    const { data } = await api.post('/admin/documents', body, {
      onUploadProgress: (event) => {
        if (event.lengthComputable) {
          progress.value = Math.round((event.loaded / event.total) * 100);
        }
      },
    });

    file.value = null;
    emit('uploaded', data.data);
  } catch (e) {
    error.value = e?.message || 'Não foi possível enviar o documento.';
  } finally {
    busy.value = false;
  }
}

async function submitLink() {
  busy.value = true;
  statusText.value = 'Salvando...';

  try {
    const payload = { url: url.value.trim() };

    if (props.status) {
      payload.status = props.status;
    }

    const { data } = await api.post('/admin/documents', payload);
    url.value = '';
    emit('uploaded', data.data);
  } catch (e) {
    error.value = e?.message || 'Não foi possível salvar o documento.';
  } finally {
    busy.value = false;
  }
}

defineExpose({ submit, busy, canSubmit });
</script>

<style scoped>
.document-uploader{display:flex;flex-direction:column;gap:14px;}
.upload-progress{display:flex;flex-direction:column;gap:6px;}
</style>
