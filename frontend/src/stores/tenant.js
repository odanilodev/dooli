import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api, getCompanyDomain } from '../api/http';
import { useBrandingStore } from './branding';

const DEFAULT_PLATFORM_DOMAINS = ['localhost', '127.0.0.1'];

function platformDomains() {
  const configured = String(import.meta.env.VITE_PLATFORM_DOMAINS || '')
    .split(',')
    .map((domain) => domain.trim().toLowerCase())
    .filter(Boolean);

  return configured.length ? configured : DEFAULT_PLATFORM_DOMAINS;
}

export const useTenantStore = defineStore('tenant', () => {
  const domain = ref(getCompanyDomain());
  const company = ref(null);
  const resolved = ref(false);
  const loading = ref(false);
  const error = ref(null);

  const isPlatform = computed(() => platformDomains().includes(domain.value));
  const isDomainMode = computed(() => !isPlatform.value);
  const companySlug = computed(() => company.value?.slug || '');

  async function resolve(force = false) {
    if (resolved.value && !force) {
      return company.value;
    }

    error.value = null;

    if (!isDomainMode.value) {
      company.value = null;
      resolved.value = true;

      return null;
    }

    loading.value = true;

    try {
      const { data } = await api.get('/public/tenant');
      company.value = data.data;
      useBrandingStore().set(data.data?.branding);

      return company.value;
    } catch (e) {
      company.value = null;
      error.value = e?.message || 'Domínio da empresa não configurado.';

      return null;
    } finally {
      loading.value = false;
      resolved.value = true;
    }
  }

  function reset() {
    company.value = null;
    error.value = null;
    resolved.value = false;
  }

  return {
    domain,
    company,
    companySlug,
    resolved,
    loading,
    error,
    isPlatform,
    isDomainMode,
    resolve,
    reset,
  };
});
