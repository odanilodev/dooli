import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '../api/http';

export const useUsersStore = defineStore('users', () => {
  const items = ref([]);
  const meta = ref({ current_page: 1, last_page: 1, total: 0 });
  const loading = ref(false);

  async function fetchUsers(params = {}) {
    loading.value = true;

    try {
      const { data } = await api.get('/admin/users', { params });

      items.value = data.data ?? [];
      meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };

      return items.value;
    } catch (e) {
      items.value = [];

      return items.value;
    } finally {
      loading.value = false;
    }
  }

  async function saveUser(payload, id = null) {
    const { data } = id
      ? await api.put(`/admin/users/${id}`, payload)
      : await api.post('/admin/users', payload);

    return data.data;
  }

  async function removeUser(id) {
    await api.delete(`/admin/users/${id}`);
  }

  function reset() {
    items.value = [];
    meta.value = { current_page: 1, last_page: 1, total: 0 };
  }

  return { items, meta, loading, fetchUsers, saveUser, removeUser, reset };
});
