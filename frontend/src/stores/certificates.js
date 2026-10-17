import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '../api/http';

function toFormData(payload, files = {}) {
  const hasFile = files.background || files.signature;

  if (!hasFile) {
    return null;
  }

  const formData = new FormData();

  Object.entries(payload).forEach(([key, value]) => {
    if (value !== null && value !== undefined) {
      formData.append(key, value);
    }
  });

  if (files.background) {
    formData.append('background', files.background);
  }

  if (files.signature) {
    formData.append('signature', files.signature);
  }

  return formData;
}

export const useCertificatesStore = defineStore('certificates', () => {
  const templates = ref([]);
  const meta = ref({ current_page: 1, last_page: 1, total: 0 });
  const loading = ref(false);
  const saving = ref(false);
  const error = ref(null);

  const issued = ref([]);
  const issuedMeta = ref({ current_page: 1, last_page: 1, total: 0 });
  const issuedLoading = ref(false);

  async function fetchTemplates(params = {}) {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get('/admin/certificates', { params });

      templates.value = data.data ?? [];
      meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };

      return templates.value;
    } catch (e) {
      error.value = e?.message || 'Não foi possível carregar os certificados.';
      templates.value = [];

      return [];
    } finally {
      loading.value = false;
    }
  }

  async function createTemplate(payload, files = {}) {
    saving.value = true;

    try {
      const formData = toFormData(payload, files);
      const { data } = formData
        ? await api.post('/admin/certificates', formData)
        : await api.post('/admin/certificates', payload);

      return data.data;
    } finally {
      saving.value = false;
    }
  }

  async function updateTemplate(id, payload, files = {}) {
    saving.value = true;

    try {
      const formData = toFormData(payload, files);

      if (formData) {
        formData.append('_method', 'PUT');
        const { data } = await api.post(`/admin/certificates/${id}`, formData);

        return data.data;
      }

      const { data } = await api.put(`/admin/certificates/${id}`, payload);

      return data.data;
    } finally {
      saving.value = false;
    }
  }

  async function deleteTemplate(id) {
    await api.delete(`/admin/certificates/${id}`);
  }

  async function fetchIssued(params = {}) {
    issuedLoading.value = true;

    try {
      const { data } = await api.get('/admin/certificate-users', { params });

      issued.value = data.data ?? [];
      issuedMeta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };

      return issued.value;
    } catch (e) {
      issued.value = [];

      return [];
    } finally {
      issuedLoading.value = false;
    }
  }

  async function revoke(id, reason) {
    const { data } = await api.post(`/admin/certificate-users/${id}/revoke`, { reason });

    return data.data;
  }

  return {
    templates,
    meta,
    loading,
    saving,
    error,
    issued,
    issuedMeta,
    issuedLoading,
    fetchTemplates,
    createTemplate,
    updateTemplate,
    deleteTemplate,
    fetchIssued,
    revoke,
  };
});
