<template>
  <article
    class="card acard"
    :class="{ 'menu-open': menuOpen }"
    role="link"
    tabindex="0"
    @click="go"
    @keydown.enter.self="go"
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

        <span class="acard-menu" @click.stop>
          <button
            type="button"
            class="acard-kebab"
            :disabled="busy"
            :aria-expanded="menuOpen"
            aria-haspopup="menu"
            title="Ações"
            @click="toggleMenu"
            @keydown.enter.stop
            @keydown.esc="menuOpen = false"
          >⋯</button>

          <div v-if="menuOpen" class="acard-dropdown" role="menu" @keydown.esc="menuOpen = false">
            <button type="button" role="menuitem" :disabled="busy" @click="run('open')">Abrir curso</button>
            <button type="button" role="menuitem" :disabled="busy" @click="run('edit')">Editar</button>
            <button type="button" role="menuitem" :disabled="busy" @click="run(published ? 'unpublish' : 'publish')">
              {{ published ? 'Despublicar' : 'Publicar' }}
            </button>
            <div class="acard-menu-sep"></div>
            <button type="button" role="menuitem" class="danger" :disabled="busy" @click="run('delete')">Excluir</button>
          </div>
        </span>
      </span>
    </div>
  </article>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { statusLabel } from '../../utils/adminLabels';

const props = defineProps({
  course: { type: Object, required: true },
  busy: { type: Boolean, default: false },
});
const emit = defineEmits(['open', 'edit', 'publish', 'unpublish', 'delete']);

const router = useRouter();
const menuOpen = ref(false);

const GRADIENTS = [
  'linear-gradient(135deg,#22e0ab,#0fa87a)',
  'linear-gradient(135deg,#38bdf8,#2563eb)',
  'linear-gradient(135deg,#a78bfa,#7c3aed)',
  'linear-gradient(135deg,#fbbf24,#f97316)',
  'linear-gradient(135deg,#f472b6,#db2777)',
];

const gradient = computed(() => GRADIENTS[Number(props.course.id) % GRADIENTS.length]);
const moduleCount = computed(() => (props.course.modules ?? []).length);
const published = computed(() => props.course.status === 'active');

function go() {
  router.push({ name: 'admin-conteudo-curso', params: { courseId: props.course.id } });
}

function toggleMenu() {
  menuOpen.value = !menuOpen.value;
}

function closeMenu() {
  menuOpen.value = false;
}

function run(action) {
  menuOpen.value = false;
  emit(action, props.course);
}

onMounted(() => document.addEventListener('click', closeMenu));
onBeforeUnmount(() => document.removeEventListener('click', closeMenu));
</script>

<style scoped>
.acard{display:flex;flex-direction:column;cursor:pointer;position:relative;overflow:visible;}
.acard.menu-open{z-index:5;}
.acard:focus-visible{outline:2px solid var(--accent);outline-offset:2px;}
.acard-thumb{position:relative;height:112px;display:flex;align-items:center;justify-content:center;overflow:hidden;border-radius:var(--radius-md) var(--radius-md) 0 0;}
.acard-thumb .glow{position:absolute;width:140px;height:140px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.14),transparent 70%);}
.acard-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
.acard-icon{position:relative;color:#fff;opacity:.85;}
.acard-body{padding:13px 15px;display:flex;flex-direction:column;gap:5px;}
.acard-name{font-size:13.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.acard-sub{font-size:11.5px;color:var(--text-muted);font-weight:600;}
.acard-foot{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:5px;}

.acard-menu{position:relative;display:inline-flex;}
.acard-kebab{
  display:inline-flex;align-items:center;justify-content:center;width:30px;height:26px;padding:0;border-radius:9px;cursor:pointer;
  background:transparent;border:1.5px solid var(--border);color:var(--text-secondary);
  font-family:inherit;font-size:16px;line-height:1;font-weight:800;transition:border-color .14s ease,color .14s ease,background .14s ease;
}
.acard-kebab:hover,.acard.menu-open .acard-kebab{border-color:var(--accent);color:var(--text-primary);}
.acard-kebab:disabled{opacity:.5;cursor:wait;}
.acard-dropdown{
  position:absolute;right:0;top:calc(100% + 6px);z-index:30;min-width:172px;padding:6px;
  background:var(--bg-card);border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);
  display:flex;flex-direction:column;gap:2px;
}
.acard-dropdown button{
  text-align:left;background:none;border:none;color:var(--text-secondary);font-family:inherit;font-size:12.5px;font-weight:600;
  padding:8px 10px;border-radius:8px;cursor:pointer;white-space:nowrap;
}
.acard-dropdown button:hover{background:var(--bg-card-alt);color:var(--text-primary);}
.acard-dropdown button:disabled{opacity:.5;cursor:wait;}
.acard-dropdown button.danger{color:#ef4444;}
.acard-dropdown button.danger:hover{background:rgba(239,68,68,.12);}
.acard-menu-sep{height:1px;background:var(--border);margin:4px 2px;}
</style>
