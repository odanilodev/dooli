<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Conteúdo</h1>
        <p class="page-subtitle">{{ courses.length }} curso(s) · {{ draftCount }} rascunho(s)</p>
      </div>
      <button class="btn btn-primary" @click="createCourse">+ Novo curso</button>
    </div>

    <div class="catalog-toolbar">
      <div class="filter-tabs">
        <button
          v-for="tab in filters"
          :key="tab.value"
          class="filter-tab"
          :class="{ active: status === tab.value }"
          @click="status = tab.value"
        >{{ tab.label }}</button>
      </div>
      <input class="form-control admin-search" type="search" v-model="search" placeholder="Buscar curso...">
    </div>

    <span class="form-error" :class="{ show: error }">{{ error }}</span>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <div v-else-if="!filtered.length" class="empty-state">
      <strong>{{ courses.length ? 'Nenhum curso encontrado' : 'Nenhum curso ainda' }}</strong>
      <span>{{ courses.length ? 'Ajuste a busca ou o filtro selecionado.' : 'Crie o primeiro curso para começar a montar o conteúdo.' }}</span>
      <button v-if="!courses.length" class="btn btn-primary" @click="createCourse">+ Novo curso</button>
    </div>

    <div v-else class="card-grid admin-course-grid">
      <AdminCourseCard v-for="c in filtered" :key="c.id" :course="c" @edit="editing = $event" />
    </div>

    <Transition name="drawer">
      <ItemDrawer
        v-if="editing"
        type="course"
        :item="editing"
        :course="editing"
        @close="editing = null"
        @saved="onSaved"
      />
    </Transition>

  </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api/http';
import AdminCourseCard from '../components/admin/AdminCourseCard.vue';
import ItemDrawer from '../components/admin/ItemDrawer.vue';

const router = useRouter();

const courses = ref([]);
const loading = ref(true);
const error = ref('');
const search = ref('');
const status = ref('');
const editing = ref(null);

const filters = [
  { value: '', label: 'Todos' },
  { value: 'active', label: 'Publicados' },
  { value: 'inactive', label: 'Rascunhos' },
];

const draftCount = computed(() => courses.value.filter((c) => c.status !== 'active').length);

const filtered = computed(() => {
  const term = normalize(search.value.trim());

  return courses.value.filter((c) => {
    const matchesStatus = !status.value || c.status === status.value;
    const matchesSearch = !term || normalize(c.name).includes(term) || normalize(c.description).includes(term);
    return matchesStatus && matchesSearch;
  });
});

function normalize(str) {
  return (str || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get('/admin/courses', { params: { per_page: 100 } });
    courses.value = data.data ?? [];
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar os cursos.';
  } finally {
    loading.value = false;
  }
}

async function createCourse() {
  router.push({ name: 'admin-conteudo-novo' });
}

function onSaved({ id, data }) {
  const course = courses.value.find((c) => c.id === id);

  if (course) {
    Object.assign(course, data);
  }

  editing.value = null;
}

onMounted(load);
</script>

<style scoped>
.admin-course-grid{grid-template-columns:repeat(auto-fill,minmax(230px,1fr));}
</style>
