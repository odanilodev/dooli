<template>
  <img v-if="branding.hasLogo" :src="branding.logoUrl" class="brand-logo" alt="Logo">
  <template v-else>
    <span class="brand-mark">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l9 18H3z"/></svg>
    </span>
    <span class="brand-text">
      <span class="brand-name" :style="nameStyle">DELTA CLASS</span>
      <span class="brand-tag" :style="tagStyle">Conhecimento que gera resultados</span>
    </span>
  </template>
</template>

<script setup>
import { computed } from 'vue';
import { useBrandingStore } from '../stores/branding';

const props = defineProps({
  variant: {
    type: String,
    default: 'default',
  },
});

const branding = useBrandingStore();

const onMedia = computed(() => props.variant === 'auth');
const nameStyle = computed(() => (onMedia.value ? { color: '#fff' } : null));
const tagStyle = computed(() => (onMedia.value ? { color: '#c7d2de' } : null));
</script>
