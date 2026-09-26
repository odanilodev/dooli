<template>
  <form class="panel admin-panel" @submit.prevent="save">
    <div class="admin-panel-head">
      <h2 class="panel-title">Aula</h2>
      <div class="admin-panel-actions">
        <button type="button" class="btn btn-outline btn-sm" :disabled="busy" @click="togglePublish">
          {{ isPublished ? 'Despublicar' : 'Publicar' }}
        </button>
        <button type="submit" class="btn btn-primary btn-sm" :disabled="saving">
          {{ saving ? 'Salvando...' : 'Salvar' }}
        </button>
      </div>
    </div>

    <div class="form-success" :class="{ show: saved }">Aula salva.</div>
    <span class="form-error" :class="{ show: error }">{{ error }}</span>

    <div class="form-group">
      <label for="lp-name">Nome</label>
      <input id="lp-name" class="form-control" v-model="form.name" required>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="lp-status">Status</label>
        <select id="lp-status" class="form-control" v-model="form.status">
          <option value="active">active</option>
          <option value="inactive">inactive</option>
        </select>
      </div>
      <div class="form-group">
        <label for="lp-sort">Ordem</label>
        <input id="lp-sort" class="form-control" type="number" min="0" v-model.number="form.sort_order">
      </div>
    </div>

    <div class="form-group">
      <label for="lp-description">Descrição</label>
      <textarea id="lp-description" class="form-control" rows="2" v-model="form.description"></textarea>
    </div>

    <div class="form-group">
      <label for="lp-content">Conteúdo (HTML)</label>
      <textarea id="lp-content" class="form-control" rows="4" v-model="form.content" placeholder="<p>Conteúdo da aula...</p>"></textarea>
    </div>

    <div class="admin-block">
      <div class="admin-block-head"><strong>Vídeo</strong></div>
      <select class="form-control" v-model="form.video_id">
        <option value="">Sem vídeo</option>
        <option v-for="v in videos" :key="v.id" :value="v.id">#{{ v.id }} · {{ v.title }} ({{ v.player || '—' }})</option>
      </select>
      <button type="button" class="admin-link-btn" @click="showVideo = !showVideo">
        {{ showVideo ? '− fechar' : '+ novo vídeo' }}
      </button>
      <div v-if="showVideo" class="admin-inline">
        <input class="form-control" v-model="newVideo.title" placeholder="Título">
        <select class="form-control" v-model="newVideo.player">
          <option value="vdocipher">vdocipher</option>
          <option value="panda">panda</option>
          <option value="vimeo">vimeo</option>
          <option value="fastpix">fastpix</option>
          <option value="external">external</option>
        </select>
        <input class="form-control" v-model="newVideo.code" placeholder="Código (code)">
        <input class="form-control" v-model="newVideo.link" placeholder="Link (external)">
        <button type="button" class="btn btn-primary btn-sm" :disabled="creatingVideo" @click="createVideo">
          {{ creatingVideo ? '...' : 'Criar e vincular' }}
        </button>
      </div>
    </div>

    <div class="admin-block">
      <div class="admin-block-head"><strong>Documento</strong></div>
      <select class="form-control" v-model="form.document_id">
        <option value="">Sem documento</option>
        <option v-for="d in documents" :key="d.id" :value="d.id">#{{ d.id }} · {{ d.path }}</option>
      </select>
      <button type="button" class="admin-link-btn" @click="showDocument = !showDocument">
        {{ showDocument ? '− fechar' : '+ novo documento' }}
      </button>
      <div v-if="showDocument" class="admin-inline">
        <input class="form-control" v-model="newDocument.path" placeholder="Caminho (path)">
        <input class="form-control" v-model="newDocument.image" placeholder="Imagem (url, opcional)">
        <button type="button" class="btn btn-primary btn-sm" :disabled="creatingDocument" @click="createDocument">
          {{ creatingDocument ? '...' : 'Criar e vincular' }}
        </button>
      </div>
    </div>

    <div class="admin-block">
      <div class="admin-block-head"><strong>Anexos</strong></div>
      <div v-if="attachments.length" class="admin-checks">
        <label v-for="a in attachments" :key="a.id" class="form-check">
          <input type="checkbox" :value="a.id" v-model="selectedAttachments">
          <span>#{{ a.id }} · {{ a.path }}</span>
        </label>
      </div>
      <p v-else class="form-hint">Nenhum anexo cadastrado.</p>
      <button type="button" class="admin-link-btn" @click="showAttachment = !showAttachment">
        {{ showAttachment ? '− fechar' : '+ novo anexo' }}
      </button>
      <div v-if="showAttachment" class="admin-inline">
        <input class="form-control" v-model="newAttachment.path" placeholder="Caminho (path)">
        <button type="button" class="btn btn-primary btn-sm" :disabled="creatingAttachment" @click="createAttachment">
          {{ creatingAttachment ? '...' : 'Criar e vincular' }}
        </button>
      </div>
    </div>

    <div class="admin-block">
      <div class="admin-block-head"><strong>Transmissão ao vivo</strong></div>
      <div class="form-group">
        <label for="lp-webinar">Link do embed</label>
        <input id="lp-webinar" class="form-control" v-model="form.webinar_link" placeholder="https://player.vdocipher.com/live-v2?liveId=...">
      </div>
      <div class="form-group">
        <label for="lp-webinar-at">Início agendado</label>
        <input id="lp-webinar-at" class="form-control" type="datetime-local" v-model="form.webinar_starts_at">
      </div>
    </div>

    <div class="admin-block">
      <div class="admin-block-head"><strong>Publicação</strong></div>
      <div class="form-group">
        <label for="lp-published">Publicado em</label>
        <input id="lp-published" class="form-control" type="datetime-local" v-model="form.published_at">
      </div>
      <p class="form-hint">Vazio + status active = publicado agora. Data futura = agendado.</p>
    </div>
  </form>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { api } from '../../api/http';
