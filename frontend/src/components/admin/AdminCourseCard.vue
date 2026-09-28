<template>
  <article
    class="card acard"
    role="link"
    tabindex="0"
    @click="go"
    @keydown.enter="go"
  >
    <div class="acard-thumb" :style="{ background: gradient }">
      <span class="glow" style="top:-30px;right:-20px;left:auto;"></span>
      <img v-if="course.image" :src="course.image" class="acard-img" :alt="course.name">
      <svg v-else class="acard-icon" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20v3H6.5A2.5 2.5 0 0 1 4 20.5z"/></svg>
    </div>

    <div class="acard-body">
      <span class="acard-name">{{ course.name }}</span>
      <span class="acard-sub">{{ moduleCount }} módulo(s)</span>
      <span class="acard-foot">
        <span class="status-pill" :class="course.status">{{ statusLabel(course.status) }}</span>
        <button type="button" class="acard-edit" @click.stop="emit('edit', course)">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
          Editar
        </button>
      </span>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { statusLabel } from '../../utils/adminLabels';

const props = defineProps({ course: { type: Object, required: true } });
const emit = defineEmits(['edit']);

const router = useRouter();

const GRADIENTS = [
  'linear-gradient(135deg,#22e0ab,#0fa87a)',
  'linear-gradient(135deg,#38bdf8,#2563eb)',
  'linear-gradient(135deg,#a78bfa,#7c3aed)',
  'linear-gradient(135deg,#fbbf24,#f97316)',
  'linear-gradient(135deg,#f472b6,#db2777)',
];

const gradient = computed(() => GRADIENTS[Number(props.course.id) % GRADIENTS.length]);
const moduleCount = computed(() => (props.course.modules ?? []).length);

function go() {
  router.push({ name: 'admin-conteudo-curso', params: { courseId: props.course.id } });
}
</script>

<style scoped>
.acard{display:flex;flex-direction:column;cursor:pointer;}
.acard:focus-visible{outline:2px solid var(--accent);outline-offset:2px;}
.acard-thumb{position:relative;height:112px;display:flex;align-items:center;justify-content:center;overflow:hidden;}
.acard-thumb .glow{position:absolute;width:140px;height:140px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.14),transparent 70%);}
.acard-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
.acard-icon{position:relative;color:#fff;opacity:.85;}
.acard-body{padding:13px 15px;display:flex;flex-direction:column;gap:5px;}
.acard-name{font-size:13.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.acard-sub{font-size:11.5px;color:var(--text-muted);font-weight:600;}
.acard-foot{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:5px;}
.acard-edit{
  display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:9px;cursor:pointer;
  background:transparent;border:1.5px solid var(--border);color:var(--text-secondary);
  font-family:inherit;font-size:11.5px;font-weight:700;transition:border-color .14s ease,color .14s ease;
}
.acard-edit:hover{border-color:var(--accent);color:var(--text-primary);}
</style>
