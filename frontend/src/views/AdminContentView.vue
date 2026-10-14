<template>
  <main class="content">

    <div class="page-header">
      <router-link class="btn btn-outline btn-sm builder-back" :to="{ name: 'admin-cursos' }">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        Cursos
      </router-link>

      <div class="admin-head-actions" v-if="course">
        <button class="btn btn-outline" @click="openDrawer('course', course.id)">Editar curso</button>
      </div>
    </div>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <template v-else-if="course">
      <CurriculumBuilder
        :course="course"
        :selected="selected"
        @select="onSelect"
        @rename="rename"
        @add-module="createModule"
        @attach-module="openAttach"
        @add-topic="createTopic"
        @add-lesson="createLesson"
        @add-module-lesson="createModuleLesson"
        @remove="removeNode"
        @reorder="reorder"
      />
    </template>

    <div v-else class="empty-state">
      <strong>Curso não encontrado</strong>
      <span>Ele pode ter sido removido. Volte para a lista de cursos.</span>
      <router-link class="btn btn-primary" :to="{ name: 'admin-cursos' }">‹ Voltar para cursos</router-link>
    </div>

    <Transition name="drawer">
      <ItemDrawer
        v-if="selectedItem"
        :type="selected.type"
        :item="selectedItem"
        :course="course"
        :focus="drawerFocus"
        @close="closeDrawer"
        @saved="onSaved"
      />
    </Transition>

    <div v-if="showAttach" class="admin-modal-backdrop" @mousedown="onBackdropDown" @click.self="backdropDown && closeAttach()">
      <div class="panel admin-modal">
        <div class="admin-panel-head">
          <h2 class="panel-title">Vincular módulos existentes</h2>
          <button class="admin-modal-close" @click="closeAttach">×</button>
        </div>

        <input class="form-control admin-search" type="search" v-model="moduleSearch" placeholder="Buscar módulo...">

        <p v-if="modulesLoading" class="page-subtitle">Carregando módulos...</p>
        <p v-else-if="!moduleResults.length" class="form-hint">Nenhum módulo encontrado.</p>

        <div v-else class="admin-attach-list">
          <label
            v-for="m in moduleResults"
            :key="m.id"
            class="admin-attach-item"
            :class="{ 'is-linked': moduleIsLinked(m) }"
          >
            <input type="checkbox" :value="m.id" v-model="selectedModuleIds" :disabled="moduleIsLinked(m)">
            <span class="attach-name">{{ m.name }}</span>
            <span v-if="moduleIsLinked(m)" class="admin-muted">Já vinculado</span>
            <span v-else class="status-pill" :class="m.status">{{ statusLabel(m.status) }}</span>
          </label>
        </div>

        <div v-if="moduleMeta.last_page > 1" class="admin-pager">
          <button class="btn btn-outline btn-sm" :disabled="moduleMeta.current_page <= 1" @click="goModulePage(moduleMeta.current_page - 1)">Anterior</button>
          <span class="admin-muted">Página {{ moduleMeta.current_page }} de {{ moduleMeta.last_page }}</span>
          <button class="btn btn-outline btn-sm" :disabled="moduleMeta.current_page >= moduleMeta.last_page" @click="goModulePage(moduleMeta.current_page + 1)">Próxima</button>
        </div>

        <div class="admin-modal-actions">
          <button class="btn btn-primary" :disabled="!selectableSelected.length || attaching" @click="attachSelected">
            {{ attaching ? 'Vinculando...' : `Vincular (${selectableSelected.length})` }}
          </button>
        </div>
      </div>
    </div>

    <ReorderConflictModal
      v-if="reorderConflicts.length"
      :conflicts="reorderConflicts"
      @cancel="cancelReorder"
      @confirm="confirmReorder"
    />

    <ConfirmModal
      v-if="removeConfirm"
      :title="removeConfirm.title"
      :message="removeConfirm.message"
      :confirm-label="removeConfirm.confirmLabel"
      :variant="removeConfirm.variant"
      @cancel="cancelRemove"
      @confirm="confirmRemove"
    />

  </main>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';
import { useCurriculumModules } from '../composables/useCurriculumModules';
import { statusLabel } from '../utils/adminLabels';
import ConfirmModal from '../components/admin/ConfirmModal.vue';
import CurriculumBuilder from '../components/admin/CurriculumBuilder.vue';
import ItemDrawer from '../components/admin/ItemDrawer.vue';
import ReorderConflictModal from '../components/admin/ReorderConflictModal.vue';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const courseId = computed(() => Number(route.params.courseId));
const course = ref(null);
const selected = ref(null);
const drawerFocus = ref(null);
const loading = ref(true);

const showAttach = ref(false);
const attaching = ref(false);
const modulesLoading = ref(false);
const moduleResults = ref([]);
const moduleMeta = ref({ current_page: 1, last_page: 1, total: 0 });
const moduleSearch = ref('');
const selectedModuleIds = ref([]);
const backdropDown = ref(false);

function onBackdropDown(e) {
  backdropDown.value = e.target === e.currentTarget;
}

const modules = computed({
  get: () => course.value?.modules ?? [],
  set: (value) => { if (course.value) course.value.modules = value; },
});

