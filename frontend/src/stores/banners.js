import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '../api/http';
import { useAuthStore } from './auth';

export const useBannersStore = defineStore('banners', () => {
  const banners = ref([]);
  const loading = ref(false);
  const loaded = ref(false);
  const error = ref(null);

  // The public endpoint resolves the tenant from the X-Company header.
  function companyHeaders() {
    const companyId = useAuthStore().user?.company_id;

    return companyId ? { 'X-Company': String(companyId) } : {};
  }

  async function fetchBanners() {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get('/banners', { headers: companyHeaders() });

      banners.value = data.data ?? [];
      loaded.value = true;

      return banners.value;
    } catch (e) {
      error.value = e?.message || 'Não foi possível carregar os destaques.';
      banners.value = [];

      return [];
    } finally {
      loading.value = false;
    }
  }

  function reset() {
    banners.value = [];
    loaded.value = false;
    error.value = null;
  }

  return { banners, loading, loaded, error, fetchBanners, reset };
});
