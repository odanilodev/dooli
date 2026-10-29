const STORAGE_PREFIX = 'delta-class-referral';
const MAX_AGE_MS = 30 * 24 * 60 * 60 * 1000;

function normalizeCode(value) {
  const code = String(value ?? '').trim().toLowerCase();

  return /^[a-z0-9][a-z0-9_-]{0,31}$/.test(code) ? code : null;
}

function storageKey(companySlug) {
  const slug = String(companySlug ?? '').trim().toLowerCase();

  return `${STORAGE_PREFIX}:${slug || 'default'}`;
}

export function useReferral() {
  function capture(companySlug, code) {
    const normalized = normalizeCode(code);

    if (!normalized) {
      return null;
    }

    try {
      localStorage.setItem(storageKey(companySlug), JSON.stringify({
        code: normalized,
        captured_at: Date.now(),
      }));
    } catch (e) {
      /* localStorage unavailable */
    }

    return normalized;
  }

  function current(companySlug) {
    try {
      const raw = localStorage.getItem(storageKey(companySlug));

      if (!raw) {
        return null;
      }

      const payload = JSON.parse(raw);
      const age = Date.now() - Number(payload?.captured_at || 0);
      const code = normalizeCode(payload?.code);

      if (!code || age < 0 || age > MAX_AGE_MS) {
        clear(companySlug);

        return null;
      }

      return code;
    } catch (e) {
      return null;
    }
  }

  function clear(companySlug) {
    try {
      localStorage.removeItem(storageKey(companySlug));
    } catch (e) {
      /* localStorage unavailable */
    }
  }

  function captureFromRoute(companySlug, ref) {
    return capture(companySlug, ref);
  }

  return {
    capture,
    current,
    clear,
    captureFromRoute,
  };
}
