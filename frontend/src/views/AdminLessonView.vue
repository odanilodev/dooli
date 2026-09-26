<template>
  <main class="content">

    <div class="page-header">
      <div>
        <router-link class="admin-back" :to="backTo">← {{ backLabel }}</router-link>
        <h1 class="page-title">{{ lesson?.name || 'Aula' }}</h1>
        <p class="page-subtitle">Dados da aula, vídeo, materiais e transmissão ao vivo.</p>
      </div>
      <div class="admin-head-actions" v-if="lesson">
        <button class="btn btn-outline" :disabled="busy" @click="togglePublish">
          {{ published ? 'Despublicar' : 'Publicar' }}
        </button>
        <button class="btn btn-primary" :disabled="saving" @click="save">
          {{ saving ? 'Salvando...' : 'Salvar aula' }}
        </button>
      </div>
    </div>

    <span class="form-error" :class="{ show: error }">{{ error }}</span>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <template v-else-if="lesson">

      <div class="form-success" :class="{ show: saved }">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
        Aula salva.
      </div>

      <form class="panel" @submit.prevent="save">
        <h2 class="panel-title">Dados da aula</h2>

        <div class="form-group">
          <label for="l-name">Nome</label>
          <input id="l-name" class="form-control" v-model="form.name" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="l-topic">Tópico</label>
            <select id="l-topic" class="form-control" v-model.number="form.topic_id">
              <option v-for="t in topics" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label for="l-status">Status</label>
            <select id="l-status" class="form-control" v-model="form.status">
              <option value="active">active</option>
              <option value="inactive">inactive</option>
            </select>
          </div>
          <div class="form-group">
            <label for="l-sort">Ordem</label>
            <input id="l-sort" class="form-control" type="number" min="0" v-model.number="form.sort_order">
          </div>
        </div>

        <div class="form-group">
          <label for="l-description">Descrição</label>
          <textarea id="l-description" class="form-control" rows="2" v-model="form.description"></textarea>
        </div>

        <div class="form-group">
          <label for="l-content">Conteúdo (HTML)</label>
          <textarea id="l-content" class="form-control" rows="5" v-model="form.content" placeholder="<p>Conteúdo da aula...</p>"></textarea>
        </div>
      </form>

      <section class="section">
        <h2 class="section-title">Vídeo e materiais</h2>

        <div class="panel">
          <div class="form-row">
            <div class="form-group">
              <label for="l-video">Vídeo</label>
              <select id="l-video" class="form-control" v-model="form.video_id">
                <option value="">Sem vídeo</option>
                <option v-for="v in videos" :key="v.id" :value="v.id">#{{ v.id }} · {{ v.title }} ({{ v.player || '—' }})</option>
              </select>
            </div>
            <div class="form-group">
              <label for="l-document">Documento</label>
              <select id="l-document" class="form-control" v-model="form.document_id">
                <option value="">Sem documento</option>
                <option v-for="d in documents" :key="d.id" :value="d.id">#{{ d.id }} · {{ d.path }}</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Anexos</label>
            <p v-if="attachments.length === 0" class="form-hint">Nenhum anexo cadastrado.</p>
            <div v-else class="admin-checks">
              <label v-for="a in attachments" :key="a.id" class="form-check">
                <input type="checkbox" :value="a.id" v-model="selectedAttachments">
                <span>#{{ a.id }} · {{ a.path }}</span>
              </label>
            </div>
          </div>
        </div>
      </section>

      <section class="section">
        <h2 class="section-title">Transmissão ao vivo</h2>

        <div class="panel">
          <p class="panel-desc">Preencha para transformar esta aula em uma transmissão ao vivo (embed do player).</p>

          <div class="form-row">
            <div class="form-group">
              <label for="l-webinar">Link do embed</label>
              <input id="l-webinar" class="form-control" v-model="form.webinar_link" placeholder="https://player.vdocipher.com/live-v2?liveId=...">
            </div>
            <div class="form-group">
              <label for="l-webinar-at">Início agendado</label>
              <input id="l-webinar-at" class="form-control" type="datetime-local" v-model="form.webinar_starts_at">
            </div>
          </div>
          <p class="form-hint">Antes do horário, o aluno vê o card "EM BREVE"; depois, o player ao vivo.</p>
        </div>
      </section>

      <section class="section">
        <h2 class="section-title">Publicação</h2>
        <div class="panel">
          <div class="form-group">
            <label for="l-published">Publicado em</label>
            <input id="l-published" class="form-control" type="datetime-local" v-model="form.published_at">
          </div>
          <p class="form-hint">Vazio = publicado imediatamente quando o status é active. Para agendar, informe uma data futura.</p>
        </div>
      </section>

    </template>

  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api/http';
