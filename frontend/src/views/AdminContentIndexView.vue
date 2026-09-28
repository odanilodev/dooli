<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Conteúdo</h1>
        <p class="page-subtitle">{{ courses.length }} curso(s) · {{ draftCount }} rascunho(s)</p>
      </div>
      <button class="btn btn-primary" :disabled="creating" @click="createCourse">
        {{ creating ? 'Criando...' : '+ Novo curso' }}
      </button>
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
      <AdminCourseCard v-for="c in filtered" :key="c.id" :course="c" />
    </div>

  </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api/http';
import AdminCourseCard from '../components/admin/AdminCourseCard.vue';

const router = useRouter();

const courses = ref([]);
const loading = ref(true);
const creating = ref(false);
const error = ref('');
const search = ref('');
const status = ref('');

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
  creating.value = true;
  error.value = '';

  try {
    const { data } = await api.post('/admin/courses', { name: 'Novo curso', status: 'active' });
    router.push({
      name: 'admin-conteudo-curso',
      params: { courseId: data.data.id },
      query: { edit: 'course' },
    });
  } catch (e) {
    error.value = e?.message || 'Não foi possível criar o curso.';
    creating.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.admin-course-grid{grid-template-columns:repeat(auto-fill,minmax(230px,1fr));}
</style>
