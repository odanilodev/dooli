<template>
  <main class="content">

    <div class="page-header">
      <div>
        <router-link class="admin-back" :to="{ name: 'admin-cursos' }">← Cursos</router-link>
        <h1 class="page-title">{{ course?.name || 'Curso' }}</h1>
        <p class="page-subtitle">Edite os dados do curso e organize os módulos.</p>
      </div>
      <div class="admin-head-actions" v-if="course">
        <button class="btn btn-outline" :disabled="busy" @click="togglePublish">
          {{ course.status === 'active' ? 'Despublicar' : 'Publicar' }}
        </button>
        <button class="btn btn-primary" :disabled="savingCourse" @click="saveCourse">
          {{ savingCourse ? 'Salvando...' : 'Salvar curso' }}
        </button>
      </div>
    </div>

    <span class="form-error" :class="{ show: error }">{{ error }}</span>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <template v-else-if="course">

      <div class="form-success" :class="{ show: savedCourse }">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
        Dados do curso salvos.
      </div>

      <form class="panel" @submit.prevent="saveCourse">
        <h2 class="panel-title">Dados do curso</h2>

        <div class="form-group">
          <label for="c-name">Nome</label>
          <input id="c-name" class="form-control" v-model="form.name" required>
        </div>

        <div class="form-group">
          <label for="c-description">Descrição</label>
          <textarea id="c-description" class="form-control" rows="3" v-model="form.description"></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="c-image">Imagem (url)</label>
            <input id="c-image" class="form-control" v-model="form.image">
          </div>
          <div class="form-group">
            <label for="c-status">Status</label>
            <select id="c-status" class="form-control" v-model="form.status">
              <option value="active">active</option>
              <option value="inactive">inactive</option>
            </select>
          </div>
          <div class="form-group">
            <label for="c-sort">Ordem</label>
            <input id="c-sort" class="form-control" type="number" min="0" v-model.number="form.sort_order">
          </div>
        </div>
      </form>

      <section class="section">
        <div class="section-head">
          <h2 class="section-title">Módulos ({{ modules.length }})</h2>
        </div>

        <div class="panel">
          <div class="form-row admin-add-row">
            <div class="form-group">
              <label for="nm-name">Novo módulo</label>
              <input id="nm-name" class="form-control" v-model="newModuleName" placeholder="Nome do módulo">
            </div>
            <div class="form-group admin-add-action">
              <button class="btn btn-primary" :disabled="creatingModule" @click="createModule">
                {{ creatingModule ? 'Criando...' : 'Criar e vincular' }}
              </button>
            </div>
          </div>

          <div class="form-row admin-add-row">
            <div class="form-group">
              <label for="am-select">Vincular módulo existente</label>
              <select id="am-select" class="form-control" v-model="attachId">
                <option value="">Selecione um módulo...</option>
                <option v-for="m in attachableModules" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
            </div>
            <div class="form-group admin-add-action">
              <button class="btn btn-outline" :disabled="!attachId || attaching" @click="attachModule">
                {{ attaching ? 'Vinculando...' : 'Vincular' }}
              </button>
            </div>
          </div>

          <span class="form-error" :class="{ show: moduleError }">{{ moduleError }}</span>
        </div>

        <div v-if="modules.length === 0" class="empty-state">
          <strong>Nenhum módulo vinculado</strong>
          <span>Crie um novo módulo ou vincule um existente acima.</span>
        </div>

        <div v-else class="admin-module-list">
          <article v-for="(module, index) in modules" :key="module.id" class="admin-module">
            <div class="admin-module-head">
              <div class="admin-module-title">
                <strong>{{ module.name }}</strong>
                <span class="admin-sub">#{{ module.id }} · {{ module.topics?.length ?? 0 }} tópico(s) · {{ lessonCount(module) }} aula(s)</span>
              </div>
              <div class="admin-module-actions">
                <span class="status-pill" :class="module.status">{{ module.status }}</span>
                <router-link class="btn btn-outline btn-sm" :to="{ name: 'admin-modulo', params: { moduleId: module.id }, query: { course: courseId } }">
                  Abrir
                </router-link>
                <button class="btn btn-outline btn-sm" :disabled="index === 0" @click="move(index, -1)">↑</button>
                <button class="btn btn-outline btn-sm" :disabled="index === modules.length - 1" @click="move(index, 1)">↓</button>
                <button class="btn btn-danger btn-sm" @click="detachModule(module)">Remover</button>
              </div>
            </div>

            <div v-if="(module.topics?.length ?? 0) > 0" class="admin-tree">
              <div v-for="topic in module.topics" :key="topic.id" class="admin-tree-topic">
                <span class="admin-tree-label">Tópico: {{ topic.name }}</span>
                <ul v-if="topic.lessons?.length" class="admin-tree-lessons">
                  <li v-for="lesson in topic.lessons" :key="lesson.id">{{ lesson.name }}</li>
                </ul>
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
const courseId = Number(route.params.courseId);

const course = ref(null);
const modules = ref([]);
const allModules = ref([]);
const loading = ref(true);
const error = ref('');
const busy = ref(false);

const savingCourse = ref(false);
const savedCourse = ref(false);
const form = reactive({ name: '', description: '', image: '', status: 'active', sort_order: 0 });

