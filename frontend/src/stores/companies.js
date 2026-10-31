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

  async function saveBranding(id, {
    primary_color,
    logo = null,
    logo_light = null,
    logo_dark = null,
  } = {}) {
    const payload = new FormData();
    payload.append('_method', 'PUT');

    if (primary_color !== undefined) {
      payload.append('primary_color', primary_color ?? '');
    }

    if (logo) {
      payload.append('logo', logo);
    }

    if (logo_light) {
      payload.append('logo_light', logo_light);
    }

    if (logo_dark) {
      payload.append('logo_dark', logo_dark);
    }

    const { data } = await api.post(`/admin/companies/${id}/settings`, payload);

    return data.data;
  }

  async function fetchMailSettings(id) {
    const { data } = await api.get(`/admin/companies/${id}/mail-settings`);

    return data.data;
  }

  async function saveMailSettings(id, payload) {
    const { data } = await api.put(`/admin/companies/${id}/mail-settings`, payload);

    return data.data;
  }

  async function validateMailCredentials(id) {
    const { data } = await api.post(`/admin/companies/${id}/mail-settings/validate-credentials`);

    return data.data;
  }

  async function registerMailDomain(id) {
    const { data } = await api.post(`/admin/companies/${id}/mail-settings/register-domain`);

    return data.data;
  }

  async function checkMailDomain(id) {
    const { data } = await api.post(`/admin/companies/${id}/mail-settings/check-domain`);

    return data.data;
  }

  async function testMailSettings(id, email) {
    const { data } = await api.post(`/admin/companies/${id}/mail-settings/test`, { email });

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
    fetchMailSettings,
    saveMailSettings,
    validateMailCredentials,
    registerMailDomain,
    checkMailDomain,
    testMailSettings,
  };
});
