<template>
  <template v-for="item in items" :key="item.key">
    <router-link :to="item.to" @click="emit('navigate')">
      <NavIcon :name="item.icon" :size="15" />
      {{ item.label }}
    </router-link>
  </template>
  <a href="#" @click.prevent="emit('logout')">
    <NavIcon name="logout" :size="15" />
    Sair
  </a>
</template>

<script setup>
import { useAccountMenu } from '../composables/useAccountMenu';
import NavIcon from './NavIcon.vue';

const props = defineProps({
  variant: { type: String, default: 'app' },
});

const emit = defineEmits(['navigate', 'logout']);
const { items } = useAccountMenu(props.variant);
</script>
