import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api } from '../api/http';
import { PALETTE } from '../data/palette';
import { formatDuration } from '../utils/format';

function statusFromPercentage(pct) {
  if (pct >= 100) {
    return 'concluido';
  }

  if (pct <= 0) {
    return 'nao-iniciado';
  }

  return 'andamento';
}

export const useCoursesStore = defineStore('courses', () => {
  const items = ref([]);
  const meta = ref({});
  const continueItems = ref([]);
  const loading = ref(false);
  const loaded = ref(false);
  const error = ref(null);

  const cards = computed(() =>
    items.value.map((course, index) => {
      const pct = Math.round(Number(course.percentage) || 0);
      const palette = PALETTE[index % PALETTE.length];

      return {
        id: course.id,
        title: course.name,
        count: course.total_lessons ?? 0,
        pct,
        status: statusFromPercentage(pct),
        badge: '',
        icon: palette.icon,
        grad: palette.grad,
        image: course.image ?? '',
      };
    }),
  );

  const continueCards = computed(() =>
    continueItems.value.map((item, index) => {
      const palette = PALETTE[index % PALETTE.length];

      return {
        id: `${item.course_id}-${item.lesson_id}`,
        title: item.course_name,
        sub: item.lesson_name,
        dur: formatDuration(item.duration),
        pct: Math.round(Number(item.percentage) || 0),
        image: item.image ?? '',
        icon: palette.icon,
        grad: palette.grad,
        to: { name: 'aula', params: { courseId: item.course_id, lessonId: item.lesson_id } },
      };
    }),
  );

  const completedCount = computed(() => cards.value.filter((c) => c.status === 'concluido').length);

  async function fetchMyCourses(force = false) {
    if (loaded.value && !force) {
      return items.value;
    }

    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get('/me/courses');
      items.value = data.data ?? [];
      meta.value = data.meta ?? {};
      loaded.value = true;

      return items.value;
    } catch (e) {
      error.value = e?.message || 'Não foi possível carregar seus cursos.';
      return [];
    } finally {
      loading.value = false;
    }
  }

  async function fetchContinueWatching() {
    try {
      const { data } = await api.get('/me/continue-watching');
      continueItems.value = data.data ?? [];

      return continueItems.value;
    } catch (e) {
      continueItems.value = [];
      return [];
    }
  }

  function reset() {
    items.value = [];
    meta.value = {};
    continueItems.value = [];
    loaded.value = false;
    error.value = null;
  }

  return {
    items,
    meta,
    loading,
    loaded,
    error,
    cards,
    continueItems,
    continueCards,
    completedCount,
    fetchMyCourses,
    fetchContinueWatching,
    reset,
  };
});
