<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Módulos</h1>
        <p class="page-subtitle">{{ meta.total }} módulo(s) · tópicos e aulas de todos os cursos</p>
      </div>
    </div>

    <div class="catalog-toolbar">
      <div class="filter-tabs">
        <button
          v-for="tab in filters"
          :key="tab.value"
          class="filter-tab"
          :class="{ active: usage === tab.value }"
          @click="setUsage(tab.value)"
        >{{ tab.label }}</button>
      </div>
      <input class="form-control admin-search" type="search" v-model="search" placeholder="Buscar módulo...">
    </div>

    <div v-if="bulkCount" class="bulk-bar">
      <strong>{{ bulkCount }} módulo(s) selecionado(s)</strong>
      <div class="bulk-bar-actions">
        <button type="button" class="btn btn-outline btn-sm" :disabled="bulkBusy" @click="bulkStatus('activate')">Ativar</button>
        <button type="button" class="btn btn-outline btn-sm" :disabled="bulkBusy" @click="bulkStatus('deactivate')">Inativar</button>
        <button type="button" class="btn btn-danger btn-sm" :disabled="bulkBusy" @click="bulkDelete">Excluir</button>
        <button type="button" class="btn btn-outline btn-sm" :disabled="bulkBusy" @click="clearSelection">Limpar</button>
      </div>
    </div>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <template v-else>
      <CurriculumBuilder
        v-model:modules="modules"
        :selected="selected"
        :selectable="true"
        :selected-ids="selectedIds"
        title="Todos os módulos"
        subtitle=""
        :show-attach="false"
        :allow-module-reorder="false"
        :empty-title="emptyTitle"
        :empty-hint="emptyHint"
        @select="onSelect"
        @rename="rename"
        @add-module="onCreateModule"
        @add-topic="createTopic"
        @add-lesson="createLesson"
        @add-module-lesson="createModuleLesson"
        @remove="onRemove"
        @reorder="reorder"
        @toggle-select="toggle"
        @toggle-select-all="toggleAllPage"
      />

      <div v-if="meta.last_page > 1" class="admin-pager">
        <button class="btn btn-outline btn-sm" :disabled="meta.current_page <= 1" @click="goPage(meta.current_page - 1)">Anterior</button>
        <span class="admin-muted">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <button class="btn btn-outline btn-sm" :disabled="meta.current_page >= meta.last_page" @click="goPage(meta.current_page + 1)">Próxima</button>
      </div>
    </template>

    <Transition name="drawer">
      <ItemDrawer
        v-if="selectedItem"
        :type="selected.type"
        :item="selectedItem"
        :course="drawerCourse"
        @close="closeDrawer"
        @saved="onSaved"
        @changed="onChanged"
      />
    </Transition>

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
      @confirm="onConfirmRemove"
    >
      <ul v-if="removeConfirm.courses.length" class="admin-conflict-list">
        <li v-for="course in removeConfirm.courses" :key="course.id"><strong>{{ course.name }}</strong></li>
      </ul>
    </ConfirmModal>

    <ConfirmModal
      v-if="bulkConfirm"
      title="Excluir módulos selecionados"
      :message="bulkConfirm.courses.length
        ? `Tem certeza que deseja excluir ${bulkConfirm.count} módulo(s)? As aulas deles (com progresso, comentários e avaliações dos alunos) também serão apagadas. Alguns estão vinculados aos cursos abaixo, que ficarão sem estes módulos.`
        : `Tem certeza que deseja excluir ${bulkConfirm.count} módulo(s)? As aulas deles (com progresso, comentários e avaliações dos alunos) também serão apagadas.`"
      confirm-label="Excluir"
      :busy="bulkBusy"
      @cancel="bulkConfirm = null"
      @confirm="confirmBulkDelete"
    >
      <ul v-if="bulkConfirm.courses.length" class="admin-conflict-list">
        <li v-for="course in bulkConfirm.courses" :key="course.id"><strong>{{ course.name }}</strong></li>
      </ul>
    </ConfirmModal>

  </main>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';
import { useBulkSelection } from '../composables/useBulkSelection';
import { useCurriculumModules } from '../composables/useCurriculumModules';
import CurriculumBuilder from '../components/admin/CurriculumBuilder.vue';
import ConfirmModal from '../components/admin/ConfirmModal.vue';
import ItemDrawer from '../components/admin/ItemDrawer.vue';
import ReorderConflictModal from '../components/admin/ReorderConflictModal.vue';

const PER_PAGE = 10;

const toast = useToast();

const modules = ref([]);
const selected = ref(null);
const loading = ref(true);
const search = ref('');
const usage = ref('');
const meta = ref({ current_page: 1, last_page: 1, total: 0 });