const root = computed(() => course.value);

function openDrawer(type, id, options = {}) {
  selected.value = { type, id };
  drawerFocus.value = options.focus ?? null;
}

const {
  findNode,
  createModule,
  createTopic,
  createLesson,
  createModuleLesson,
  rename,
  removeNode,
  confirmRemove,
  cancelRemove,
  removeConfirm,
  reorder,
  confirmReorder,
  cancelReorder,
  reorderConflicts,
} = useCurriculumModules({
  modules,
  root,
  courseId,
  attachModuleToCourse: true,
  openDrawer,
  reload: () => load(),
});

const selectedItem = computed(() => (selected.value ? findNode(selected.value.type, selected.value.id) : null));
const linkedModuleIds = computed(() => new Set((course.value?.modules ?? []).map((m) => m.id)));
const selectableSelected = computed(() => selectedModuleIds.value.filter((id) => !linkedModuleIds.value.has(id)));

async function load() {
  if (!courseId.value) {
    course.value = null;
    loading.value = false;
    return;
  }

  loading.value = true;
  course.value = null;

  try {
    const { data } = await api.get(`/admin/courses/${courseId.value}`);
    course.value = data.data;

    if (route.query.edit === 'course') {
      openDrawer('course', course.value.id, { focus: 'name' });
      router.replace({ name: 'admin-curso', params: { courseId: courseId.value } });
    }
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar o curso.');
  } finally {
    loading.value = false;
  }
}

function onSelect(target) {
  selected.value = target;
}

function closeDrawer() {
  selected.value = null;
  drawerFocus.value = null;
}

function onSaved({ type, id, data }) {
  const node = findNode(type, id);

  if (node) {
    Object.assign(node, data);
  }
}

async function openAttach() {
  showAttach.value = true;
  moduleSearch.value = '';
  selectedModuleIds.value = [];
  moduleMeta.value = { current_page: 1, last_page: 1, total: 0 };
  await loadModules();
}

function closeAttach() {
  showAttach.value = false;
  moduleSearch.value = '';
  moduleResults.value = [];
  selectedModuleIds.value = [];
}

async function loadModules() {
  modulesLoading.value = true;

  try {
    const { data } = await api.get('/admin/modules', {
      params: {
        search: moduleSearch.value || undefined,
        page: moduleMeta.value.current_page || 1,
        per_page: 10,
      },
    });

    moduleResults.value = data.data ?? [];
    moduleMeta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar os módulos.');
  } finally {
    modulesLoading.value = false;
  }
}

function goModulePage(page) {
  moduleMeta.value.current_page = page;
  loadModules();
}

function moduleIsLinked(module) {
  return linkedModuleIds.value.has(module.id);
}

async function attachSelected() {
  const ids = selectableSelected.value;

  if (!ids.length) {
    return;
  }

  attaching.value = true;

  try {
    for (const id of ids) {
      await api.post(`/admin/courses/${courseId.value}/modules`, { module_id: id });
    }

    toast.success(`${ids.length} módulo(s) vinculado(s).`);
    closeAttach();
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível vincular os módulos.');
  } finally {
    attaching.value = false;
  }
}

onMounted(load);

watch(courseId, () => {
  selected.value = null;
  load();
});

let moduleSearchDebounce;

watch(moduleSearch, () => {
  if (!showAttach.value) {
    return;
  }

  clearTimeout(moduleSearchDebounce);
  moduleSearchDebounce = setTimeout(() => {
    moduleMeta.value.current_page = 1;
    loadModules();
  }, 350);
});
</script>

<style scoped>
.builder-back{display:inline-flex;align-items:center;gap:6px;}
.admin-modal .admin-search{max-width:none;}
.admin-attach-item{justify-content:flex-start;}
.admin-attach-item.is-linked{opacity:.55;cursor:default;}
.admin-attach-item.is-linked:hover{border-color:var(--border);background:var(--bg-card);}
.attach-name{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.admin-attach-item input[type="checkbox"]{
  appearance:none;-webkit-appearance:none;margin:0;flex-shrink:0;
  width:18px;height:18px;display:inline-grid;place-content:center;cursor:pointer;
  border:1.5px solid var(--border);border-radius:6px;background:var(--bg-input);
  transition:border-color .14s ease,background .14s ease;
}
.admin-attach-item input[type="checkbox"]::before{
  content:"";width:10px;height:10px;transform:scale(0);transition:transform .12s ease;
  box-shadow:inset 1em 1em var(--accent-ink);
  clip-path:polygon(14% 44%,0 65%,50% 100%,100% 16%,80% 0%,43% 62%);
}
.admin-attach-item input[type="checkbox"]:hover{border-color:var(--accent);}
.admin-attach-item input[type="checkbox"]:checked{background:var(--accent);border-color:var(--accent);}
.admin-attach-item input[type="checkbox"]:checked::before{transform:scale(1);}
.admin-attach-item input[type="checkbox"]:focus-visible{outline:2px solid var(--accent);outline-offset:2px;}
.admin-attach-item input[type="checkbox"]:disabled{cursor:default;opacity:.55;}
</style>
