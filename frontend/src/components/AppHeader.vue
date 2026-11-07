<template>
  <header class="topbar">
    <div class="topbar-left">
      <button class="hamburger" :aria-label="hasLive ? 'Abrir menu — aula ao vivo em andamento' : 'Abrir menu'" @click="toggle">
        <span v-if="hasLive" class="hamburger-live" aria-hidden="true"></span>
        <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
      <router-link :to="{ name: 'dashboard' }" class="topbar-brand" aria-label="Início">
        <BrandLogo />
      </router-link>
      <label v-if="route.meta.headerSearch" class="search">
        <svg class="icon" width="16" height="16" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <input type="text" v-model="query" :placeholder="placeholder">
      </label>
    </div>

    <div class="topbar-right">
      <button class="theme-toggle" aria-label="Alternar tema" @click="toggleTheme">
        <svg class="icon sun" width="13" height="13" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2 12h2M20 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/></svg>
        <svg class="icon moon" width="13" height="13" viewBox="0 0 24 24" stroke-width="2"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z"/></svg>
        <span class="knob"></span>
      </button>

      <div class="notif-menu" :class="{ open: notifOpen }" ref="notifRef">
        <button class="icon-btn" aria-label="Notificações" @click="notifOpen = !notifOpen">
          <svg class="icon" width="20" height="20" viewBox="0 0 24 24"><path d="M6 9a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5h-15S6 13 6 9z"/><path d="M10 18a2 2 0 0 0 4 0"/></svg>
          <span v-if="unreadCount > 0" class="badge-dot">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
        </button>

        <div class="notif-dropdown">
          <div class="notif-dropdown-head">
            <span class="notif-dropdown-title">Notificações</span>
            <button v-if="unreadCount > 0" class="notif-mark-all" type="button" @click="markAll">Marcar todas</button>
          </div>

          <div v-if="notifications.loading && !notifications.loaded" class="notif-empty">Carregando...</div>
          <div v-else-if="notifications.error" class="notif-empty">{{ notifications.error }}</div>
          <div v-else-if="notifications.items.length === 0" class="notif-empty">Nenhuma notificação.</div>
          <div v-else class="notif-list">
            <button
              v-for="item in notifications.items"
              :key="item.id"
              class="notif-item"
              :class="{ unread: isNew(item) }"
              type="button"
              @click="read(item)"
            >
              <span class="notif-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5h-15S6 13 6 9z"/><path d="M10 18a2 2 0 0 0 4 0"/></svg>
              </span>
              <span class="notif-body">
                <span class="notif-head">
                  <span class="notif-type">{{ item.title || label(item) }}</span>
                  <span v-if="isNew(item)" class="notif-dot"></span>
                </span>
                <span v-if="item.message" class="notif-message">{{ item.message }}</span>
                <span class="notif-date">{{ formatDate(item.release_at || item.created_at) }}</span>
              </span>
            </button>
          </div>
        </div>
      </div>

      <TeamSwitcher v-if="!auth.isAdmin && route.name !== 'dashboard'" />

      <div class="v-divider"></div>

      <div class="user-menu" :class="{ open: userMenuOpen }" ref="menuRef">
        <button class="user-trigger" @click="userMenuOpen = !userMenuOpen">
          <span class="avatar">{{ auth.initials }}</span>
          <span class="user-text">
            <span class="user-name">{{ auth.shortName || 'Visitante' }}</span>
            <span class="user-role">{{ auth.role || 'Aluno' }}</span>
          </span>
          <svg class="icon" width="15" height="15" viewBox="0 0 24 24" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div class="user-dropdown">
          <a href="#" class="menu-theme-item" @click.prevent="toggleTheme">
            <svg class="icon" width="15" height="15" viewBox="0 0 24 24" stroke-width="2"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z"/></svg>
            Alternar tema
          </a>
          <AccountMenuLinks
            variant="app"
            @navigate="userMenuOpen = false"
            @logout="handleLogout"
          />
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useTheme } from '../composables/useTheme';
import { useSidebar } from '../composables/useSidebar';
import { useSearch } from '../composables/useSearch';
import { useAuthStore } from '../stores/auth';
import { useLiveClassesStore } from '../stores/liveClasses';
import { useNotificationsStore } from '../stores/notifications';
import { resetUserStores } from '../stores/resetUserStores';
import AccountMenuLinks from './AccountMenuLinks.vue';
import TeamSwitcher from './TeamSwitcher.vue';
import BrandLogo from './BrandLogo.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const live = useLiveClassesStore();
const notifications = useNotificationsStore();
const { toggleTheme } = useTheme();
const { toggle } = useSidebar();
const { query } = useSearch();

const unreadCount = computed(() => notifications.unreadCount);
const hasLive = computed(() => !auth.canAccessAdmin && live.hasLiveNow);

const placeholder = computed(() => route.meta?.searchPlaceholder || 'Buscar cursos, aulas ou temas...');

watch(
  () => route.meta?.headerSearch,
  (enabled) => {
    if (!enabled) {
      query.value = '';
    }
  },
);

const userMenuOpen = ref(false);
const menuRef = ref(null);
const notifOpen = ref(false);
const notifRef = ref(null);

// Ao abrir o sininho, tudo que era não lido é marcado como visto. Os ids ficam guardados só
// enquanto o menu está aberto, para o aluno ainda enxergar quais eram novas (destaque/ponto).
const freshIds = ref(new Set());

function isNew(item) {
  return !item.read_at || freshIds.value.has(item.id);
}

watch([notifOpen, unreadCount], async ([open, unread]) => {
  if (!open) {
    freshIds.value = new Set();
    return;
  }

  if (unread > 0) {
    freshIds.value = new Set([
      ...freshIds.value,
      ...notifications.items.filter((item) => !item.read_at).map((item) => item.id),
    ]);

    try {
      await notifications.markAllAsRead();
    } catch (e) {
      /* tenta de novo na próxima abertura */
    }
  }
});

const NOTIF_LABELS = {
  course_completed: 'Curso concluído',
  certificate_available: 'Certificado disponível',
  lesson_updated: 'Aula atualizada',
  certificate_recalculation_required: 'Certificado precisa ser recalculado',
};

function label(item) {
  return NOTIF_LABELS[item.type?.slug] || item.type?.name || 'Notificação';
}

function formatDate(value) {
  if (!value) {
    return '';
  }

  try {
    return new Date(value).toLocaleString('pt-BR', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch (e) {
    return '';
  }
}

async function read(item) {
  try {
    await notifications.markAsRead(item.id);
  } catch (e) {
    /* mantém como está em caso de falha */
  }

  if (item.action_url) {
    notifOpen.value = false;
    router.push(item.action_url.startsWith('/') ? item.action_url : `/${item.action_url}`);
  }
}

async function markAll() {
  try {
    await notifications.markAllAsRead();
  } catch (e) {
    /* ignora falha */
  }
}

async function handleLogout() {
  userMenuOpen.value = false;
  await auth.logout();
  resetUserStores();
  router.push({ name: 'login' });
}

function onClickOutside(event) {
  if (menuRef.value && !menuRef.value.contains(event.target)) {
    userMenuOpen.value = false;
  }

  if (notifRef.value && !notifRef.value.contains(event.target)) {
    notifOpen.value = false;
  }
}

onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>
