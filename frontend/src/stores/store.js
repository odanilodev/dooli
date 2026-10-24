import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '../api/http';
import { useAuthStore } from './auth';

export const useShopStore = defineStore('shop', () => {
  const products = ref([]);
  const storefront = ref(null);
  const meta = ref({});
  const loading = ref(false);
  const error = ref(null);

  // The catalog endpoints are public and resolve the tenant from X-Company.
  function companyHeaders() {
    const companyId = useAuthStore().user?.company_id;

    return companyId ? { 'X-Company': String(companyId) } : {};
  }

  async function fetchProducts(filters = {}) {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get('/store/products', {
        headers: companyHeaders(),
        params: { per_page: 24, ...filters },
      });

      products.value = data.data ?? [];
      meta.value = data.meta ?? {};

      return products.value;
    } catch (e) {
      error.value = e?.message || 'Não foi possível carregar a loja.';
      products.value = [];

      return [];
    } finally {
      loading.value = false;
    }
  }

  async function fetchStorefront() {
    try {
      const { data } = await api.get('/storefront', { headers: companyHeaders() });
      storefront.value = data.data ?? null;

      return storefront.value;
    } catch (e) {
      storefront.value = null;

      return null;
    }
  }

  async function fetchProduct(slug) {
    const { data } = await api.get(`/store/products/${slug}`, { headers: companyHeaders() });

    return data.data;
  }

  function reset() {
    products.value = [];
    storefront.value = null;
    meta.value = {};
    error.value = null;
  }

  return {
    products,
    storefront,
    meta,
    loading,
    error,
    fetchProducts,
    fetchStorefront,
    fetchProduct,
    reset,
  };
});
