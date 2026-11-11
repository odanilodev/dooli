import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api } from '../api/http';
import { useTheme } from '../composables/useTheme';
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
  const { theme } = useTheme();
  const cached = readCache();
  const companyName = ref(cached?.company_name || null);
  const primaryColor = ref(cached?.primary_color || null);
  const logoLightUrl = ref(cached?.logo_light_url || cached?.logo_url || null);
  const logoDarkUrl = ref(cached?.logo_dark_url || null);
  const loaded = ref(false);
  let platformApplied = false;
  let fallbackRequested = false;

  // Logo padrão (Dooli): usada quando a empresa do domínio/subdomínio ainda não cadastrou logo.
  const fallbackLogoLightUrl = ref(null);
  const fallbackLogoDarkUrl = ref(null);

  const logoUrl = computed(() => {
    if (theme.value === 'dark') {
      return logoDarkUrl.value || logoLightUrl.value
        || fallbackLogoDarkUrl.value || fallbackLogoLightUrl.value;
    }

    return logoLightUrl.value || logoDarkUrl.value
      || fallbackLogoLightUrl.value || fallbackLogoDarkUrl.value;
  });

  // Telas sobre fundo sempre escuro (login/cadastro) usam a logo do tema escuro, qualquer que seja o tema do usuário.
  const logoUrlOnDark = computed(() => logoDarkUrl.value || logoLightUrl.value
    || fallbackLogoDarkUrl.value || fallbackLogoLightUrl.value);

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

  function set(data, { persist = true } = {}) {
    companyName.value = data?.company_name || null;
    primaryColor.value = data?.primary_color || null;
    logoLightUrl.value = data?.logo_light_url || data?.logo_url || null;
    logoDarkUrl.value = data?.logo_dark_url || null;
    if (persist) {
      writeCache({
        company_name: companyName.value,
        primary_color: primaryColor.value,
        logo_light_url: logoLightUrl.value,
        logo_dark_url: logoDarkUrl.value,
      });
    }

    apply();

    if (!logoLightUrl.value && !logoDarkUrl.value) {
      loadFallbackLogo();
    }
  }

  async function loadFallbackLogo() {
    if (fallbackRequested) {
      return;
    }

    fallbackRequested = true;

    try {
      const slug = import.meta.env.VITE_PLATFORM_COMPANY_SLUG || 'dooli';
      const { data } = await api.get(`/public/companies/${encodeURIComponent(slug)}`);
      const platform = data.data?.branding;

      fallbackLogoLightUrl.value = platform?.logo_light_url || platform?.logo_url || null;
      fallbackLogoDarkUrl.value = platform?.logo_dark_url || null;
    } catch (e) {
      fallbackRequested = false;
    }
  }

  // Identidade padrão da plataforma (domínio principal, sem usuário logado): a empresa
  // dona da plataforma (Dooli). Não é gravada no cache para não vazar para o login de outra empresa.
  async function ensurePlatformDefault() {
    if (platformApplied) {
      return;
    }

    platformApplied = true;

    try {
      const slug = import.meta.env.VITE_PLATFORM_COMPANY_SLUG || 'dooli';
      const { data } = await api.get(`/public/companies/${encodeURIComponent(slug)}`);
      set(data.data?.branding, { persist: false });
    } catch (e) {
      platformApplied = false;
    }
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
    platformApplied = false;
    companyName.value = null;
    primaryColor.value = null;
    logoLightUrl.value = null;
    logoDarkUrl.value = null;
    loaded.value = false;
    writeCache(null);
    apply();
  }

  apply();

  return {
    companyName,
    primaryColor,
    logoLightUrl,
    logoDarkUrl,
    logoUrl,
    logoUrlOnDark,
    loaded,
    hasLogo,
    fetch,
    set,
    ensurePlatformDefault,
    apply,
    reset,
  };
});
