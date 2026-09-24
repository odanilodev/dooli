import { ref } from 'vue';

const STORAGE_KEY = 'delta-class-theme';

function getInitial() {
  try {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved === 'dark' || saved === 'light') return saved;
  } catch (e) {
    /* localStorage unavailable */
  }
  const prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
  return prefersLight ? 'light' : 'dark';
}

const theme = ref(getInitial());

function apply(value) {
  theme.value = value;
  document.documentElement.setAttribute('data-theme', value);
  try {
    localStorage.setItem(STORAGE_KEY, value);
  } catch (e) {
    /* localStorage unavailable */
  }
}

apply(theme.value);

export function useTheme() {
  function toggleTheme() {
    apply(theme.value === 'dark' ? 'light' : 'dark');
  }
  function setTheme(value) {
    apply(value);
  }
  return { theme, toggleTheme, setTheme };
}
