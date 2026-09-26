<template>
  <main class="content">

    <div class="page-header">
      <div>
        <router-link class="admin-back" :to="backTo">← {{ backLabel }}</router-link>
        <h1 class="page-title">{{ module?.name || 'Módulo' }}</h1>
        <p class="page-subtitle">Edite os dados do módulo e organize os tópicos.</p>
      </div>
      <div class="admin-head-actions" v-if="module">
        <button class="btn btn-primary" :disabled="saving" @click="save">
          {{ saving ? 'Salvando...' : 'Salvar módulo' }}
        </button>
      </div>
    </div>

    <span class="form-error" :class="{ show: error }">{{ error }}</span>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <template v-else-if="module">

      <div class="form-success" :class="{ show: saved }">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
        Módulo salvo.
      </div>

      <form class="panel" @submit.prevent="save">
        <h2 class="panel-title">Dados do módulo</h2>
        <p class="panel-desc">O mesmo módulo pode ser reaproveitado em vários cursos.</p>

        <div class="form-group">
          <label for="m-name">Nome</label>
          <input id="m-name" class="form-control" v-model="form.name" required>
        </div>

        <div class="form-group">
          <label for="m-description">Descrição</label>
          <textarea id="m-description" class="form-control" rows="2" v-model="form.description"></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="m-image">Imagem (url)</label>
            <input id="m-image" class="form-control" v-model="form.image">
          </div>
          <div class="form-group">
            <label for="m-status">Status</label>
            <select id="m-status" class="form-control" v-model="form.status">
              <option value="active">active</option>
              <option value="inactive">inactive</option>
            </select>
          </div>
        </div>
      </form>

      <section class="section">
        <div class="section-head">
          <h2 class="section-title">Tópicos ({{ topics.length }})</h2>
        </div>

        <div class="panel">
          <div class="form-row admin-add-row">
            <div class="form-group">
              <label for="nt-name">Novo tópico</label>
              <input id="nt-name" class="form-control" v-model="newTopicName" placeholder="Nome do tópico">
            </div>
            <div class="form-group admin-add-action">
              <label for="nt-status">Status</label>
              <select id="nt-status" class="form-control" v-model="newTopicStatus">
                <option value="active">active</option>
                <option value="inactive">inactive</option>
              </select>
            </div>
            <div class="form-group admin-add-action">
              <button class="btn btn-primary" :disabled="creating" @click="createTopic">
                {{ creating ? 'Criando...' : 'Criar tópico' }}
              </button>
            </div>
          </div>

          <span class="form-error" :class="{ show: topicError }">{{ topicError }}</span>
        </div>

        <div v-if="topics.length === 0" class="empty-state">
          <strong>Nenhum tópico ainda</strong>
          <span>Crie o primeiro tópico deste módulo.</span>
        </div>

        <div v-else class="admin-module-list">
          <article v-for="(topic, index) in topics" :key="topic.id" class="admin-module">
            <div class="admin-module-head">
              <div class="admin-module-title">
                <strong>{{ topic.name }}</strong>
                <span class="admin-sub">#{{ topic.id }} · {{ topic.lessons?.length ?? 0 }} aula(s)</span>
              </div>
              <div class="admin-module-actions">
                <span class="status-pill" :class="topic.status">{{ topic.status }}</span>
                <router-link class="btn btn-outline btn-sm" :to="{ name: 'admin-topico', params: { topicId: topic.id }, query: moduleId ? { module: moduleId } : {} }">
                  Abrir
                </router-link>
                <button class="btn btn-outline btn-sm" :disabled="index === 0" @click="moveTopic(index, -1)">↑</button>
                <button class="btn btn-outline btn-sm" :disabled="index === topics.length - 1" @click="moveTopic(index, 1)">↓</button>
                <button class="btn btn-danger btn-sm" @click="removeTopic(topic)">Excluir</button>
              </div>
            </div>

            <ul v-if="topic.lessons?.length" class="admin-tree-lessons admin-tree-flat">
              <li v-for="lesson in topic.lessons" :key="lesson.id">{{ lesson.name }}</li>
            </ul>
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
const moduleId = Number(route.params.moduleId);
const courseId = route.query.course ? Number(route.query.course) : null;

