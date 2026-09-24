<template>
  <div class="auth-shell">
    <button class="theme-toggle auth-theme-toggle" aria-label="Alternar tema" @click="toggleTheme">
      <svg class="icon sun" width="13" height="13" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2 12h2M20 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/></svg>
      <svg class="icon moon" width="13" height="13" viewBox="0 0 24 24" stroke-width="2"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z"/></svg>
      <span class="knob"></span>
    </button>

    <div class="auth-visual">
      <span class="auth-visual-glow"></span>
      <router-link to="/" class="brand" style="position:relative;">
        <span class="brand-mark">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l9 18H3z"/></svg>
        </span>
        <span class="brand-text">
          <span class="brand-name" style="color:#fff;">DELTA CLASS</span>
          <span class="brand-tag" style="color:#c7d2de;">Conhecimento que gera resultados</span>
        </span>
      </router-link>
      <p class="auth-visual-quote">{{ route.meta.quote }} <span>{{ route.meta.quoteHighlight }}</span></p>
      <span class="auth-visual-foot">© 2026 Delta Class. Todos os direitos reservados.</span>
    </div>

    <div class="auth-form-side">
      <router-view />
    </div>
  </div>
</template>

<script setup>
import { useRoute } from 'vue-router';
import { useTheme } from '../composables/useTheme';

const route = useRoute();
const { toggleTheme } = useTheme();
</script>
