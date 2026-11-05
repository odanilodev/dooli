import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api } from '../api/http';

// Ferramentas que o aluno pode usar (liberadas por turma). Vazio = o menu Ferramentas some.
export const useRobotsStore = defineStore('robots', () => {
  const items = ref([]);
  const loaded = ref(false);

  async function fetch() {
    try {
      const { data } = await api.get('/me/robots');
      items.value = data.data ?? [];
    } catch {
      items.value = [];
    } finally {
      loaded.value = true;
    }
  }

  const hasAny = computed(() => items.value.length > 0);

  return { items, loaded, hasAny, fetch };
});
