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
import { onMounted } from 'vue';
import AppSidebar from '../components/AppSidebar.vue';
import AppHeader from '../components/AppHeader.vue';
import { useSidebar } from '../composables/useSidebar';
import { useNotificationsStore } from '../stores/notifications';

const { isOpen, close } = useSidebar();
const notifications = useNotificationsStore();

onMounted(() => {
  notifications.fetch();
});
</script>
