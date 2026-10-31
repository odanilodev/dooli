import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api } from '../api/http';

export const useNotificationsStore = defineStore('notifications', () => {
  const items = ref([]);
  const meta = ref({});
  const loading = ref(false);
  const loaded = ref(false);
  const error = ref(null);

  const unreadCount = computed(() => items.value.filter((item) => !item.read_at).length);

  async function fetch(force = false) {
    if (loaded.value && !force) {
      return items.value;
    }

    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get('/me/notifications', { params: { per_page: 20 } });
      items.value = data.data ?? [];
      meta.value = data.meta ?? {};
      loaded.value = true;

      return items.value;
    } catch (e) {
      error.value = e?.message || 'Não foi possível carregar suas notificações.';
      return [];
    } finally {
      loading.value = false;
    }
  }

  async function refresh() {
    return fetch(true);
  }

  async function markAsRead(id) {
    const item = items.value.find((notification) => notification.id === Number(id));

    if (!item || item.read_at) {
      return;
    }

    await api.post(`/me/notifications/${id}/read`);
    item.read_at = new Date().toISOString();
  }

  async function markAllAsRead() {
    if (unreadCount.value === 0) {
      return;
    }

    await api.post('/me/notifications/read-all');

    const now = new Date().toISOString();
    items.value = items.value.map((item) => ({ ...item, read_at: item.read_at ?? now }));
  }

  function reset() {
    items.value = [];
    meta.value = {};
    loaded.value = false;
    error.value = null;
  }

  return { items, meta, loading, loaded, error, unreadCount, fetch, refresh, markAsRead, markAllAsRead, reset };
});