const filters = [
  { value: '', label: 'Todos' },
  { value: 'used', label: 'Em uso' },
  { value: 'unused', label: 'Sem curso' },
];

const { selectedIds, count: bulkCount, toggle, toggleAll, clear: clearSelection } = useBulkSelection();
const bulkBusy = ref(false);
const bulkConfirm = ref(null);
const pageIds = computed(() => modules.value.map((module) => module.id));

function toggleAllPage(checked) {
  toggleAll(pageIds.value, checked);
}

function openDrawer(type, id) {
  selected.value = { type, id };
}

function closeDrawer() {
  selected.value = null;
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
  openDrawer,
  reload: () => load(),
});

const selectedItem = computed(() => (selected.value ? findNode(selected.value.type, selected.value.id) : null));

const emptyTitle = computed(() => ((search.value || usage.value) ? 'Nenhum módulo encontrado' : 'Sem conteúdo ainda'));

const emptyHint = computed(() => ((search.value || usage.value)
  ? 'Ajuste a busca ou o filtro selecionado.'
  : 'Crie o primeiro módulo para começar.'));

const drawerCourse = computed(() => {
  const type = selected.value?.type;

  if (type !== 'topic' && type !== 'lesson') {
    return null;
  }

  const module = modules.value.find((m) => m.id === selectedItem.value?.module_id);

  return module ? { id: 0, name: module.name, modules: [module] } : null;
});

async function load() {
  loading.value = true;

  try {
    const { data } = await api.get('/admin/modules/tree', {
      params: {
        search: search.value || undefined,
        usage: usage.value || undefined,
        page: meta.value.current_page || 1,
        per_page: PER_PAGE,
      },
    });

    modules.value = data.data ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };

    if (!modules.value.length && meta.value.current_page > 1 && meta.value.current_page > meta.value.last_page) {
      goPage(meta.value.last_page);
    }
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar os módulos.');
  } finally {
    loading.value = false;
  }
}

function goPage(page) {
  if (page < 1 || page > meta.value.last_page) {
    return;
  }

  meta.value.current_page = page;
  clearSelection();
  load();
}

function setUsage(value) {
  usage.value = value;
  meta.value.current_page = 1;
  clearSelection();
  load();
}

function onSelect(target) {
  selected.value = target;
}

async function onCreateModule() {
  const before = modules.value.length;
  await createModule();

  if (modules.value.length > before) {
    meta.value.total += 1;
  }
}

function onRemove(payload) {
  removeNode(payload);
}

async function onConfirmRemove() {
  await confirmRemove();
  await load();
}

function onSaved({ type, id, data }) {
  const node = findNode(type, id);

  if (node) {
    Object.assign(node, data);
  }
}

function onChanged({ type, id, data }) {
  const node = findNode(type, id);

  if (node) {
    Object.assign(node, data);
  }
}

function reportSummary(summary) {
  const processed = summary?.processed ?? 0;
  const failed = summary?.failed ?? [];

  if (failed.length) {
    toast.error(`${processed} aplicado(s), ${failed.length} falha(s).`);
    return;
  }

  toast.success(`${processed} aplicado(s).`);
}

async function runBulk(payload) {
  bulkBusy.value = true;

  try {
    const { data } = await api.post('/admin/modules/bulk', payload);
    reportSummary(data.data);
    await load();
    clearSelection();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível concluir a ação.');
  } finally {
    bulkBusy.value = false;
  }
}

function bulkStatus(action) {
  return runBulk({ action, module_ids: selectedIds.value });
}

function bulkDelete() {
  const selected = modules.value.filter((module) => selectedIds.value.includes(module.id));
  const courses = [];
  const seen = new Set();

  for (const module of selected) {
    for (const course of module.courses ?? []) {
      if (!seen.has(course.id)) {
        seen.add(course.id);
        courses.push(course);
      }
    }
  }

  bulkConfirm.value = { count: bulkCount.value, courses };
}

async function confirmBulkDelete() {
  await runBulk({ action: 'delete', module_ids: selectedIds.value });
  bulkConfirm.value = null;
}

let searchDebounce;

watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    meta.value.current_page = 1;
    clearSelection();
    load();
  }, 350);
});

onMounted(load);
</script>

<style scoped>
.admin-search{max-width:none;width:100%;}
:deep(.cb){max-width:none;}
.bulk-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:12px 0;padding:10px 14px;border:1px solid var(--border);border-radius:12px;background:var(--bg-card-alt);}
.bulk-bar strong{font-size:13px;}
.bulk-bar-actions{display:flex;gap:8px;flex-wrap:wrap;}
</style>