import { toDateTimeInput } from '../utils/format';

const route = useRoute();
const lessonId = Number(route.params.lessonId);

const lesson = ref(null);
const topics = ref([]);
const videos = ref([]);
const documents = ref([]);
const attachments = ref([]);
const selectedAttachments = ref([]);
const loading = ref(true);
const error = ref('');
const busy = ref(false);

const saving = ref(false);
const saved = ref(false);
const published = ref(false);
const form = reactive({
  name: '',
  topic_id: null,
  description: '',
  content: '',
  sort_order: 0,
  status: 'active',
  video_id: '',
  document_id: '',
  webinar_link: '',
  webinar_starts_at: '',
  published_at: '',
});

const backTo = computed(() =>
  form.topic_id ? { name: 'admin-topico', params: { topicId: form.topic_id } } : { name: 'admin-cursos' },
);
const backLabel = computed(() => (form.topic_id ? 'Voltar ao tópico' : 'Cursos'));

function syncForm() {
  const data = lesson.value;
  form.name = data.name ?? '';
  form.topic_id = data.topic_id ?? null;
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
  published.value = data.published_at != null && new Date(data.published_at).getTime() <= Date.now();
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get(`/admin/lessons/${lessonId}`);
    lesson.value = data.data;
    syncForm();
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar a aula.';
  } finally {
    loading.value = false;
  }
}

async function loadReferences() {
  try {
    const [topicsRes, videosRes, documentsRes, attachmentsRes] = await Promise.all([
      api.get('/admin/topics', { params: { per_page: 100 } }),
      api.get('/admin/videos', { params: { per_page: 100 } }),
      api.get('/admin/documents', { params: { per_page: 100 } }),
      api.get('/admin/attachments', { params: { per_page: 100 } }),
    ]);

    topics.value = topicsRes.data.data ?? [];
    videos.value = videosRes.data.data ?? [];
    documents.value = documentsRes.data.data ?? [];
    attachments.value = attachmentsRes.data.data ?? [];
  } catch (e) {
    /* silent: selects just stay empty */
  }
}

async function save() {
  saving.value = true;
  error.value = '';

  try {
    const payload = {
      name: form.name,
      topic_id: form.topic_id,
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
    };

    const { data } = await api.put(`/admin/lessons/${lessonId}`, payload);
    lesson.value = { ...lesson.value, ...data.data };
    syncForm();
    saved.value = true;
    setTimeout(() => { saved.value = false; }, 2500);
  } catch (e) {
    error.value = e?.message || 'Não foi possível salvar a aula.';
  } finally {
    saving.value = false;
  }
}

async function togglePublish() {
  busy.value = true;

  try {
    const action = published.value ? 'unpublish' : 'publish';
    const { data } = await api.post(`/admin/lessons/${lessonId}/${action}`);
    lesson.value = { ...lesson.value, ...data.data };
    syncForm();
  } catch (e) {
    error.value = e?.message || 'Não foi possível alterar a publicação.';
  } finally {
    busy.value = false;
  }
}

onMounted(() => {
  load();
  loadReferences();
});
</script>

<style scoped>
.admin-back{font-size:12.5px;font-weight:700;color:var(--text-muted);display:inline-block;margin-bottom:4px;}
.admin-back:hover{color:var(--accent);}
.admin-head-actions{display:flex;gap:10px;flex-wrap:wrap;}
.admin-checks{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;}
.admin-checks .form-check{align-items:flex-start;}
</style>
