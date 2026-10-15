import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api, getToken, setCompanyId, setToken } from '../api/http';
import { useBrandingStore } from './branding';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const token = ref(getToken());
  const loading = ref(false);
  const initialized = ref(false);

  const isAuthenticated = computed(() => Boolean(token.value));
  const role = computed(() => user.value?.user_type?.name || null);
  const isAdmin = computed(() => user.value?.user_type?.code === 'admin');
  const isSuperAdmin = computed(() => user.value?.is_super_admin === true);
  const canAccessAdmin = computed(
    () => isAdmin.value || (user.value?.user_type?.permission_menu?.length ?? 0) > 0,
  );
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

  async function login(credentials) {
    loading.value = true;

    try {
      const { data } = await api.post('/auth/login', {
        email: credentials.email,
        password: credentials.password,
        device_name: 'web',
      });

      token.value = data.data.token;
      setToken(token.value);
      user.value = data.data.user;
      setCompanyId(user.value.company_id);
      useBrandingStore().fetch();

      return user.value;
    } finally {
      loading.value = false;
    }
  }

  async function forgotPassword(email) {
    const { data } = await api.post('/auth/forgot-password', { email });

    return data;
  }

  async function resetPassword(payload) {
    const { data } = await api.post('/auth/reset-password', payload);

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
    isSuperAdmin,
    canAccessAdmin,
    initials,
    login,
    forgotPassword,
    resetPassword,
    fetchMe,
    setUser,
    logout,
    clear,
    init,
  };
});
