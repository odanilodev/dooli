<template>
  <div class="admin-modal-backdrop" @click.self="emit('close')">
    <div class="panel admin-modal">
      <div class="admin-panel-head">
        <h2 class="panel-title">Copiar aula existente</h2>
        <button class="admin-modal-close" @click="emit('close')" aria-label="Fechar">×</button>
      </div>

      <p class="form-hint">Uma cópia com conteúdo, vídeo, documento e anexos será criada neste tópico. A aula original não é alterada.</p>

      <input class="form-control" v-model="search" placeholder="Buscar aula..." @input="onSearch">

      <span class="form-error" :class="{ show: error }">{{ error }}</span>

      <p v-if="loading" class="page-subtitle">Carregando aulas...</p>
      <p v-else-if="!available.length" class="form-hint">Nenhuma aula encontrada.</p>

      <div v-else class="admin-attach-list">
        <button v-for="lesson in available" :key="lesson.id" class="admin-attach-item" :disabled="copying" @click="copy(lesson)">
          <span class="lp-name">
            <span>{{ lesson.name }}</span>
            <small v-if="topicName(lesson.topic_id)" class="lp-origin">{{ topicName(lesson.topic_id) }}</small>
          </span>
          <span class="status-pill" :class="lesson.status">{{ statusLabel(lesson.status) }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { api } from '../../api/http';
import { statusLabel } from '../../utils/adminLabels';

const props = defineProps({
  topicId: { type: Number, required: true },
  excludeIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'copied']);

const lessons = ref([]);
const topics = ref([]);
const search = ref('');
const loading = ref(true);
const copying = ref(false);
const error = ref('');

let timer = null;

const available = computed(() => lessons.value.filter((lesson) => !props.excludeIds.includes(lesson.id)));

function topicName(id) {
  return topics.value.find((topic) => topic.id === id)?.name ?? '';
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const params = { per_page: 100 };
    if (search.value.trim()) {
      params.search = search.value.trim();
    }

    const [l, t] = await Promise.all([
      api.get('/admin/lessons', { params }),
      topics.value.length ? Promise.resolve(null) : api.get('/admin/topics', { params: { per_page: 100 } }),
    ]);
    lessons.value = l.data.data ?? [];

    if (t) {
      topics.value = t.data.data ?? [];
    }
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar as aulas.';
  } finally {
    loading.value = false;
  }
}

function onSearch() {
  clearTimeout(timer);
  timer = setTimeout(load, 320);
}

async function copy(lesson) {
  copying.value = true;
  error.value = '';

  try {
    const { data } = await api.get(`/admin/lessons/${lesson.id}`);
    const src = data.data;

    const { data: created } = await api.post('/admin/lessons', {
      name: src.name,
      description: src.description,
      content: src.content,
      status: src.status,
      topic_id: props.topicId,
      video_id: src.video?.id ?? null,
      document_id: src.document?.id ?? null,
      attachment_ids: (src.attachments ?? []).map((a) => a.id),
    });

    emit('copied', {
      ...created.data,
      video: src.video ?? null,
      document: src.document ?? null,
      attachments: src.attachments ?? [],
    });
    emit('close');
  } catch (e) {
    error.value = e?.message || 'Não foi possível copiar a aula.';
  } finally {
    copying.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.lp-name{display:flex;flex-direction:column;gap:1px;min-width:0;text-align:left;}
.lp-origin{font-size:11px;color:var(--text-muted);font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.admin-attach-item:disabled{opacity:.6;cursor:wait;}
</style>
