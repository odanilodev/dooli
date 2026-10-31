<template>
  <div class="app-shell">
    <div class="sidebar-backdrop" :class="{ open: isOpen }" @click="close"></div>

    <AppSidebar />

    <div class="main-col">
      <AppHeader />
      <router-view />
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';
import AppSidebar from '../components/AppSidebar.vue';
import AppHeader from '../components/AppHeader.vue';
import { useSidebar } from '../composables/useSidebar';
import { useNotificationsStore } from '../stores/notifications';
import { useAuthStore } from '../stores/auth';
import { useStudentTeamsStore } from '../stores/studentTeams';

const { isOpen, close } = useSidebar();
const notifications = useNotificationsStore();
const auth = useAuthStore();
const teams = useStudentTeamsStore();

let refreshTimer;

onMounted(() => {
  notifications.fetch();

  if (!auth.isAdmin) {
    teams.fetchTeams();
  }

  refreshTimer = window.setInterval(() => notifications.refresh(), 60000);
});

onUnmounted(() => {
  window.clearInterval(refreshTimer);
});
</script>
