<template>
  <aside class="sidebar" :class="{ open: isOpen }">
    <router-link :to="{ name: 'dashboard' }" class="brand" @click="close">
      <BrandLogo />
    </router-link>

    <nav class="nav">
      <template v-if="auth.canAccessAdmin">
        <NavItem v-for="node in menuTree" :key="node.id" :node="node" />
      </template>

      <template v-else>
        <router-link :to="{ name: 'dashboard' }" class="nav-link" :class="{ active: $route.name === 'dashboard' }" @click="close">
          <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v9a1 1 0 0 0 1 1h3v-6h4v6h3a1 1 0 0 0 1-1v-9"/></svg>
          <span>Início</span>
        </router-link>
        <router-link to="/cursos" class="nav-link" :class="{ active: ['cursos', 'curso', 'aula'].includes($route.name) }" @click="close">
          <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3.5"/><path d="M10 9l5 3-5 3V9z" fill="currentColor" stroke="none"/></svg>
          <span>Meus cursos</span>
        </router-link>
        <router-link
          to="/aulas-ao-vivo"
          class="nav-link"
          :class="{ active: $route.name === 'aulas-ao-vivo', 'has-live': studentLive }"
          :aria-label="studentLive ? 'Aulas ao vivo — transmissão em andamento' : undefined"
          @click="close"
        >
          <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><rect x="2" y="6" width="14" height="12" rx="2.5"/><path d="m16 10 6-3v10l-6-3z"/><circle cx="7" cy="12" r="1.6" fill="currentColor" stroke="none"/></svg>
          <span>Aulas ao vivo</span>
          <span v-if="studentLive" class="nav-live-badge">
            <span class="nav-live-dot" aria-hidden="true"></span>
            AO VIVO
          </span>
        </router-link>
        <router-link to="/certificados" class="nav-link" :class="{ active: ['certs', 'certificado'].includes($route.name) }" @click="close">
          <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="9" r="5"/><path d="M8.5 13.5 7 21l5-2.5L17 21l-1.5-7.5"/></svg>
          <span>Meus certificados</span>
        </router-link>
        <template v-if="robots.hasAny">
          <span class="nav-group-label">Ferramentas</span>
          <router-link
            v-for="robot in robots.items"
            :key="robot.id"
            :to="{ name: 'robo', params: { slug: robot.slug } }"
            class="nav-link"
            :class="{ active: $route.name === 'robo' && $route.params.slug === robot.slug }"
            @click="close"
          >
            <NavIcon name="robots" />
            <span>{{ robot.name }}</span>
          </router-link>
        </template>
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
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useSidebar } from '../composables/useSidebar';
import { useAuthStore } from '../stores/auth';
import { useLiveClassesStore } from '../stores/liveClasses';
import { useMenusStore } from '../stores/menus';
import { useNavMenu } from '../composables/useNavMenu';
import BrandLogo from './BrandLogo.vue';
import NavIcon from './NavIcon.vue';
import NavItem from './NavItem.vue';
import { useRobotsStore } from '../stores/robots';

const { isOpen, close } = useSidebar();
const auth = useAuthStore();
const live = useLiveClassesStore();
const menus = useMenusStore();
const robots = useRobotsStore();
const route = useRoute();
const { openPath, isActive } = useNavMenu();

const studentLive = computed(() => !auth.canAccessAdmin && live.hasLiveNow);

