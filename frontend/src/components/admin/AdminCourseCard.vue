<template>
  <router-link :to="{ name: 'admin-conteudo-curso', params: { courseId: course.id } }" class="card acard">
    <div class="acard-thumb" :style="{ background: gradient }">
      <span class="glow" style="top:-30px;right:-20px;left:auto;"></span>
      <img v-if="course.image" :src="course.image" class="acard-img" :alt="course.name">
      <svg v-else class="acard-icon" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20v3H6.5A2.5 2.5 0 0 1 4 20.5z"/></svg>
    </div>

    <div class="acard-body">
      <span class="acard-name">{{ course.name }}</span>
      <span class="acard-meta">
        <span class="status-pill" :class="course.status">{{ statusLabel(course.status) }}</span>
        <span class="acard-sub">{{ moduleCount }} módulo(s)</span>
      </span>
    </div>
  </router-link>
</template>

<script setup>
import { computed } from 'vue';
import { statusLabel } from '../../utils/adminLabels';

const props = defineProps({ course: { type: Object, required: true } });

const GRADIENTS = [
  'linear-gradient(135deg,#22e0ab,#0fa87a)',
  'linear-gradient(135deg,#38bdf8,#2563eb)',
  'linear-gradient(135deg,#a78bfa,#7c3aed)',
  'linear-gradient(135deg,#fbbf24,#f97316)',
  'linear-gradient(135deg,#f472b6,#db2777)',
];

const gradient = computed(() => GRADIENTS[Number(props.course.id) % GRADIENTS.length]);
const moduleCount = computed(() => (props.course.modules ?? []).length);
</script>

<style scoped>
.acard{display:flex;flex-direction:column;}
.acard-thumb{position:relative;height:112px;display:flex;align-items:center;justify-content:center;overflow:hidden;}
.acard-thumb .glow{position:absolute;width:140px;height:140px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.14),transparent 70%);}
.acard-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
.acard-icon{position:relative;color:#fff;opacity:.85;}
.acard-body{padding:13px 15px;display:flex;flex-direction:column;gap:9px;}
.acard-name{font-size:13.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.acard-meta{display:flex;align-items:center;justify-content:space-between;gap:8px;}
.acard-sub{font-size:11.5px;color:var(--text-muted);font-weight:600;flex-shrink:0;}
</style>
