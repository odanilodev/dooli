import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api, getToken, setCompanyId, setToken } from '../api/http';
import { useBrandingStore } from './branding';
import { useTenantStore } from './tenant';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const token = ref(getToken());
  const loading = ref(false);
  const initialized = ref(false);

  const isAuthenticated = computed(() => Boolean(token.value));
  const role = computed(() => user.value?.user_type?.name || null);
  const isAdmin = computed(() => user.value?.user_type?.code === 'admin');
  const isStudent = computed(() => user.value?.user_type?.code === 'student');
  const isSuperAdmin = computed(() => user.value?.is_super_admin === true);
  const canAccessAdmin = computed(
    () => isAdmin.value || (user.value?.user_type?.permission_menu?.length ?? 0) > 0,
  );
  // Só o primeiro e o último nome (ex.: "Luis Felipe Santos Silva" -> "Luis Silva").
  const shortName = computed(() => {
    const parts = user.value?.name?.trim().split(/\s+/).filter(Boolean) ?? [];

    if (!parts.length) {
      return '';
    }

    return parts.length > 1 ? `${parts[0]} ${parts[parts.length - 1]}` : parts[0];
  });

  const initials = computed(() => {
    const name = user.value?.name?.trim();

    if (!name) {
      return '?';
    }

    const parts = name.split(/\s+/);

    return (
      (parts[0]?.[0] || '') + (parts.length > 1 ? parts[parts.length - 1][0] : '')
    ).toUpperCase();
  });

  function setUser(value) {
    user.value = value;
  }

  function clear() {
    user.value = null;
    token.value = null;
    setToken(null);
  }

  function applySession(payload) {
    token.value = payload.token;
    setToken(token.value);
    user.value = payload.user;
    setCompanyId(user.value.company_id);
    useBrandingStore().fetch();

    return user.value;
  }

  function tenantEndpoint(companySlug, action) {
    if (companySlug) {
      return `/public/companies/${encodeURIComponent(companySlug)}/auth/${action}`;
    }

    return useTenantStore().isDomainMode
      ? `/public/tenant/auth/${action}`
      : `/auth/${action}`;
  }

  async function login(credentials, companySlug = null) {
    loading.value = true;

    try {
      const endpoint = tenantEndpoint(companySlug, 'login');

      const { data } = await api.post(endpoint, {
        email: credentials.email,
        password: credentials.password,
        device_name: 'web',
      });

      return applySession(data.data);
    } finally {
      loading.value = false;
    }
  }

  async function register(payload, companySlug = null) {
    loading.value = true;

    try {
      const endpoint = tenantEndpoint(companySlug, 'register');

      const { data } = await api.post(endpoint, {
        ...payload,
        device_name: 'web',
      });

      return data.data;
    } finally {
      loading.value = false;
    }
  }

  async function verifyEmail(payload, companySlug = null) {
    loading.value = true;

    try {
      const endpoint = tenantEndpoint(companySlug, 'verify-email');

      const { data } = await api.post(endpoint, {
        ...payload,
        device_name: 'web',
      });

      return applySession(data.data);
    } finally {
      loading.value = false;
    }
  }

  async function resendVerification(email, companySlug = null) {
    const endpoint = tenantEndpoint(companySlug, 'resend-verification');

    const { data } = await api.post(endpoint, { email });

    return data;
  }

  async function forgotPassword(email, companySlug = null) {
    const endpoint = tenantEndpoint(companySlug, 'forgot-password');

    const { data } = await api.post(endpoint, { email });

    return data;
  }

  async function resetPassword(payload, companySlug = null) {
    const endpoint = tenantEndpoint(companySlug, 'reset-password');

    const { data } = await api.post(endpoint, payload);

    return data;
  }

  async function fetchMe() {
    if (!token.value) {
      return null;
    }

    try {
      const { data } = await api.get('/auth/me');
      user.value = data.data;
      setCompanyId(user.value.company_id);
      useBrandingStore().fetch();

      return user.value;
    } catch (error) {
      if (error.status === 401) {
        clear();
      }

      return null;
    }
  }

  async function logout() {
    try {
      await api.post('/auth/logout');
    } catch (error) {
      /* token already invalid: proceed with local cleanup */
    }

    clear();
  }

  async function init() {
    if (initialized.value) {
      return;
    }

    if (token.value) {
      await fetchMe();
    }

    initialized.value = true;
  }

  return {
    user,
    token,
    loading,
    initialized,
    isAuthenticated,
    role,
    isAdmin,
    isStudent,
    isSuperAdmin,
    canAccessAdmin,
    initials,
    shortName,
    login,
    register,
    verifyEmail,
    resendVerification,
    forgotPassword,
    resetPassword,
    fetchMe,
    setUser,
    logout,
    clear,
    init,
  };
});
