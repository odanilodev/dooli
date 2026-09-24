<template>
  <router-link :to="to" class="card card-reco" style="display:block;">
    <div class="card-thumb" :style="{ background: grad }">
      <span class="glow"></span>
      <svg class="icon" width="26" height="26" viewBox="0 0 24 24" stroke-opacity=".6"><path :d="icon" /></svg>
    </div>
    <div class="card-body">
      <span class="card-title">{{ title }}</span>
    </div>
    <span class="reco-arrow">
      <svg class="icon" width="12" height="12" viewBox="0 0 24 24" stroke-width="2.6"><path d="M9 6l6 6-6 6"/></svg>
    </span>
  </router-link>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  icon: { type: String, required: true },
  grad: { type: String, required: true },
  to: { type: [String, Object], default: '/aula' },
});
</script>
