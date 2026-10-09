import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api } from '../api/http';
import { contrastInk, darken, lighten, normalizeHex, rgba } from '../utils/color';

const STORAGE_KEY = 'delta-class-branding';

export const DEFAULT_PRIMARY_COLOR = '#17c993';

function readCache() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);

    return raw ? JSON.parse(raw) : null;
  } catch (e) {
    return null;
  }
}

function writeCache(value) {
  try {
    if (value) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
    } else {
      localStorage.removeItem(STORAGE_KEY);
    }
  } catch (e) {
    /* localStorage unavailable */
  }
}

export const useBrandingStore = defineStore('branding', () => {
  const cached = readCache();
  const primaryColor = ref(cached?.primary_color || null);
  const logoUrl = ref(cached?.logo_url || null);
  const loaded = ref(false);

  const hasLogo = computed(() => Boolean(logoUrl.value));

  function apply() {
    const root = document.documentElement;
    const color = normalizeHex(primaryColor.value);

    if (!color) {
      ['--accent', '--accent-2', '--accent-grad', '--accent-ink', '--accent-soft'].forEach((token) => {
        root.style.removeProperty(token);
      });

      return;
    }

    root.style.setProperty('--accent', color);
    root.style.setProperty('--accent-2', darken(color, 0.14));
    root.style.setProperty(
      '--accent-grad',
      `linear-gradient(135deg, ${lighten(color, 0.08)}, ${darken(color, 0.1)})`,
    );
    root.style.setProperty('--accent-ink', contrastInk(color));
    root.style.setProperty('--accent-soft', rgba(color, 0.14));
  }

  function set(data) {
    primaryColor.value = data?.primary_color || null;
    logoUrl.value = data?.logo_url || null;
    writeCache({ primary_color: primaryColor.value, logo_url: logoUrl.value });
    apply();
  }

  async function fetch() {
    try {
      const { data } = await api.get('/branding');
      set(data.data);

      return data.data;
    } catch (e) {
      apply();

      return null;
    } finally {
      loaded.value = true;
    }
  }

  function reset() {
    primaryColor.value = null;
    logoUrl.value = null;
    loaded.value = false;
    writeCache(null);
    apply();
  }

  apply();

  return { primaryColor, logoUrl, loaded, hasLogo, fetch, set, apply, reset };
});
