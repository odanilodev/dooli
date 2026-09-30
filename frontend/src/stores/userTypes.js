import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '../api/http';

export const useUserTypesStore = defineStore('userTypes', () => {
  const items = ref([]);
  const loading = ref(false);
  const loaded = ref(false);

  async function fetchUserTypes(params = {}) {
    loading.value = true;

    try {
      const { data } = await api.get('/admin/user-types', {
        params: { per_page: 100, ...params },
      });

      items.value = data.data ?? [];
      loaded.value = true;

      return items.value;
    } catch (e) {
      return items.value;
    } finally {
      loading.value = false;
    }
  }

  async function saveUserType(payload, id = null) {
    const { data } = id
      ? await api.put(`/admin/user-types/${id}`, payload)
      : await api.post('/admin/user-types', payload);

    await fetchUserTypes();

    return data.data;
  }

  async function removeUserType(id) {
    await api.delete(`/admin/user-types/${id}`);
    await fetchUserTypes();
  }

  function reset() {
    items.value = [];
    loaded.value = false;
  }

  return { items, loading, loaded, fetchUserTypes, saveUserType, removeUserType, reset };
});
