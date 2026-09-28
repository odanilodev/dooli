<template>
  <main class="content">

    <div class="page-header">
      <router-link class="btn btn-outline btn-sm builder-back" :to="{ name: 'admin-conteudo' }">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        Conteúdo
      </router-link>
    </div>

    <div class="wizard">
      <ol class="wizard-steps">
        <li
          v-for="(s, i) in steps"
          :key="s.key"
          class="wizard-step"
          :class="{ active: step === i + 1, done: step > i + 1, clickable: i + 1 <= step || courseId }"
          @click="goto(i + 1)"
        >
          <span class="wizard-num">{{ step > i + 1 ? '✓' : i + 1 }}</span>
          <span class="wizard-label">{{ s.label }}</span>
        </li>
      </ol>

      <span class="form-error" :class="{ show: error }">{{ error }}</span>

      <!-- Passo 1 -->
      <section v-if="step === 1" class="panel wizard-panel">
        <h2 class="panel-title">Dados do curso</h2>
        <div class="form-group">
          <label for="wz-name">Nome</label>
          <input id="wz-name" ref="nameInput" class="form-control" v-model="form.name" placeholder="Ex: Curso de PHP" @keydown.enter.prevent="next">
        </div>
        <div class="form-group">
          <label for="wz-desc">Descrição</label>
          <textarea id="wz-desc" class="form-control" rows="3" v-model="form.description"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="wz-image">Imagem (URL)</label>
            <input id="wz-image" class="form-control" v-model="form.image" placeholder="https://...">
          </div>
          <div class="form-group">
            <label for="wz-status">Status</label>
            <select id="wz-status" class="form-control" v-model="form.status">
              <option value="active">Publicado</option>
              <option value="inactive">Rascunho</option>
            </select>
          </div>
        </div>
      </section>

      <!-- Passo 2 -->
      <section v-else-if="step === 2" class="panel wizard-panel">
        <div class="wizard-panel-head">
          <h2 class="panel-title">Módulos</h2>
          <div class="wizard-panel-actions">
            <button class="btn btn-outline btn-sm" @click="openModulePicker">Vincular módulo</button>
            <button class="btn btn-primary btn-sm" @click="newModule.open = true">+ Novo módulo</button>
          </div>
        </div>

        <div v-if="newModule.open" class="wizard-inline">
          <input class="form-control" v-model="newModule.name" placeholder="Nome do módulo" @keydown.enter.prevent="createModule">
          <button class="btn btn-primary btn-sm" :disabled="busy" @click="createModule">Criar</button>
          <button class="btn btn-outline btn-sm" @click="cancelNewModule">Cancelar</button>
        </div>

        <p v-if="!modules.length" class="form-hint">Nenhum módulo vinculado ainda.</p>
        <div v-else class="wizard-list">
          <div v-for="m in modules" :key="m.id" class="wizard-item">
            <span class="wizard-item-name">{{ m.name }}</span>
            <span class="wizard-item-meta">{{ m.topics?.length ?? 0 }} tópico(s)</span>
            <button class="wizard-remove" @click="detachModule(m)">remover</button>
          </div>
        </div>
      </section>

      <!-- Passo 3 -->
      <section v-else-if="step === 3" class="panel wizard-panel">
        <h2 class="panel-title">Tópicos</h2>
        <p class="panel-desc">Para cada módulo, crie tópicos novos ou mova tópicos existentes para cá.</p>

        <p v-if="!modules.length" class="form-hint">Vincule pelo menos um módulo no passo anterior.</p>

        <div v-for="m in modules" :key="m.id" class="wizard-module-block">
          <div class="wizard-module-head">
            <strong>{{ m.name }}</strong>
            <div class="wizard-panel-actions">
              <button class="btn btn-outline btn-sm" @click="openTopicPicker(m)">Vincular tópico</button>
              <button class="btn btn-primary btn-sm" @click="startNewTopic(m)">+ Novo tópico</button>
            </div>
          </div>

          <div v-if="newTopic.moduleId === m.id" class="wizard-inline">
            <input class="form-control" v-model="newTopic.name" placeholder="Nome do tópico" @keydown.enter.prevent="createTopic(m)">
            <button class="btn btn-primary btn-sm" :disabled="busy" @click="createTopic(m)">Criar</button>
            <button class="btn btn-outline btn-sm" @click="cancelNewTopic">Cancelar</button>
          </div>

          <p v-if="!(m.topics?.length)" class="form-hint">Nenhum tópico ainda.</p>
          <div v-else class="wizard-list">
            <div v-for="t in m.topics" :key="t.id" class="wizard-item">
              <span class="wizard-item-name">{{ t.name }}</span>
              <button class="wizard-remove" @click="removeTopic(t)">remover</button>
            </div>
          </div>
        </div>
      </section>

      <!-- Passo 4 -->
      <section v-else-if="step === 4" class="panel wizard-panel">
        <h2 class="panel-title">Aulas</h2>
        <p class="panel-desc">Adicione aulas novas ou copie aulas existentes para cada tópico.</p>

        <p v-if="!topicCount" class="form-hint">Crie pelo menos um tópico no passo anterior.</p>

        <div v-for="m in modules" :key="m.id" class="wizard-module-block">
          <strong>{{ m.name }}</strong>

          <div v-for="t in m.topics" :key="t.id" class="wizard-topic-block">
            <div class="wizard-module-head">
              <span class="wizard-topic-name">{{ t.name }}</span>
              <div class="wizard-panel-actions">
                <button class="btn btn-primary btn-sm" @click="startNewLesson(t)">+ Nova aula</button>
              </div>
            </div>

            <div v-if="newLesson.topicId === t.id" class="wizard-inline">
              <input class="form-control" v-model="newLesson.name" placeholder="Nome da aula" @keydown.enter.prevent="createLesson(t)">
              <button class="btn btn-primary btn-sm" :disabled="busy" @click="createLesson(t)">Criar</button>
              <button class="btn btn-outline btn-sm" @click="cancelNewLesson">Cancelar</button>
            </div>

            <p v-if="!(t.lessons?.length)" class="form-hint">Nenhuma aula ainda.</p>
            <div v-else class="wizard-list">
              <div v-for="l in t.lessons" :key="l.id" class="wizard-item wizard-item-clickable" @click="openLesson(l)">
                <span class="wizard-item-name">{{ l.name }}</span>
                <span v-if="l.video" class="wizard-item-meta">vídeo</span>
                <button class="wizard-remove" @click.stop="removeLesson(l)">remover</button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Passo 5 -->
      <section v-else-if="step === 5" class="panel wizard-panel">
        <h2 class="panel-title">Revisar</h2>
        <div class="wizard-review">
          <div class="wizard-review-row"><span>Curso</span><strong>{{ form.name }}</strong></div>
          <div class="wizard-review-row"><span>Status</span><span class="status-pill" :class="form.status">{{ statusLabel(form.status) }}</span></div>
          <div class="wizard-review-row"><span>Módulos</span><strong>{{ modules.length }}</strong></div>
          <div class="wizard-review-row"><span>Tópicos</span><strong>{{ topicCount }}</strong></div>
          <div class="wizard-review-row"><span>Aulas</span><strong>{{ lessonCount }}</strong></div>
        </div>

        <div v-for="m in modules" :key="m.id" class="wizard-review-module">
          <strong>{{ m.name }}</strong>
          <template v-for="t in m.topics" :key="t.id">
            <p class="wizard-review-topic">{{ t.name }}</p>
            <ul>
              <li v-for="l in t.lessons" :key="l.id">{{ l.name }}</li>
              <li v-if="!(t.lessons?.length)" class="wizard-review-empty">sem aulas</li>
            </ul>
          </template>
          <p v-if="!(m.topics?.length)" class="wizard-review-empty">sem tópicos</p>
        </div>
      </section>

      <div class="wizard-foot">
        <button v-if="step > 1" class="btn btn-outline" @click="step--">Voltar</button>
        <span class="wizard-spacer"></span>
        <button v-if="step < 5" class="btn btn-primary" :disabled="busy" @click="next">
          {{ busy ? 'Salvando...' : (step === 1 ? (courseId ? 'Salvar e continuar' : 'Criar e continuar') : 'Continuar') }}
        </button>
        <button v-else class="btn btn-primary" @click="finish">Concluir</button>
      </div>
    </div>

    <div v-if="showModules" class="admin-modal-backdrop" @click.self="showModules = false">
      <div class="panel admin-modal">
        <div class="admin-panel-head">
          <h2 class="panel-title">Vincular módulo existente</h2>
          <button class="admin-modal-close" @click="showModules = false">×</button>
        </div>
        <p v-if="loadingPool" class="page-subtitle">Carregando...</p>
        <p v-else-if="!availableModules.length" class="form-hint">Todos os módulos já estão vinculados a este curso.</p>
        <div v-else class="admin-attach-list">
          <button v-for="m in availableModules" :key="m.id" class="admin-attach-item" @click="attachModule(m)">
            <span>{{ m.name }}</span>
            <span class="status-pill" :class="m.status">{{ statusLabel(m.status) }}</span>
          </button>
        </div>
      </div>
    </div>

    <div v-if="linkTopic.module" class="admin-modal-backdrop" @click.self="linkTopic.module = null">
      <div class="panel admin-modal">
        <div class="admin-panel-head">
          <h2 class="panel-title">Vincular tópico a "{{ linkTopic.module.name }}"</h2>
          <button class="admin-modal-close" @click="linkTopic.module = null">×</button>
        </div>
        <p class="form-hint">O tópico será <strong>movido</strong> do módulo atual para este.</p>
        <p v-if="loadingPool" class="page-subtitle">Carregando...</p>
        <p v-else-if="!availableTopics.length" class="form-hint">Nenhum tópico disponível para vincular.</p>
        <div v-else class="admin-attach-list">
          <button v-for="t in availableTopics" :key="t.id" class="admin-attach-item" @click="linkTopicTo(t)">
            <span>{{ t.name }}</span>
            <span class="admin-muted">módulo #{{ t.module_id }}</span>
          </button>
        </div>
      </div>
    </div>

    <ItemDrawer
      v-if="editingLesson"
      type="lesson"
      :item="editingLesson"
      :course="course"
      @close="editingLesson = null"
      @saved="onLessonSaved"
    />

  </main>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api/http';