const backTo = computed(() => (courseId ? { name: 'admin-curso', params: { courseId } } : { name: 'admin-cursos' }));
const backLabel = computed(() => (courseId ? 'Voltar ao curso' : 'Cursos'));

const module = ref(null);
const topics = ref([]);
const loading = ref(true);
const error = ref('');

const saving = ref(false);
const saved = ref(false);
const form = reactive({ name: '', description: '', image: '', status: 'active' });

const newTopicName = ref('');
const newTopicStatus = ref('active');
const creating = ref(false);
const topicError = ref('');

function syncForm() {
  form.name = module.value.name ?? '';
  form.description = module.value.description ?? '';
  form.image = module.value.image ?? '';
  form.status = module.value.status ?? 'active';
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get(`/admin/modules/${moduleId}`);
    module.value = data.data;
    topics.value = data.data.topics ?? [];
    syncForm();
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar o módulo.';
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  error.value = '';

  try {
    const { data } = await api.put(`/admin/modules/${moduleId}`, { ...form });
    module.value = { ...module.value, ...data.data, topics: topics.value };
    syncForm();
    saved.value = true;
    setTimeout(() => { saved.value = false; }, 2500);
  } catch (e) {
    error.value = e?.message || 'Não foi possível salvar o módulo.';
  } finally {
    saving.value = false;
  }
}

async function createTopic() {
  if (!newTopicName.value.trim()) {
    topicError.value = 'Informe o nome do tópico.';
    return;
  }

  creating.value = true;
  topicError.value = '';

  try {
    await api.post('/admin/topics', {
      name: newTopicName.value.trim(),
      module_id: moduleId,
      status: newTopicStatus.value,
    });

    newTopicName.value = '';
    await load();
  } catch (e) {
    topicError.value = e?.message || 'Não foi possível criar o tópico.';
  } finally {
    creating.value = false;
  }
}

async function removeTopic(topic) {
  if (!window.confirm(`Excluir o tópico "${topic.name}"?`)) {
    return;
  }

  topicError.value = '';

  try {
    await api.delete(`/admin/topics/${topic.id}`);
    await load();
  } catch (e) {
    topicError.value = e?.message || 'Não foi possível excluir o tópico.';
  }
}

async function moveTopic(index, delta) {
  const target = index + delta;

  if (target < 0 || target >= topics.value.length) {
    return;
  }

  const reordered = [...topics.value];
  [reordered[index], reordered[target]] = [reordered[target], reordered[index]];
  topics.value = reordered;

  try {
    await api.put(`/admin/modules/${moduleId}/topics/reorder`, {
      ids: reordered.map((t) => t.id),
    });
  } catch (e) {
    topicError.value = e?.message || 'Não foi possível reordenar os tópicos.';
    await load();
  }
}

onMounted(load);
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
.admin-tree-lessons{margin:0;padding-left:18px;display:flex;flex-direction:column;gap:2px;}
.admin-tree-lessons li{font-size:12.5px;color:var(--text-muted);}
.admin-tree-flat{border-top:1px dashed var(--border);padding-top:10px;}
.btn-sm{padding:7px 12px;font-size:12px;border-radius:9px;}
.status-pill{font-size:11px;font-weight:800;padding:3px 9px;border-radius:999px;text-transform:uppercase;letter-spacing:.4px;}
.status-pill.active{background:rgba(34,197,94,.15);color:#22c55e;}
.status-pill.inactive{background:rgba(148,163,184,.18);color:#94a3b8;}
@media (max-width:640px){
  .admin-add-row{flex-direction:column;align-items:stretch;}
  .admin-add-action{flex:1 1 auto !important;}
}
</style>
