import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '../api/http';

export const useRatingsStore = defineStore('ratings', () => {
  const items = ref([]);
  const meta = ref({});
  const loading = ref(false);
  const posting = ref(false);
  const error = ref(null);
  const duplicate = ref(false);
  const loadedLessonId = ref(null);

  async function fetch(lessonId, force = false) {
    const id = Number(lessonId);

    if (!id) {
      return [];
    }

    if (loadedLessonId.value === id && !force) {
      return items.value;
    }

    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get('/ratings', {
        params: { lesson_id: id, per_page: 50 },
      });

      items.value = data.data ?? [];
      meta.value = data.meta ?? {};
      loadedLessonId.value = id;

      return items.value;
    } catch (e) {
      error.value = e?.message || 'Não foi possível carregar as avaliações.';
      items.value = [];

      return [];
    } finally {
      loading.value = false;
    }
  }

  async function store({ rating, lessonId, moduleId, courseId, teamId }) {
    posting.value = true;
    duplicate.value = false;

    try {
      const { data } = await api.post('/ratings', {
        rating,
        lesson_id: lessonId,
        module_id: moduleId,
        course_id: courseId,
        team_id: teamId,
      });

      items.value = [data.data, ...items.value];

      return data.data;
    } catch (e) {
      // The student already rated this lesson: resync the list so the existing
      // rating shows up instead of surfacing a hard error.
      if (e?.status === 409) {
        duplicate.value = true;
        await fetch(loadedLessonId.value, true);

        return null;
      }

      throw e;
    } finally {
      posting.value = false;
    }
  }

  function reset() {
    items.value = [];
    meta.value = {};
    loadedLessonId.value = null;
    error.value = null;
    duplicate.value = false;
  }

  return { items, meta, loading, posting, error, duplicate, fetch, store, reset };
});
