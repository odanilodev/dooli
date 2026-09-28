<template>
  <div class="cp">
    <button class="cp-trigger" @click="toggle" :class="{ open }">
      <span class="cp-name">{{ current ? current.name : 'Selecione um curso' }}</span>
      <span v-if="current" class="status-pill" :class="current.status">{{ statusLabel(current.status) }}</span>
      <svg class="cp-caret" :class="{ open }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
    </button>

    <div v-if="open" class="cp-pop" @click.stop>
      <input ref="searchInput" class="form-control cp-search" v-model="search" placeholder="Buscar curso...">

      <div class="cp-list">
        <button
          v-for="c in filtered"
          :key="c.id"
          class="cp-item"
          :class="{ active: c.id === currentId }"
          @click="choose(c.id)"
        >
          <span class="cp-item-name">{{ c.name }}</span>
          <span class="status-pill" :class="c.status">{{ statusLabel(c.status) }}</span>
        </button>
        <p v-if="!filtered.length" class="form-hint cp-empty">Nenhum curso encontrado.</p>
      </div>

      <button class="cp-new" @click="create">+ Novo curso</button>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { statusLabel } from '../../utils/adminLabels';

const props = defineProps({
  courses: { type: Array, default: () => [] },
  currentId: { type: [Number, String], default: null },
  current: { type: Object, default: null },
});

const emit = defineEmits(['select', 'create']);

const open = ref(false);
const search = ref('');
const searchInput = ref(null);

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase();

  if (!term) {
    return props.courses;
  }

  return props.courses.filter((c) => c.name.toLowerCase().includes(term));
});

function toggle() {
  open.value = !open.value;

  if (open.value) {
    search.value = '';
    nextTick(() => searchInput.value?.focus());
  }
}

function choose(id) {
  open.value = false;
  emit('select', id);
}

function create() {
  open.value = false;
  emit('create');
}

function onDocClick() {
  open.value = false;
}

watch(open, (value) => {
  if (value) {
    document.addEventListener('click', onDocClick);
  } else {
    document.removeEventListener('click', onDocClick);
  }
});

onMounted(() => open.value && document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>

<style scoped>
.cp{position:relative;}
.cp-trigger{
  display:inline-flex;align-items:center;gap:10px;padding:9px 14px;border-radius:12px;cursor:pointer;font-family:inherit;
  background:var(--bg-card);border:1px solid var(--border);color:var(--text-primary);transition:border-color .14s ease;
}
.cp-trigger:hover,.cp-trigger.open{border-color:var(--accent);}
.cp-name{font-size:14.5px;font-weight:800;max-width:340px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cp-caret{color:var(--text-muted);transition:transform .16s ease;}
.cp-caret.open{transform:rotate(180deg);}

.cp-pop{
  position:absolute;left:0;top:calc(100% + 8px);z-index:60;width:340px;padding:10px;
  background:var(--bg-card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);
  display:flex;flex-direction:column;gap:8px;
}
.cp-search{width:100%;}
.cp-list{display:flex;flex-direction:column;gap:4px;max-height:280px;overflow:auto;}
.cp-item{
  display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border-radius:10px;
  background:none;border:1px solid transparent;cursor:pointer;font-family:inherit;color:var(--text-primary);text-align:left;
}
.cp-item:hover{background:var(--bg-card-alt);}
.cp-item.active{background:var(--accent-soft);border-color:var(--accent);}
.cp-item-name{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cp-empty{padding:8px 4px;margin:0;}
.cp-new{
  text-align:left;background:none;border:none;border-top:1px dashed var(--border);padding:10px 12px 4px;
  color:var(--accent);font-weight:700;font-size:12.5px;cursor:pointer;font-family:inherit;
}
</style>
