<template>
  <router-link :to="to" class="card" style="display:block;">
    <div class="card-thumb" :style="{ background: grad }">
      <img v-if="image" class="card-img" :src="image" :alt="title">
      <template v-else>
        <span class="glow"></span>
        <svg class="icon" width="30" height="30" viewBox="0 0 24 24"><path :d="icon" /></svg>
      </template>
      <span class="card-dur">{{ dur }}</span>
      <span class="card-progress"><span :style="{ width: pct + '%' }"></span></span>
    </div>
    <div class="card-body">
      <span class="card-title">{{ title }}</span>
      <span class="card-sub">{{ sub }}</span>
    </div>
  </router-link>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  sub: { type: String, default: '' },
  dur: { type: String, default: '' },
  pct: { type: Number, default: 0 },
  image: { type: String, default: '' },
  icon: { type: String, required: true },
  grad: { type: String, required: true },
  to: { type: [String, Object], default: '/cursos' },
});
</script>
