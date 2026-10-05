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
      <template v-if="auth.canAccessAdmin">
        <NavItem v-for="node in menuTree" :key="node.id" :node="node" />
      </template>

      <template v-else>
        <router-link to="/" class="nav-link" :class="{ active: $route.name === 'dashboard' }" @click="close">
          <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v9a1 1 0 0 0 1 1h3v-6h4v6h3a1 1 0 0 0 1-1v-9"/></svg>
          <span>Início</span>
        </router-link>
        <router-link to="/cursos" class="nav-link" :class="{ active: ['cursos', 'curso', 'aula'].includes($route.name) }" @click="close">
          <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3.5"/><path d="M10 9l5 3-5 3V9z" fill="currentColor" stroke="none"/></svg>
          <span>Meus cursos</span>
        </router-link>
        <router-link to="/perfil" class="nav-link" :class="{ active: $route.name === 'perfil' }" @click="close">
          <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.9 3.1-7 7-7s7 3.1 7 7"/></svg>
          <span>Meu perfil</span>
        </router-link>
        <router-link to="/configuracoes" class="nav-link" :class="{ active: $route.name === 'configuracoes' }" @click="close">
          <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></svg>
          <span>Configurações</span>
        </router-link>
      </template>
    </nav>

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
import { computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useSidebar } from '../composables/useSidebar';
import { useAuthStore } from '../stores/auth';
import { useMenusStore } from '../stores/menus';
import { useNavMenu } from '../composables/useNavMenu';
import NavItem from './NavItem.vue';

const { isOpen, close } = useSidebar();
const auth = useAuthStore();
const menus = useMenusStore();
const route = useRoute();
const { openPath, isActive } = useNavMenu();

const FALLBACK_MENU = [
  { id: 'fb-dashboard', name: 'Dashboard', type: 'link', depth: 0, link: '/', icon: 'dashboard', children: [] },
  {
    id: 'fb-admin',
    name: 'Administração',
    type: 'section',
    depth: 0,
    link: null,
    icon: null,
    children: [
      { id: 'fb-content', name: 'Cursos', type: 'link', depth: 1, link: '/admin/cursos', icon: 'content' },
      { id: 'fb-teams', name: 'Turmas', type: 'link', depth: 1, link: '/admin/turmas', icon: 'teams' },
      { id: 'fb-videos', name: 'Vídeos', type: 'link', depth: 1, link: '/admin/videos', icon: 'videos' },
      { id: 'fb-documents', name: 'Documentos', type: 'link', depth: 1, link: '/admin/documentos', icon: 'documents' },
      { id: 'fb-attachments', name: 'Anexos', type: 'link', depth: 1, link: '/admin/anexos', icon: 'attachments' },
      { id: 'fb-banners', name: 'Banners', type: 'link', depth: 1, link: '/admin/banners', icon: 'banners' },
      { id: 'fb-video-platforms', name: 'Plataformas de vídeo', type: 'link', depth: 1, link: '/admin/video-platforms', icon: 'video-platforms' },
      { id: 'fb-menus', name: 'Menus', type: 'link', depth: 1, link: '/admin/menus', icon: 'menu' },
      { id: 'fb-users', name: 'Usuários', type: 'link', depth: 1, link: '/admin/usuarios', icon: 'users' },
      { id: 'fb-user-types', name: 'Tipos de usuário', type: 'link', depth: 1, link: '/admin/tipos-de-usuario', icon: 'user-types' },
    ],
  },
  {
    id: 'fb-account',
    name: 'Conta',
    type: 'section',
    depth: 0,
    link: null,
    icon: null,
    children: [
      { id: 'fb-profile', name: 'Meu perfil', type: 'link', depth: 1, link: '/perfil', icon: 'profile' },
      { id: 'fb-settings', name: 'Configurações', type: 'link', depth: 1, link: '/configuracoes', icon: 'settings' },
    ],
  },
];

const menuTree = computed(() => {
  const base = menus.tree.length ? menus.tree : FALLBACK_MENU;

  return auth.isAdmin ? base : filterAdminOnly(base);
});

const ADMIN_ONLY_LINKS = ['/admin/menus', '/admin/usuarios', '/admin/tipos-de-usuario'];

function filterAdminOnly(nodes) {
  return nodes
    .filter((node) => !ADMIN_ONLY_LINKS.includes(node.link))
    .map((node) => ({
      ...node,
      children: node.children ? filterAdminOnly(node.children) : [],
    }))
    .filter((node) => !['section', 'dropdown'].includes(node.type) || (node.children && node.children.length));
}

function findTrail(nodes, trail = []) {
  for (const node of nodes) {
    const nextTrail = node.type === 'dropdown' ? [...trail, [node.depth, node.id]] : trail;

    if (isActive(node.link)) {
      return nextTrail;
    }

    if (node.children && node.children.length) {
      const found = findTrail(node.children, nextTrail);

      if (found) {
        return found;
      }
    }
  }

  return null;
}

watch(
  [() => route.path, menuTree],
  () => {
    const trail = findTrail(menuTree.value);

    if (trail && trail.length) {
      openPath(trail);
    }
  },
  { immediate: true },
);

onMounted(() => {
  if (auth.canAccessAdmin && !menus.loaded) {
    menus.fetchMyMenus();
  }
});
</script>
