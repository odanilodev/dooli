<template>
  <aside class="sidebar" :class="{ open: isOpen }">
    <router-link to="/" class="brand" @click="close">
      <span class="brand-mark">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l9 18H3z"/></svg>
      </span>
      <span class="brand-text">
        <span class="brand-name">DELTA CLASS</span>
        <span class="brand-tag">Conhecimento que gera resultados</span>
      </span>
    </router-link>

    <nav class="nav">
      <router-link to="/" class="nav-link" :class="{ active: $route.name === 'dashboard' }" @click="close">
        <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v9a1 1 0 0 0 1 1h3v-6h4v6h3a1 1 0 0 0 1-1v-9"/></svg>
        <span>Início</span>
      </router-link>
      <router-link to="/cursos" class="nav-link" :class="{ active: ['cursos', 'curso', 'aula'].includes($route.name) }" @click="close">
        <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3.5"/><path d="M10 9l5 3-5 3V9z" fill="currentColor" stroke="none"/></svg>
        <span>Meus cursos</span>
      </router-link>
      <a href="#" class="nav-link" @click.prevent>
        <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><path d="M6 9a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5h-15S6 13 6 9z"/><path d="M10 18a2 2 0 0 0 4 0"/></svg>
        <span style="flex-grow:1;">Notificações</span>
        <span class="nav-badge">2</span>
      </a>
      <router-link to="/perfil" class="nav-link" :class="{ active: $route.name === 'perfil' }" @click="close">
        <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.9 3.1-7 7-7s7 3.1 7 7"/></svg>
        <span>Meu perfil</span>
      </router-link>
      <router-link to="/configuracoes" class="nav-link" :class="{ active: $route.name === 'configuracoes' }" @click="close">
        <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></svg>
        <span>Configurações</span>
      </router-link>
    </nav>

    <div class="sidebar-spacer"></div>

    <div class="help-card">
      <span class="help-icon">
        <svg class="icon" width="17" height="17" viewBox="0 0 24 24"><path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="3" y="13" width="4" height="6" rx="1.5"/><rect x="17" y="13" width="4" height="6" rx="1.5"/><path d="M20 19v1a3 3 0 0 1-3 3h-3"/></svg>
      </span>
      <span class="help-title">Precisa de ajuda?</span>
      <span class="help-text">Nosso suporte está pronto para te atender.</span>
      <a class="help-link" href="#" @click.prevent>Falar com o suporte →</a>
    </div>
  </aside>
</template>

<script setup>
import { useSidebar } from '../composables/useSidebar';

const { isOpen, close } = useSidebar();
</script>
