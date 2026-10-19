import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '../api/http';

export const useCommentsStore = defineStore('comments', () => {
  const items = ref([]);
  const meta = ref({});
  const loading = ref(false);
  const posting = ref(false);
  const replying = ref(false);
  const error = ref(null);
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
      const { data } = await api.get('/comments', {
        params: { lesson_id: id, per_page: 50 },
      });

      items.value = data.data ?? [];
      meta.value = data.meta ?? {};
      loadedLessonId.value = id;

      return items.value;
    } catch (e) {
      error.value = e?.message || 'Não foi possível carregar os comentários.';
      items.value = [];

      return [];
    } finally {
      loading.value = false;
    }
  }

  async function store({ content, lessonId, moduleId, courseId, teamId }) {
    posting.value = true;

    try {
      const { data } = await api.post('/comments', {
        content,
        lesson_id: lessonId,
        module_id: moduleId,
        course_id: courseId,
        team_id: teamId,
      });

      items.value = [data.data, ...items.value];

      return data.data;
    } finally {
      posting.value = false;
    }
  }

  async function reply({ parentId, content }) {
    replying.value = true;

    try {
      const { data } = await api.post('/comments', {
        parent_id: parentId,
        content,
      });

      const created = data.data;
      const root = items.value.find((item) => item.id === parentId);

      if (root) {
        root.replies = [...(root.replies ?? []), created];
      }

      return created;
    } finally {
      replying.value = false;
    }
  }

  async function remove(id, parentId = null) {
    await api.delete(`/comments/${id}`);

    if (parentId) {
      const root = items.value.find((item) => item.id === parentId);

      if (root) {
        root.replies = (root.replies ?? []).filter((reply) => reply.id !== id);
      }

      return;
    }

    items.value = items.value.filter((item) => item.id !== id);
  }

  function reset() {
    items.value = [];
    meta.value = {};
    loadedLessonId.value = null;
    error.value = null;
    replying.value = false;
  }

  return { items, meta, loading, posting, replying, error, fetch, store, reply, remove, reset };
});
