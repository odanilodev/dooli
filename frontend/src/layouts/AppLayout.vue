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
import AppSidebar from '../components/AppSidebar.vue';
import AppHeader from '../components/AppHeader.vue';
import { useSidebar } from '../composables/useSidebar';

const { isOpen, close } = useSidebar();
</script>