import { statusLabel } from '../utils/adminLabels';
import ItemDrawer from '../components/admin/ItemDrawer.vue';

const router = useRouter();

const steps = [
  { key: 'dados', label: 'Dados' },
  { key: 'modulos', label: 'Módulos' },
  { key: 'topicos', label: 'Tópicos' },
  { key: 'aulas', label: 'Aulas' },
  { key: 'revisar', label: 'Revisar' },
];

const step = ref(1);
const courseId = ref(null);
const course = ref(null);
const error = ref('');
const busy = ref(false);

const form = reactive({ name: '', description: '', image: '', status: 'active' });

const nameInput = ref(null);
const showModules = ref(false);
const loadingPool = ref(false);
const modulePool = ref([]);
const topicPool = ref([]);

const newModule = reactive({ open: false, name: '' });
const newTopic = reactive({ moduleId: null, name: '' });
const linkTopic = reactive({ module: null });
const newLesson = reactive({ topicId: null, name: '' });
const editingLesson = ref(null);

const modules = computed(() => course.value?.modules ?? []);
const topicCount = computed(() => modules.value.reduce((total, m) => total + (m.topics?.length ?? 0), 0));
const lessonCount = computed(() => modules.value.reduce(
  (total, m) => total + (m.topics ?? []).reduce((sum, t) => sum + (t.lessons?.length ?? 0), 0),
  0,
));