const FALLBACK_MENU = [
  { id: 'fb-dashboard', name: 'Dashboard', type: 'link', depth: 0, link: '/inicio', icon: 'dashboard', children: [] },
  {
    id: 'fb-admin',
    name: 'Administração',
    type: 'section',
    depth: 0,
    link: null,
    icon: null,
    children: [
      { id: 'fb-content', name: 'Cursos', type: 'link', depth: 1, link: '/admin/cursos', icon: 'content' },
      { id: 'fb-modules', name: 'Módulos', type: 'link', depth: 1, link: '/admin/modulos', icon: 'modules' },
      { id: 'fb-live-classes', name: 'Aulas ao vivo', type: 'link', depth: 1, link: '/admin/aulas-ao-vivo', icon: 'live' },
      { id: 'fb-teams', name: 'Turmas', type: 'link', depth: 1, link: '/admin/turmas', icon: 'teams' },
      { id: 'fb-reports', name: 'Relatórios', type: 'link', depth: 1, link: '/admin/relatorios', icon: 'report' },
      { id: 'fb-comments', name: 'Comentários', type: 'link', depth: 1, link: '/admin/comentarios', icon: 'comments' },
      { id: 'fb-videos', name: 'Vídeos', type: 'link', depth: 1, link: '/admin/videos', icon: 'videos' },
      { id: 'fb-documents', name: 'Documentos', type: 'link', depth: 1, link: '/admin/documentos', icon: 'documents' },
      { id: 'fb-attachments', name: 'Anexos', type: 'link', depth: 1, link: '/admin/anexos', icon: 'attachments' },
      { id: 'fb-banners', name: 'Banners', type: 'link', depth: 1, link: '/admin/banners', icon: 'banners' },
      { id: 'fb-products', name: 'Produtos', type: 'link', depth: 1, link: '/admin/produtos', icon: 'store' },
      { id: 'fb-storefront', name: 'Loja', type: 'link', depth: 1, link: '/admin/loja', icon: 'storefront' },
      { id: 'fb-gateways', name: 'Gateways', type: 'link', depth: 1, link: '/admin/gateways', icon: 'gateway' },
      { id: 'fb-orders', name: 'Pedidos', type: 'link', depth: 1, link: '/admin/pedidos', icon: 'orders' },
      { id: 'fb-subscriptions', name: 'Assinaturas', type: 'link', depth: 1, link: '/admin/assinaturas', icon: 'subscriptions' },
      { id: 'fb-certificates', name: 'Certificados', type: 'link', depth: 1, link: '/admin/certificados', icon: 'certificate' },
      { id: 'fb-video-platforms', name: 'Plataformas de vídeo', type: 'link', depth: 1, link: '/admin/video-platforms', icon: 'video-platforms' },
      { id: 'fb-menus', name: 'Menus', type: 'link', depth: 1, link: '/admin/menus', icon: 'menu' },
      { id: 'fb-companies', name: 'Empresas', type: 'link', depth: 1, link: '/admin/empresas', icon: 'company' },
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

  if (auth.isSuperAdmin) {
    return filterOnlyLinks(base, SUPER_ADMIN_ALLOWED_LINKS);
  }

  return filterLinks(base, auth.isAdmin ? SUPER_ADMIN_ONLY_LINKS : ADMIN_ONLY_LINKS);
});

const SUPER_ADMIN_ONLY_LINKS = ['/admin/menus', '/admin/empresas'];
const ADMIN_ONLY_LINKS = [...SUPER_ADMIN_ONLY_LINKS, '/admin/usuarios', '/admin/tipos-de-usuario'];

// A platform super admin only manages companies and the global menu config.
const SUPER_ADMIN_ALLOWED_LINKS = ['/admin/empresas', '/admin/menus', '/perfil', '/configuracoes'];

function filterOnlyLinks(nodes, allowed) {
  return nodes
    .filter((node) => (node.link ? allowed.includes(node.link) : true))
    .map((node) => ({
      ...node,
      children: node.children ? filterOnlyLinks(node.children, allowed) : [],
    }))
    .filter((node) => node.link || (node.children && node.children.length));
}

function filterLinks(nodes, blocked) {
  return nodes
    .filter((node) => !blocked.includes(node.link))
    .map((node) => ({
      ...node,
      children: node.children ? filterLinks(node.children, blocked) : [],
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

function refreshLiveOnVisible() {
  if (document.visibilityState === 'visible') {
    live.fetch(true);
  }
}

onMounted(() => {
  if (auth.canAccessAdmin && !menus.loaded) {
    menus.fetchMyMenus();
  }

  if (!auth.canAccessAdmin) {
    robots.fetch();
    live.fetch();
    live.startClock();
    document.addEventListener('visibilitychange', refreshLiveOnVisible);
  }
});

onBeforeUnmount(() => {
  document.removeEventListener('visibilitychange', refreshLiveOnVisible);
  live.stopClock();
});
</script>
