<template>
  <main class="content">

    <div class="page-header">
      <div>
        <router-link class="admin-back" :to="backTo">← {{ backLabel }}</router-link>
        <h1 class="page-title">{{ topic?.name || 'Tópico' }}</h1>
        <p class="page-subtitle">Edite os dados do tópico e organize as aulas.</p>
      </div>
      <div class="admin-head-actions" v-if="topic">
        <button class="btn btn-primary" :disabled="saving" @click="save">
          {{ saving ? 'Salvando...' : 'Salvar tópico' }}
        </button>
      </div>
    </div>

    <span class="form-error" :class="{ show: error }">{{ error }}</span>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <template v-else-if="topic">

      <div class="form-success" :class="{ show: saved }">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
        Tópico salvo.
      </div>

      <form class="panel" @submit.prevent="save">
        <h2 class="panel-title">Dados do tópico</h2>

        <div class="form-group">
          <label for="t-name">Nome</label>
          <input id="t-name" class="form-control" v-model="form.name" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="t-module">Módulo</label>
            <select id="t-module" class="form-control" v-model.number="form.module_id">
              <option v-for="m in modules" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label for="t-status">Status</label>
            <select id="t-status" class="form-control" v-model="form.status">
              <option value="active">active</option>
              <option value="inactive">inactive</option>
            </select>
          </div>
          <div class="form-group">
            <label for="t-sort">Ordem</label>
            <input id="t-sort" class="form-control" type="number" min="0" v-model.number="form.sort_order">
          </div>
        </div>
      </form>

      <section class="section">
        <div class="section-head">
          <h2 class="section-title">Aulas ({{ lessons.length }})</h2>
        </div>

        <div class="panel">
          <div class="form-row admin-add-row">
            <div class="form-group">
              <label for="nl-name">Nova aula</label>
              <input id="nl-name" class="form-control" v-model="newLessonName" placeholder="Nome da aula">
            </div>
            <div class="form-group admin-add-action">
              <label for="nl-status">Status</label>
              <select id="nl-status" class="form-control" v-model="newLessonStatus">
                <option value="active">active</option>
                <option value="inactive">inactive</option>
              </select>
            </div>
            <div class="form-group admin-add-action">
              <button class="btn btn-primary" :disabled="creating" @click="createLesson">
                {{ creating ? 'Criando...' : 'Criar aula' }}
              </button>
            </div>
          </div>

          <span class="form-error" :class="{ show: lessonError }">{{ lessonError }}</span>
        </div>

        <div v-if="lessons.length === 0" class="empty-state">
          <strong>Nenhuma aula ainda</strong>
          <span>Crie a primeira aula deste tópico.</span>
        </div>

        <div v-else class="admin-module-list">
          <article v-for="(lesson, index) in lessons" :key="lesson.id" class="admin-module">
            <div class="admin-module-head">
              <div class="admin-module-title">
                <strong>{{ lesson.name }}</strong>
                <span class="admin-sub">#{{ lesson.id }}</span>
              </div>
              <div class="admin-module-actions">
                <span class="status-pill" :class="lesson.status">{{ lesson.status }}</span>
                <router-link class="btn btn-outline btn-sm" :to="{ name: 'admin-aula', params: { lessonId: lesson.id } }">
                  Abrir
                </router-link>
                <button class="btn btn-outline btn-sm" :disabled="index === 0" @click="moveLesson(index, -1)">↑</button>
                <button class="btn btn-outline btn-sm" :disabled="index === lessons.length - 1" @click="moveLesson(index, 1)">↓</button>
                <button class="btn btn-danger btn-sm" @click="removeLesson(lesson)">Excluir</button>
              </div>
            </div>
          </article>
        </div>
      </section>

    </template>

  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api/http';

const route = useRoute();
const topicId = Number(route.params.topicId);

const topic = ref(null);
const lessons = ref([]);
const modules = ref([]);
const loading = ref(true);
const error = ref('');

const saving = ref(false);
const saved = ref(false);
const form = reactive({ name: '', module_id: null, status: 'active', sort_order: 0 });

const newLessonName = ref('');
const newLessonStatus = ref('active');
const creating = ref(false);
const lessonError = ref('');