const linkedModuleIds = computed(() => new Set(modules.value.map((m) => m.id)));
const availableModules = computed(() => modulePool.value.filter((m) => !linkedModuleIds.value.has(m.id)));

const availableTopics = computed(() => {
  const inCourse = new Set();

  for (const m of modules.value) {
    for (const t of m.topics ?? []) {
      inCourse.add(t.id);
    }
  }

  return topicPool.value.filter((t) => !inCourse.has(t.id));
});

function goto(n) {
  if (n <= step.value || (courseId.value && n <= 5)) {
    step.value = n;
  }
}

async function ensurePools() {
  if (modulePool.value.length || topicPool.value.length) {
    return;
  }

  loadingPool.value = true;

  try {
    const [m, t] = await Promise.all([
      api.get('/admin/modules', { params: { per_page: 100 } }),
      api.get('/admin/topics', { params: { per_page: 100 } }),
    ]);
    modulePool.value = m.data.data ?? [];
    topicPool.value = t.data.data ?? [];
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar módulos e tópicos.';
  } finally {
    loadingPool.value = false;
  }
}

async function loadCourse() {
  const { data } = await api.get(`/admin/courses/${courseId.value}`);
  course.value = data.data;
}

async function next() {
  error.value = '';

  if (step.value === 1) {
    if (!form.name.trim()) {
      error.value = 'Informe o nome do curso.';
      return;
    }

    busy.value = true;

    try {
      const payload = {
        name: form.name.trim(),
        description: form.description || null,
        image: form.image || null,
        status: form.status,
      };

      if (courseId.value) {
        await api.put(`/admin/courses/${courseId.value}`, payload);
      } else {
        const { data } = await api.post('/admin/courses', payload);
        courseId.value = data.data.id;
      }

      await loadCourse();
      step.value = 2;
    } catch (e) {
      error.value = e?.message || 'Não foi possível salvar o curso.';
    } finally {
      busy.value = false;
    }

    return;
  }

  if (step.value === 2) {
    await ensurePools();
  }

  step.value += 1;
}

