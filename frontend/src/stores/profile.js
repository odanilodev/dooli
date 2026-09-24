import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '../api/http';
import { useAuthStore } from './auth';

export const useProfileStore = defineStore('profile', () => {
  const profile = ref(null);
  const loading = ref(false);
  const loaded = ref(false);
  const error = ref(null);
  const saving = ref(false);

  async function fetch(force = false) {
    if (loaded.value && !force) {
      return profile.value;
    }

    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get('/me/profile');
      profile.value = data.data;
      loaded.value = true;

      return profile.value;
    } catch (e) {
      error.value = e?.message || 'Não foi possível carregar seu perfil.';
      return null;
    } finally {
      loading.value = false;
    }
  }

  async function update(payload) {
    saving.value = true;

    try {
      const { data } = await api.put('/me/profile', payload);
      profile.value = data.data;

      const auth = useAuthStore();
      auth.setUser(data.data);

      return data.data;
    } finally {
      saving.value = false;
    }
  }

  async function updatePassword(payload) {
    const { data } = await api.put('/me/password', payload);

    return data;
  }

  function reset() {
    profile.value = null;
    loaded.value = false;
    error.value = null;
  }

  return { profile, loading, loaded, error, saving, fetch, update, updatePassword, reset };
});