import { toDateTimeInput } from '../../utils/format';

const props = defineProps({ lesson: { type: Object, required: true } });
const emit = defineEmits(['changed']);

const form = reactive({
  name: '', description: '', content: '', sort_order: 0, status: 'active',
  video_id: '', document_id: '', webinar_link: '', webinar_starts_at: '', published_at: '',
});
const selectedAttachments = ref([]);
const videos = ref([]);
const documents = ref([]);
const attachments = ref([]);

const saving = ref(false);
const saved = ref(false);
const error = ref('');
const busy = ref(false);
const isPublished = ref(true);

const showVideo = ref(false);
const showDocument = ref(false);
const showAttachment = ref(false);
const newVideo = reactive({ title: '', player: 'vdocipher', code: '', link: '' });
const newDocument = reactive({ path: '', image: '' });
const newAttachment = reactive({ path: '' });
const creatingVideo = ref(false);
const creatingDocument = ref(false);
const creatingAttachment = ref(false);

function sync() {
  const data = props.lesson;
  form.name = data.name ?? '';
  form.description = data.description ?? '';
  form.content = data.content ?? '';
  form.sort_order = data.sort_order ?? 0;
  form.status = data.status ?? 'active';
  form.video_id = data.video?.id ?? '';
  form.document_id = data.document?.id ?? '';
  form.webinar_link = data.webinar_link ?? '';
  form.webinar_starts_at = toDateTimeInput(data.webinar_starts_at);
  form.published_at = toDateTimeInput(data.published_at);
  selectedAttachments.value = (data.attachments ?? []).map((a) => a.id);
  isPublished.value = data.status === 'active';
}

async function loadReferences() {
  try {
    const [v, d, a] = await Promise.all([
      api.get('/admin/videos', { params: { per_page: 100 } }),
      api.get('/admin/documents', { params: { per_page: 100 } }),
      api.get('/admin/attachments', { params: { per_page: 100 } }),
    ]);
    videos.value = v.data.data ?? [];
    documents.value = d.data.data ?? [];
    attachments.value = a.data.data ?? [];
  } catch (e) {
    /* silent */
  }
}

