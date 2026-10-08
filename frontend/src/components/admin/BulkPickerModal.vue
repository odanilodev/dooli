<template>
  <div v-if="open" class="admin-modal-backdrop" @mousedown="onBackdropDown" @click.self="backdropDown && close()">
    <div class="panel admin-modal">
      <div class="admin-panel-head">
        <h2 class="panel-title">{{ title }}</h2>
        <button class="admin-modal-close" type="button" @click="close">×</button>
      </div>

      <div class="form-group">
        <label>{{ label }}</label>
        <SearchSelect
          v-model="picked"
          remote
          :options="options"
          :loading="loading"
          :placeholder="placeholder"
          :empty-text="emptyText"
          @search="onSearch"
        />
        <p class="form-hint">{{ hint }}</p>
      </div>

      <div v-if="selected.length" class="bulk-chips">
        <span v-for="item in selected" :key="item.value" class="bulk-chip">
          {{ item.label }}
          <button type="button" aria-label="Remover" @click="remove(item.value)">×</button>
        </span>
      </div>
      <p v-else class="form-hint">Nenhum item selecionado.</p>

      <div class="admin-modal-actions">
        <button type="button" class="btn btn-outline" @click="close">Cancelar</button>
        <button type="button" class="btn btn-primary" :disabled="!selected.length" @click="confirm">
          {{ confirmLabel }} ({{ selected.length }})
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { api } from '../../api/http';
import SearchSelect from './SearchSelect.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: 'Selecionar' },
  label: { type: String, default: 'Item' },
  placeholder: { type: String, default: 'Buscar...' },
  emptyText: { type: String, default: 'Nenhum resultado.' },
  hint: { type: String, default: 'Busque e selecione um ou mais itens.' },
  confirmLabel: { type: String, default: 'Confirmar' },
  searchEndpoint: { type: String, required: true },
  params: { type: Object, default: () => ({}) },
  mapResults: { type: Function, required: true },
});

const emit = defineEmits(['close', 'confirm']);

const options = ref([]);
const selected = ref([]);
const picked = ref('');
const loading = ref(false);
const backdropDown = ref(false);

watch(() => props.open, (value) => {
  if (value) {
    selected.value = [];
    picked.value = '';
    onSearch('');
  }
});

watch(picked, (value) => {
  if (value === '' || value === null || value === undefined) {
    return;
  }

  const option = options.value.find((o) => String(o.value) === String(value));

  if (option && !selected.value.some((s) => String(s.value) === String(value))) {
    selected.value = [...selected.value, option];
  }

  picked.value = '';
});

async function onSearch(term) {
  loading.value = true;

  try {
    const { data } = await api.get(props.searchEndpoint, {
      params: { ...props.params, search: term || undefined, per_page: 20 },
    });

    options.value = (data.data ?? []).map((item) => props.mapResults(item));
  } catch {
    options.value = [];
  } finally {
    loading.value = false;
  }
}

function remove(value) {
  selected.value = selected.value.filter((item) => String(item.value) !== String(value));
}

function onBackdropDown(event) {
  backdropDown.value = event.target === event.currentTarget;
}

function close() {
  emit('close');
}

function confirm() {
  if (selected.value.length) {
    emit('confirm', [...selected.value]);
  }
}
</script>

<style scoped>
.bulk-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 4px;
}
.bulk-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 6px 4px 10px;
  border-radius: 999px;
  background: var(--accent-soft);
  color: var(--accent);
  font-size: 12px;
  font-weight: 700;
}
.bulk-chip button {
  border: none;
  background: none;
  color: inherit;
  cursor: pointer;
  font-size: 14px;
  line-height: 1;
  padding: 0 2px;
  font-family: inherit;
}
</style>
