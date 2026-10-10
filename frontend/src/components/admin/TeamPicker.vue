<template>
  <div class="team-picker">
    <div v-if="modelValue.length" class="team-picker-chips">
      <span v-for="team in modelValue" :key="team.id" class="team-chip">
        {{ team.name }}
        <button type="button" :aria-label="`Remover ${team.name}`" @click="remove(team.id)">×</button>
      </span>
    </div>
    <p v-else class="form-hint">Nenhuma turma selecionada.</p>

    <button type="button" class="btn btn-outline btn-sm" @click="open = true">
      Adicionar turma
    </button>

    <BulkPickerModal
      :open="open"
      title="Turmas com acesso"
      label="Turma"
      placeholder="Buscar turma..."
      confirm-label="Adicionar"
      search-endpoint="/admin/teams"
      :map-results="mapResult"
      @close="open = false"
      @confirm="onConfirm"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue';
import BulkPickerModal from './BulkPickerModal.vue';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);

function mapResult(item) {
  return { value: item.id, label: item.name, sub: item.status === 'active' ? 'Ativa' : 'Inativa' };
}

function onConfirm(items) {
  const merged = [...props.modelValue];

  items.forEach((item) => {
    if (!merged.some((team) => team.id === item.value)) {
      merged.push({ id: item.value, name: item.label });
    }
  });

  emit('update:modelValue', merged);
}

function remove(id) {
  emit('update:modelValue', props.modelValue.filter((team) => team.id !== id));
}
</script>

<style scoped>
.team-picker{display:flex;flex-direction:column;gap:8px;align-items:flex-start;}
.team-picker-chips{display:flex;flex-wrap:wrap;gap:6px;}
.team-chip{
  display:inline-flex;align-items:center;gap:6px;padding:4px 6px 4px 10px;border-radius:999px;
  background:var(--accent-soft);color:var(--accent);font-size:12px;font-weight:700;
}
.team-chip button{
  border:none;background:none;color:inherit;cursor:pointer;font-size:14px;line-height:1;
  padding:0 2px;font-family:inherit;
}
</style>
