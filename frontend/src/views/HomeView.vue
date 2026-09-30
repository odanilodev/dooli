<template>
  <AdminDashboardView v-if="auth.isAdmin" />
  <DashboardView v-else />
</template>

<script setup>
import { useAuthStore } from '../stores/auth';
import AdminDashboardView from './AdminDashboardView.vue';
import DashboardView from './DashboardView.vue';

const auth = useAuthStore();
</script>
