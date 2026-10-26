import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api } from '../api/http';
import { useBrandingStore } from './branding';
import { useTenantStore } from './tenant';

export const useStorefrontStore = defineStore('storefront', () => {
  const company = ref(null);
  const products = ref([]);
  const meta = ref({});
  const loading = ref(false);
  const error = ref(null);

  const gateways = computed(() => company.value?.gateways ?? []);
  const defaultGateway = computed(() => {
    return gateways.value.find((gateway) => gateway.code === 'mercadopago') ?? gateways.value[0] ?? null;
  });

  function domainCompany(slug) {
    const tenant = useTenantStore();

    return tenant.isDomainMode && (!slug || slug === tenant.company?.slug);
  }

  function productsEndpoint(slug) {
    return domainCompany(slug)
      ? '/public/tenant/store/products'
      : `/public/companies/${encodeURIComponent(slug)}/store/products`;
  }

  async function loadCompany(slug) {
    const tenant = useTenantStore();

    if (domainCompany(slug) && tenant.company) {
      company.value = tenant.company;
      useBrandingStore().set(tenant.company?.branding);

      return company.value;
    }

    if (company.value?.slug === slug) {
      return company.value;
    }

    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get(`/public/companies/${encodeURIComponent(slug)}`);
      company.value = data.data;
      useBrandingStore().set(data.data?.branding);

      return company.value;
    } catch (e) {
      company.value = null;
      error.value = e?.message || 'Loja indisponível.';

      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function fetchProducts(slug, filters = {}) {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get(productsEndpoint(slug), {
        params: { per_page: 60, ...filters },
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

  async function fetchProduct(slug, productSlug) {
    const endpoint = domainCompany(slug)
      ? `/public/tenant/store/products/${encodeURIComponent(productSlug)}`
      : `/public/companies/${encodeURIComponent(slug)}/store/products/${encodeURIComponent(productSlug)}`;

    const { data } = await api.get(endpoint);

    return data.data;
  }

  function reset() {
    company.value = null;
    products.value = [];
    meta.value = {};
    error.value = null;
  }

  return {
    company,
    products,
    meta,
    loading,
    error,
    gateways,
    defaultGateway,
    loadCompany,
    fetchProducts,
    fetchProduct,
    reset,
  };
});
