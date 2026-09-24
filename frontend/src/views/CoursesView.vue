<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Meus cursos</h1>
        <p class="page-subtitle">{{ courses.length }} cursos matriculados · {{ completedCount }} concluídos</p>
      </div>
    </div>

    <div class="catalog-toolbar">
      <div class="filter-tabs">
        <button
          v-for="tab in filters"
          :key="tab.value"
          class="filter-tab"
          :class="{ active: activeFilter === tab.value }"
          @click="activeFilter = tab.value"
        >{{ tab.label }}</button>
      </div>
    </div>

    <div class="card-grid" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr));">
      <CourseCard
        v-for="course in filteredCourses"
        :key="course.id"
        :title="course.title"
        :count="course.count"
        :pct="course.pct"
        :badge="course.badge"
        :icon="course.icon"
        :grad="course.grad"
        :to="{ name: 'curso', params: { courseId: course.id } }"
      />
    </div>

    <div class="empty-state" v-if="filteredCourses.length === 0">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      <strong>Nenhum curso encontrado</strong>
      <span>Tente ajustar a busca ou o filtro selecionado.</span>
    </div>

  </main>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import CourseCard from '../components/CourseCard.vue';
import { useSearch } from '../composables/useSearch';
import { useCoursesStore } from '../stores/courses';

const { query } = useSearch();
const store = useCoursesStore();

// Evita herdar um texto digitado no header em outra página antes de chegar aqui.
onMounted(() => {
  query.value = '';
  store.fetchMyCourses();
});

const filters = [
  { value: 'todos', label: 'Todos' },
  { value: 'andamento', label: 'Em andamento' },
  { value: 'concluido', label: 'Concluídos' },
  { value: 'nao-iniciado', label: 'Não iniciados' },
];
const activeFilter = ref('todos');

function normalize(str) {
  return (str || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}

const courses = computed(() => store.cards);
const completedCount = computed(() => store.completedCount);

const filteredCourses = computed(() => {
  const q = normalize(query.value.trim());
  return store.cards.filter((course) => {
    const matchesFilter = activeFilter.value === 'todos' || course.status === activeFilter.value;
    const matchesSearch = !q || normalize(course.title).includes(q);
    return matchesFilter && matchesSearch;
  });
});

onUnmounted(() => {
  query.value = '';
});
</script>