function finish() {
  router.push({ name: 'admin-conteudo-curso', params: { courseId: courseId.value } });
}

async function createModule() {
  const name = newModule.name.trim();

  if (!name) {
    return;
  }

  busy.value = true;
  error.value = '';

  try {
    const { data } = await api.post('/admin/modules', { name, status: 'active' });
    modulePool.value = [...modulePool.value, data.data];
    await api.post(`/admin/courses/${courseId.value}/modules`, { module_id: data.data.id });
    newModule.name = '';
    newModule.open = false;
    await loadCourse();
  } catch (e) {
    error.value = e?.message || 'Não foi possível criar o módulo.';
  } finally {
    busy.value = false;
  }
}

function cancelNewModule() {
  newModule.open = false;
  newModule.name = '';
}

function startNewTopic(module) {
  newTopic.moduleId = module.id;
  newTopic.name = '';
}

function cancelNewTopic() {
  newTopic.moduleId = null;
  newTopic.name = '';
}

async function openModulePicker() {
  showModules.value = true;
  await ensurePools();
}

async function attachModule(module) {
  busy.value = true;
  error.value = '';

  try {
    await api.post(`/admin/courses/${courseId.value}/modules`, { module_id: module.id });
    showModules.value = false;
    await loadCourse();
  } catch (e) {
    error.value = e?.message || 'Não foi possível vincular o módulo.';
  } finally {
    busy.value = false;
  }
}

async function detachModule(module) {
  if (!window.confirm(`Remover o módulo "${module.name}" deste curso?`)) {
    return;
  }

  busy.value = true;
  error.value = '';

  try {
    await api.delete(`/admin/courses/${courseId.value}/modules/${module.id}`);
    await loadCourse();
  } catch (e) {
    error.value = e?.message || 'Não foi possível remover o módulo.';
  } finally {
    busy.value = false;
  }
}

async function createTopic(module) {
  const name = newTopic.name.trim();

  if (!name) {
    return;
  }

  busy.value = true;
  error.value = '';

  try {
    await api.post('/admin/topics', { name, module_id: module.id, status: 'active' });
    newTopic.name = '';
    newTopic.moduleId = null;
    await loadCourse();
  } catch (e) {
    error.value = e?.message || 'Não foi possível criar o tópico.';
  } finally {
    busy.value = false;
  }
}

async function openTopicPicker(module) {
  linkTopic.module = module;
  await ensurePools();
}

async function linkTopicTo(topic) {
  busy.value = true;
  error.value = '';

  try {
    await api.put(`/admin/topics/${topic.id}`, { module_id: linkTopic.module.id });
    linkTopic.module = null;
    await loadCourse();
  } catch (e) {
    error.value = e?.message || 'Não foi possível vincular o tópico.';
  } finally {
    busy.value = false;
  }
}

async function removeTopic(topic) {
  if (!window.confirm(`Remover o tópico "${topic.name}"?`)) {
    return;
  }

  busy.value = true;
  error.value = '';

  try {
    await api.delete(`/admin/topics/${topic.id}`);
    await loadCourse();
  } catch (e) {
    error.value = e?.message || 'Não foi possível remover o tópico.';
  } finally {
    busy.value = false;
  }
}

function startNewLesson(topic) {
  newLesson.topicId = topic.id;
  newLesson.name = '';
}

function cancelNewLesson() {
  newLesson.topicId = null;
  newLesson.name = '';
}

