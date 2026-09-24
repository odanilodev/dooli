<template>
  <header class="topbar">
    <div class="topbar-left">
      <button class="hamburger" aria-label="Abrir menu" @click="toggle">
        <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
      <label class="search">
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

      <button class="icon-btn" aria-label="Notificações">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24"><path d="M6 9a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5h-15S6 13 6 9z"/><path d="M10 18a2 2 0 0 0 4 0"/></svg>
        <span class="badge-dot">2</span>
      </button>

      <div class="v-divider"></div>

      <div class="user-menu" :class="{ open: userMenuOpen }" ref="menuRef">
        <button class="user-trigger" @click="userMenuOpen = !userMenuOpen">
          <span class="avatar">{{ auth.initials }}</span>
          <span class="user-text">
            <span class="user-name">{{ auth.user?.name || 'Visitante' }}</span>
            <span class="user-role">{{ auth.role || 'Aluno' }}</span>
          </span>
          <svg class="icon" width="15" height="15" viewBox="0 0 24 24" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div class="user-dropdown">
          <router-link to="/perfil" @click="userMenuOpen = false"><svg class="icon" width="15" height="15" viewBox="0 0 24 24" stroke-width="1.8"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.9 3.1-7 7-7s7 3.1 7 7"/></svg>Meu perfil</router-link>
          <router-link to="/configuracoes" @click="userMenuOpen = false"><svg class="icon" width="15" height="15" viewBox="0 0 24 24" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></svg>Configurações</router-link>
          <a href="#" @click.prevent="handleLogout"><svg class="icon" width="15" height="15" viewBox="0 0 24 24" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>Sair</a>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useTheme } from '../composables/useTheme';
import { useSidebar } from '../composables/useSidebar';
import { useSearch } from '../composables/useSearch';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { toggleTheme } = useTheme();
const { toggle } = useSidebar();
const { query } = useSearch();

const placeholder = computed(() => route.meta?.searchPlaceholder || 'Buscar cursos, aulas ou temas...');

const userMenuOpen = ref(false);
const menuRef = ref(null);

async function handleLogout() {
  userMenuOpen.value = false;
  await auth.logout();
  router.push({ name: 'login' });
}

function onClickOutside(event) {
  if (menuRef.value && !menuRef.value.contains(event.target)) {
    userMenuOpen.value = false;
  }
}

onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>
