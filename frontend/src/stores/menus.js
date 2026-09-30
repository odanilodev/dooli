import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api } from '../api/http';

export const useMenusStore = defineStore('menus', () => {
  const items = ref([]);
  const loading = ref(false);
  const loaded = ref(false);

  const tree = computed(() => {
    const sorted = [...items.value].sort(
      (a, b) => (a.sort_order - b.sort_order) || (a.id - b.id),
    );

    const ids = new Set(sorted.map((menu) => String(menu.id)));
    const childrenByParent = new Map();
    const roots = [];

    sorted.forEach((menu) => {
      const parentId = menu.sub && ids.has(String(menu.sub)) ? String(menu.sub) : null;

      if (parentId && parentId !== String(menu.id)) {
        const list = childrenByParent.get(parentId) ?? [];
        list.push(menu);
        childrenByParent.set(parentId, list);

        return;
      }

      roots.push(menu);
    });

    const build = (menu, depth, ancestors) => {
      const seen = new Set(ancestors).add(String(menu.id));
      const children = (childrenByParent.get(String(menu.id)) ?? [])
        .filter((child) => !seen.has(String(child.id)))
        .map((child) => build(child, depth + 1, seen));

      return { ...menu, depth, children };
    };

    return roots.map((menu) => build(menu, 0, new Set()));
  });

  async function fetchMenus() {
    loading.value = true;

    try {
      const { data } = await api.get('/admin/menus');
      items.value = data.data ?? [];
      loaded.value = true;

      return items.value;
    } catch (e) {
      return items.value;
    } finally {
      loading.value = false;
    }
  }

  async function fetchMyMenus() {
    loading.value = true;

    try {
      const { data } = await api.get('/me/menus');
      items.value = data.data ?? [];
      loaded.value = true;

      return items.value;
    } catch (e) {
      items.value = [];

      return items.value;
    } finally {
      loading.value = false;
    }
  }

  async function saveMenu(payload, id = null) {
    const { data } = id
      ? await api.put(`/admin/menus/${id}`, payload)
      : await api.post('/admin/menus', payload);

    await fetchMenus();

    return data.data;
  }

  async function removeMenu(id) {
    await api.delete(`/admin/menus/${id}`);
    await fetchMenus();
  }

  function reset() {
    items.value = [];
    loaded.value = false;
  }

  return { items, loading, loaded, tree, fetchMenus, fetchMyMenus, saveMenu, removeMenu, reset };
});