const backTo = computed(() =>
  topic.value?.module_id
    ? { name: 'admin-modulo', params: { moduleId: topic.value.module_id } }
    : { name: 'admin-cursos' },
);
const backLabel = computed(() => (topic.value?.module_id ? 'Voltar ao módulo' : 'Cursos'));

function syncForm() {
  form.name = topic.value.name ?? '';
  form.module_id = topic.value.module_id ?? null;
  form.status = topic.value.status ?? 'active';
  form.sort_order = topic.value.sort_order ?? 0;
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get(`/admin/topics/${topicId}`);
    topic.value = data.data;
    lessons.value = data.data.lessons ?? [];
    syncForm();
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar o tópico.';
  } finally {
    loading.value = false;
  }
}

async function loadModules() {
  try {
    const { data } = await api.get('/admin/modules', { params: { per_page: 100 } });
    modules.value = data.data ?? [];
  } catch (e) {
    /* silent */
  }
}

async function save() {
  saving.value = true;
  error.value = '';

  try {
    const { data } = await api.put(`/admin/topics/${topicId}`, { ...form });
    topic.value = { ...topic.value, ...data.data, lessons: lessons.value };
    syncForm();
    saved.value = true;
    setTimeout(() => { saved.value = false; }, 2500);
  } catch (e) {
    error.value = e?.message || 'Não foi possível salvar o tópico.';
  } finally {
    saving.value = false;
  }
}

async function createLesson() {
  if (!newLessonName.value.trim()) {
    lessonError.value = 'Informe o nome da aula.';
    return;
  }

  creating.value = true;
  lessonError.value = '';

  try {
    await api.post('/admin/lessons', {
      name: newLessonName.value.trim(),
      topic_id: topicId,
      status: newLessonStatus.value,
    });

    newLessonName.value = '';
    await load();
  } catch (e) {
    lessonError.value = e?.message || 'Não foi possível criar a aula.';
  } finally {
    creating.value = false;
  }
}

async function removeLesson(lesson) {
  if (!window.confirm(`Excluir a aula "${lesson.name}"?`)) {
    return;
  }

  lessonError.value = '';

  try {
    await api.delete(`/admin/lessons/${lesson.id}`);
    await load();
  } catch (e) {
    lessonError.value = e?.message || 'Não foi possível excluir a aula.';
  }
}

async function moveLesson(index, delta) {
  const target = index + delta;

  if (target < 0 || target >= lessons.value.length) {
    return;
  }

  const reordered = [...lessons.value];
  [reordered[index], reordered[target]] = [reordered[target], reordered[index]];
  lessons.value = reordered;

  try {
    await api.put(`/admin/topics/${topicId}/lessons/reorder`, {
      ids: reordered.map((l) => l.id),
    });
  } catch (e) {
    lessonError.value = e?.message || 'Não foi possível reordenar as aulas.';
    await load();
  }
}

onMounted(() => {
  load();
  loadModules();
});
</script>

<style scoped>
.admin-back{font-size:12.5px;font-weight:700;color:var(--text-muted);display:inline-block;margin-bottom:4px;}
.admin-back:hover{color:var(--accent);}
.admin-head-actions{display:flex;gap:10px;flex-wrap:wrap;}
.admin-add-row{align-items:flex-end;}
.admin-add-action{flex:0 0 auto !important;}
.admin-module-list{display:flex;flex-direction:column;gap:12px;}
.admin-module{border:1px solid var(--border);border-radius:14px;background:var(--bg-card);padding:16px 18px;display:flex;flex-direction:column;gap:10px;box-shadow:var(--shadow-sm);}
.admin-module-head{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;}
.admin-module-title strong{font-size:14px;}
.admin-module-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.admin-sub{display:block;font-size:11px;color:var(--text-muted);margin-top:2px;}
.btn-sm{padding:7px 12px;font-size:12px;border-radius:9px;}
.status-pill{font-size:11px;font-weight:800;padding:3px 9px;border-radius:999px;text-transform:uppercase;letter-spacing:.4px;}
.status-pill.active{background:rgba(34,197,94,.15);color:#22c55e;}
.status-pill.inactive{background:rgba(148,163,184,.18);color:#94a3b8;}
@media (max-width:640px){
  .admin-add-row{flex-direction:column;align-items:stretch;}
  .admin-add-action{flex:1 1 auto !important;}
}
</style>
