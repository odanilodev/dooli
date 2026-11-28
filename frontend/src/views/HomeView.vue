<template>
  <AdminDashboardView v-if="auth.isAdmin" />
  <InstructorHomeView v-else-if="auth.isInstructor" />
  <DashboardView v-else />
</template>

<script setup>
import { useAuthStore } from '../stores/auth';
import AdminDashboardView from './AdminDashboardView.vue';
import DashboardView from './DashboardView.vue';
import InstructorHomeView from './InstructorHomeView.vue';

const auth = useAuthStore();
</script>