async function createLesson(topic) {
  const name = newLesson.name.trim();

  if (!name) {
    return;
  }

  busy.value = true;
  error.value = '';

  try {
    await api.post('/admin/lessons', { name, topic_id: topic.id, status: 'active' });
    newLesson.name = '';
    newLesson.topicId = null;
    await loadCourse();
  } catch (e) {
    error.value = e?.message || 'Não foi possível criar a aula.';
  } finally {
    busy.value = false;
  }
}

function openLesson(lesson) {
  editingLesson.value = lesson;
}

function onLessonSaved({ data }) {
  if (editingLesson.value) {
    Object.assign(editingLesson.value, data);
  }
}

async function removeLesson(lesson) {
  if (!window.confirm(`Remover a aula "${lesson.name}"?`)) {
    return;
  }

  busy.value = true;
  error.value = '';

  try {
    await api.delete(`/admin/lessons/${lesson.id}`);
    await loadCourse();
  } catch (e) {
    error.value = e?.message || 'Não foi possível remover a aula.';
  } finally {
    busy.value = false;
  }
}

onMounted(() => nextTick(() => nameInput.value?.focus()));
</script>

<style scoped>
.builder-back{display:inline-flex;align-items:center;gap:6px;}
.wizard{display:flex;flex-direction:column;gap:20px;width:100%;max-width:900px;}
.wizard-steps{display:flex;gap:8px;flex-wrap:wrap;margin:0;padding:0;list-style:none;}
.wizard-step{
  display:flex;align-items:center;gap:9px;padding:9px 14px;border-radius:11px;border:1px solid var(--border);
  background:var(--bg-card);color:var(--text-muted);font-weight:700;font-size:13px;
}
.wizard-step.clickable{cursor:pointer;}
.wizard-step.active{border-color:var(--accent);color:var(--text-primary);background:var(--accent-soft);}
.wizard-step.done{color:var(--text-secondary);}
.wizard-num{
  width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  background:var(--bg-card-alt);color:var(--text-secondary);font-size:11.5px;font-weight:800;flex-shrink:0;
}
.wizard-step.active .wizard-num{background:var(--accent-grad);color:var(--accent-ink);}
.wizard-step.done .wizard-num{background:var(--accent-soft);color:var(--accent);}

.wizard-panel{gap:14px;}
.wizard-panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.wizard-panel-actions{display:flex;gap:8px;flex-wrap:wrap;}
.wizard-inline{
  display:flex;gap:8px;align-items:center;background:var(--bg-card-alt);border:1px solid var(--border);
  border-radius:12px;padding:12px;flex-wrap:wrap;
}
.wizard-inline .form-control{flex:1;min-width:160px;}

.wizard-list{display:flex;flex-direction:column;gap:8px;}
.wizard-item{
  display:flex;align-items:center;gap:12px;padding:11px 14px;border:1px solid var(--border);border-radius:11px;
  background:var(--bg-card);
}
.wizard-item-name{flex:1;min-width:0;font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.wizard-item-clickable{cursor:pointer;transition:border-color .14s ease,background .14s ease;}
.wizard-item-clickable:hover{border-color:var(--accent);background:var(--accent-soft);}
.wizard-item-meta{font-size:11.5px;color:var(--text-muted);flex-shrink:0;}
.wizard-remove{background:none;border:none;color:#ef4444;font-weight:700;font-size:12px;cursor:pointer;font-family:inherit;flex-shrink:0;}

.wizard-module-block{display:flex;flex-direction:column;gap:10px;border-top:1px dashed var(--border);padding-top:14px;}
.wizard-module-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.wizard-topic-block{display:flex;flex-direction:column;gap:10px;border-top:1px dashed var(--border);padding-top:12px;margin-top:4px;}
.wizard-topic-name{font-size:13px;font-weight:700;}

.wizard-review{display:flex;flex-direction:column;gap:8px;}
.wizard-review-row{display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:13px;color:var(--text-secondary);}
.wizard-review-row strong{color:var(--text-primary);}
.wizard-review-module{border-top:1px dashed var(--border);padding-top:12px;}
.wizard-review-topic{margin:10px 0 0;font-size:12.5px;font-weight:700;color:var(--text-secondary);}
.wizard-review-module ul{margin:6px 0 0;padding-left:18px;list-style:disc;color:var(--text-secondary);font-size:12.5px;}
.wizard-review-module li{margin:2px 0;}
.wizard-review-empty{color:var(--text-muted);font-style:italic;}

.wizard-foot{display:flex;align-items:center;gap:12px;}
.wizard-spacer{flex:1;}
</style>
