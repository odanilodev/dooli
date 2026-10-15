import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '../api/http';

export const useCompaniesStore = defineStore('companies', () => {
  const items = ref([]);
  const loading = ref(false);
  const loaded = ref(false);

  async function fetchCompanies(params = {}) {
    loading.value = true;

    try {
      const { data } = await api.get('/admin/companies', {
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

  async function createCompany(payload) {
    const { data } = await api.post('/admin/companies', payload);
    await fetchCompanies();

    return data.data;
  }

  async function updateCompany(id, payload) {
    const { data } = await api.put(`/admin/companies/${id}`, payload);
    await fetchCompanies();

    return data.data;
  }

  async function fetchBranding(id) {
    const { data } = await api.get(`/admin/companies/${id}/settings`);

    return data.data;
  }

  async function saveBranding(id, { primary_color, logo = null } = {}) {
    const payload = new FormData();
    payload.append('_method', 'PUT');

    if (primary_color !== undefined) {
      payload.append('primary_color', primary_color ?? '');
    }

    if (logo) {
      payload.append('logo', logo);
    }

    const { data } = await api.post(`/admin/companies/${id}/settings`, payload);

    return data.data;
  }

  return {
    items,
    loading,
    loaded,
    fetchCompanies,
    createCompany,
    updateCompany,
    fetchBranding,
    saveBranding,
  };
});
