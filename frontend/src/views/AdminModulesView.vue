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

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <template v-else>
      <CurriculumBuilder
        v-model:modules="modules"
        :selected="selected"
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

  </main>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';
import { useCurriculumModules } from '../composables/useCurriculumModules';
import CurriculumBuilder from '../components/admin/CurriculumBuilder.vue';
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
  load();
}

function setUsage(value) {
  usage.value = value;
  meta.value.current_page = 1;
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

async function onRemove(payload) {
  await removeNode(payload);
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

let searchDebounce;

watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    meta.value.current_page = 1;
    load();
  }, 350);
});

onMounted(load);
</script>

<style scoped>
.admin-search{max-width:none;width:100%;}
:deep(.cb){max-width:none;}
</style>
