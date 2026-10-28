import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useTenantStore } from '../stores/tenant';

const LEGACY_ROUTES = {
  'public-loja': 'legacy-loja',
  'public-produto': 'legacy-produto',
  'public-checkout': 'legacy-checkout',
  'public-pedido': 'legacy-pedido',
  'public-compras': 'legacy-compras',
  'public-assinaturas': 'legacy-assinaturas',
  'public-termos': 'legacy-termos',
  'public-login': 'legacy-login',
  'public-cadastro': 'legacy-cadastro',
};

export function useTenant() {
  const route = useRoute();
  const tenant = useTenantStore();

  const legacySlug = computed(
    () => (typeof route.params.companySlug === 'string' ? route.params.companySlug : null),
  );
  const isLegacy = computed(() => Boolean(legacySlug.value));
  const companySlug = computed(() => legacySlug.value || tenant.companySlug);
  const isTenant = computed(() => isLegacy.value || tenant.isDomainMode);

  function publicRoute(name, params = {}, query = {}) {
    if (isLegacy.value && LEGACY_ROUTES[name]) {
      return {
        name: LEGACY_ROUTES[name],
        params: { companySlug: legacySlug.value, ...params },
        ...(Object.keys(query).length ? { query } : {}),
      };
    }

    return {
      name,
      params,
      ...(Object.keys(query).length ? { query } : {}),
    };
  }

  function loginRoute() {
    if (isLegacy.value) {
      return publicRoute('public-login');
    }

    return tenant.isDomainMode ? { name: 'public-login' } : { name: 'login' };
  }

  function registerRoute(query = {}) {
    if (isLegacy.value) {
      return publicRoute('public-cadastro', {}, query);
    }

    return tenant.isDomainMode
      ? { name: 'public-cadastro', ...(Object.keys(query).length ? { query } : {}) }
      : { name: 'login', ...(Object.keys(query).length ? { query } : {}) };
  }

  return {
    tenant,
    legacySlug,
    isLegacy,
    isTenant,
    companySlug,
    publicRoute,
    loginRoute,
    registerRoute,
    dashboardRoute: { name: 'dashboard' },
  };
}
