<template>
  <div ref="root" class="team-picker">
    <div
      class="team-select-trigger"
      :class="{ open }"
      role="button"
      tabindex="0"
      @click="toggleOpen"
      @keydown.enter.prevent="toggleOpen"
      @keydown.space.prevent="toggleOpen"
    >
      <div v-if="modelValue.length" class="team-picker-chips">
        <span v-for="team in modelValue" :key="team.id" class="team-chip">
          {{ team.name }}
          <button type="button" :aria-label="`Remover ${team.name}`" @click.stop="remove(team.id)">×</button>
        </span>
      </div>
      <span v-else class="team-select-placeholder">Selecione as turmas...</span>
      <span class="team-select-arrow" aria-hidden="true">▾</span>
    </div>

    <div v-if="open" class="team-select-menu">
      <input
        ref="searchInput"
        v-model="search"
        class="form-control team-select-search"
        type="search"
        placeholder="Buscar turma..."
        @input="scheduleSearch"
      >

      <div class="team-select-options">
        <p v-if="loading" class="form-hint team-select-empty">Carregando...</p>
        <p v-else-if="!teams.length" class="form-hint team-select-empty">Nenhuma turma encontrada.</p>

        <label v-for="team in teams" :key="team.id" class="team-option">
          <input type="checkbox" :checked="isSelected(team.id)" @change="toggleTeam(team)">
          <span class="team-option-name">{{ team.name }}</span>
          <span v-if="team.status" class="team-option-status" :class="team.status">
            {{ team.status === 'active' ? 'Ativa' : 'Inativa' }}
          </span>
        </label>
      </div>
    </div>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { api } from '../../api/http';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const root = ref(null);
const searchInput = ref(null);
const open = ref(false);
const search = ref('');
const teams = ref([]);
const loading = ref(false);

let searchTimer = null;
let requestToken = 0;

async function loadTeams(term = '') {
  const token = ++requestToken;
  loading.value = true;

  try {
    const { data } = await api.get('/admin/teams', {
      params: { search: term || undefined, per_page: 100 },
    });

    if (token === requestToken) {
      teams.value = data.data ?? [];
    }
  } catch {
    if (token === requestToken) {
      teams.value = [];
    }
  } finally {
    if (token === requestToken) {
      loading.value = false;
    }
  }
}

function scheduleSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadTeams(search.value.trim()), 300);
}

async function toggleOpen() {
  open.value = !open.value;

  if (!open.value) {
    return;
  }

  await loadTeams(search.value.trim());
  await nextTick();
  searchInput.value?.focus();
}

function isSelected(id) {
  return props.modelValue.some((team) => team.id === id);
}

function toggleTeam(team) {
  if (isSelected(team.id)) {
    remove(team.id);

    return;
  }

  emit('update:modelValue', [...props.modelValue, { id: team.id, name: team.name }]);
}

function remove(id) {
  emit('update:modelValue', props.modelValue.filter((team) => team.id !== id));
}

function onDocumentMouseDown(event) {
  if (open.value && root.value && !root.value.contains(event.target)) {
    open.value = false;
  }
}

onMounted(() => document.addEventListener('mousedown', onDocumentMouseDown));

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocumentMouseDown);
  clearTimeout(searchTimer);
});
</script>

<style scoped>
.team-picker{position:relative;width:100%;}
.team-select-trigger{
  display:flex;align-items:center;gap:8px;width:100%;min-height:46px;box-sizing:border-box;
  border:1px solid var(--border);background:var(--bg-input);border-radius:10px;padding:7px 12px;
  cursor:pointer;transition:border-color .15s ease;outline:none;
}
.team-select-trigger.open,.team-select-trigger:focus-visible{border-color:var(--accent);}
.team-picker-chips{display:flex;flex-wrap:wrap;gap:6px;flex:1;min-width:0;}
.team-chip{
  display:inline-flex;align-items:center;gap:6px;padding:4px 6px 4px 10px;border-radius:999px;
  background:var(--accent-soft);color:var(--accent);font-size:12px;font-weight:700;
}
.team-chip button{
  border:none;background:none;color:inherit;cursor:pointer;font-size:14px;line-height:1;
  padding:0 2px;font-family:inherit;
}
.team-select-placeholder{flex:1;color:var(--text-muted);font-size:13px;}
.team-select-arrow{flex-shrink:0;color:var(--text-muted);font-size:12px;}
.team-select-menu{
  position:absolute;z-index:40;top:calc(100% + 6px);left:0;right:0;
  background:var(--bg-card);border:1px solid var(--border);border-radius:12px;
  box-shadow:0 16px 36px rgba(0,0,0,.18);padding:8px;
}
.team-select-search{margin-bottom:6px;}
.team-select-options{display:flex;flex-direction:column;gap:2px;max-height:260px;overflow-y:auto;}
.team-option{display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:8px;cursor:pointer;font-size:13px;}
.team-option:hover{background:var(--bg-card-alt);}
.team-option input{width:16px;height:16px;accent-color:var(--accent);cursor:pointer;}
.team-option-name{flex:1;min-width:0;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.team-option-status{flex-shrink:0;color:var(--text-muted);font-size:11px;font-weight:700;}
.team-option-status.active{color:var(--accent);}
.team-select-empty{margin:8px 10px;}
</style>