async function save() {
  saving.value = true;
  error.value = '';

  try {
    await api.put(`/admin/lessons/${props.lesson.id}`, {
      name: form.name,
      description: form.description || null,
      content: form.content || null,
      sort_order: form.sort_order,
      status: form.status,
      video_id: form.video_id === '' ? null : Number(form.video_id),
      document_id: form.document_id === '' ? null : Number(form.document_id),
      webinar_link: form.webinar_link || null,
      webinar_starts_at: form.webinar_starts_at || null,
      published_at: form.published_at || null,
      attachment_ids: selectedAttachments.value,
    });

    saved.value = true;
    setTimeout(() => { saved.value = false; }, 2000);
    emit('changed');
  } catch (e) {
    error.value = e?.message || 'Não foi possível salvar a aula.';
  } finally {
    saving.value = false;
  }
}

async function togglePublish() {
  busy.value = true;

  try {
    await api.post(`/admin/lessons/${props.lesson.id}/${isPublished.value ? 'unpublish' : 'publish'}`);
    emit('changed');
  } catch (e) {
    error.value = e?.message || 'Não foi possível alterar a publicação.';
  } finally {
    busy.value = false;
  }
}

async function createVideo() {
  if (!newVideo.title.trim()) {
    error.value = 'Informe o título do vídeo.';
    return;
  }

  creatingVideo.value = true;
  error.value = '';

  try {
    const { data } = await api.post('/admin/videos', {
      title: newVideo.title.trim(),
      player: newVideo.player,
      code: newVideo.code || null,
      link: newVideo.link || null,
      status: 'active',
    });
    videos.value = [...videos.value, data.data];
    form.video_id = data.data.id;
    newVideo.title = '';
    newVideo.code = '';
    newVideo.link = '';
    showVideo.value = false;
  } catch (e) {
    error.value = e?.message || 'Não foi possível criar o vídeo.';
  } finally {
    creatingVideo.value = false;
  }
}

async function createDocument() {
  if (!newDocument.path.trim()) {
    error.value = 'Informe o caminho do documento.';
    return;
  }

  creatingDocument.value = true;
  error.value = '';

  try {
    const { data } = await api.post('/admin/documents', {
      path: newDocument.path.trim(),
      image: newDocument.image || null,
      status: 'active',
    });
    documents.value = [...documents.value, data.data];
    form.document_id = data.data.id;
    newDocument.path = '';
    newDocument.image = '';
    showDocument.value = false;
  } catch (e) {
    error.value = e?.message || 'Não foi possível criar o documento.';
  } finally {
    creatingDocument.value = false;
  }
}

async function createAttachment() {
  if (!newAttachment.path.trim()) {
    error.value = 'Informe o caminho do anexo.';
    return;
  }

  creatingAttachment.value = true;
  error.value = '';

  try {
    const { data } = await api.post('/admin/attachments', {
      path: newAttachment.path.trim(),
      status: 'active',
    });
    attachments.value = [...attachments.value, data.data];
    selectedAttachments.value = [...selectedAttachments.value, data.data.id];
    newAttachment.path = '';
    showAttachment.value = false;
  } catch (e) {
    error.value = e?.message || 'Não foi possível criar o anexo.';
  } finally {
    creatingAttachment.value = false;
  }
}

watch(() => props.lesson, sync, { immediate: true });

onMounted(loadReferences);
</script>

<style scoped>
.admin-block{display:flex;flex-direction:column;gap:10px;border-top:1px dashed var(--border);padding-top:14px;}
.admin-block-head strong{font-size:12.5px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);}
.admin-link-btn{align-self:flex-start;background:none;border:none;color:var(--accent);font-weight:700;font-size:12px;cursor:pointer;padding:0;font-family:inherit;}
.admin-inline{display:flex;flex-direction:column;gap:8px;background:var(--bg-card-alt);border:1px solid var(--border);border-radius:12px;padding:12px;}
</style>
