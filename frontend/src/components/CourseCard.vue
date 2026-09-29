<template>
  <router-link :to="to" class="card card-course" style="display:block;">
    <div class="card-thumb" :style="{ background: grad }">
      <img v-if="image" class="card-img" :src="image" :alt="title">
      <span v-else class="glow" style="top:-30px;right:-20px;left:auto;"></span>
      <svg class="icon thumb-icon-tl" width="20" height="20" viewBox="0 0 24 24"><path :d="icon" /></svg>
      <span class="badge-label">{{ badge }}</span>
      <span class="pct-badge">{{ pct }}%</span>
    </div>
    <div class="card-body">
      <span class="card-title">{{ title }}</span>
      <span class="card-sub">{{ count }} aulas</span>
      <span class="progress-bar"><span :style="{ width: pct + '%' }"></span></span>
    </div>
  </router-link>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  count: { type: Number, default: 0 },
  pct: { type: Number, default: 0 },
  badge: { type: String, default: '' },
  icon: { type: String, required: true },
  grad: { type: String, required: true },
  image: { type: String, default: '' },
  to: { type: [String, Object], default: '/cursos' },
});
</script>