const newModuleName = ref('');
const creatingModule = ref(false);
const attachId = ref('');
const attaching = ref(false);
const moduleError = ref('');

const attachableModules = computed(() => {
  const attached = new Set(modules.value.map((m) => m.id));
  return allModules.value.filter((m) => !attached.has(m.id));
});

function lessonCount(module) {
  return (module.topics ?? []).reduce((total, topic) => total + (topic.lessons?.length ?? 0), 0);
}

function syncForm() {
  form.name = course.value.name ?? '';
  form.description = course.value.description ?? '';
  form.image = course.value.image ?? '';
  form.status = course.value.status ?? 'active';
  form.sort_order = course.value.sort_order ?? 0;
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get(`/admin/courses/${courseId}`);
    course.value = data.data;
    modules.value = data.data.modules ?? [];
    syncForm();
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar o curso.';
  } finally {
    loading.value = false;
  }
}

async function loadAllModules() {
  try {
    const { data } = await api.get('/admin/modules', { params: { per_page: 100 } });
    allModules.value = data.data ?? [];
  } catch (e) {
    /* silent: the picker just stays empty */
  }
}

async function saveCourse() {
  savingCourse.value = true;
  error.value = '';

  try {
    const { data } = await api.put(`/admin/courses/${courseId}`, { ...form });
    course.value = { ...course.value, ...data.data };
    syncForm();
    savedCourse.value = true;
    setTimeout(() => { savedCourse.value = false; }, 2500);
  } catch (e) {
    error.value = e?.message || 'Não foi possível salvar o curso.';
  } finally {
    savingCourse.value = false;
  }
}

async function togglePublish() {
  busy.value = true;

  try {
    const action = course.value.status === 'active' ? 'unpublish' : 'publish';
    await api.post(`/admin/courses/${courseId}/${action}`);
    course.value.status = course.value.status === 'active' ? 'inactive' : 'active';
    syncForm();
  } catch (e) {
    error.value = e?.message || 'Não foi possível alterar o status.';
  } finally {
    busy.value = false;
  }
}

async function createModule() {
  if (!newModuleName.value.trim()) {
    moduleError.value = 'Informe o nome do módulo.';
    return;
  }

  creatingModule.value = true;
  moduleError.value = '';

  try {
    const { data } = await api.post('/admin/modules', {
      name: newModuleName.value.trim(),
      status: 'active',
    });

    await api.post(`/admin/courses/${courseId}/modules`, { module_id: data.data.id });

    newModuleName.value = '';
    await Promise.all([load(), loadAllModules()]);
  } catch (e) {
    moduleError.value = e?.message || 'Não foi possível criar o módulo.';
  } finally {
    creatingModule.value = false;
  }
}

async function attachModule() {
  if (!attachId.value) {
    return;
  }

  attaching.value = true;
  moduleError.value = '';

  try {
    await api.post(`/admin/courses/${courseId}/modules`, { module_id: Number(attachId.value) });
    attachId.value = '';
    await load();
  } catch (e) {
    moduleError.value = e?.message || 'Não foi possível vincular o módulo.';
  } finally {
    attaching.value = false;
  }
}

async function detachModule(module) {
  if (!window.confirm(`Remover o módulo "${module.name}" deste curso? O módulo não é excluído.`)) {
    return;
  }

  moduleError.value = '';

  try {
    await api.delete(`/admin/courses/${courseId}/modules/${module.id}`);
    await load();
  } catch (e) {
    moduleError.value = e?.message || 'Não foi possível remover o módulo.';
  }
}

async function move(index, delta) {
  const target = index + delta;

  if (target < 0 || target >= modules.value.length) {
    return;
  }

  const reordered = [...modules.value];
  [reordered[index], reordered[target]] = [reordered[target], reordered[index]];
  modules.value = reordered;

  try {
    await api.put(`/admin/courses/${courseId}/modules/reorder`, {
      ids: reordered.map((m) => m.id),
    });
  } catch (e) {
    moduleError.value = e?.message || 'Não foi possível reordenar os módulos.';
    await load();
  }
}

onMounted(() => {
  load();
  loadAllModules();
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
.admin-tree{border-top:1px dashed var(--border);padding-top:10px;display:flex;flex-direction:column;gap:8px;}
.admin-tree-topic{display:flex;flex-direction:column;gap:4px;}
.admin-tree-label{font-size:12px;font-weight:700;color:var(--text-secondary);}
.admin-tree-lessons{margin:0;padding-left:18px;display:flex;flex-direction:column;gap:2px;}
.admin-tree-lessons li{font-size:12.5px;color:var(--text-muted);}
.btn-sm{padding:7px 12px;font-size:12px;border-radius:9px;}
.status-pill{font-size:11px;font-weight:800;padding:3px 9px;border-radius:999px;text-transform:uppercase;letter-spacing:.4px;}
.status-pill.active{background:rgba(34,197,94,.15);color:#22c55e;}
.status-pill.inactive{background:rgba(148,163,184,.18);color:#94a3b8;}
@media (max-width:640px){
  .admin-add-row{flex-direction:column;align-items:stretch;}
  .admin-add-action{flex:1 1 auto !important;}
}
</style>
