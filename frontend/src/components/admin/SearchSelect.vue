<template>
  <div ref="rootRef" class="search-select" :class="{ 'is-open': open, 'is-disabled': disabled }">
    <div class="search-select-control">
      <input
        ref="inputRef"
        v-bind="$attrs"
        class="form-control search-select-input"
        type="text"
        :value="displayText"
        :placeholder="placeholder"
        :disabled="disabled"
        autocomplete="off"
        spellcheck="false"
        role="combobox"
        :aria-expanded="open"
        @focus="onFocus"
        @input="onInput"
        @keydown.down.prevent="move(1)"
        @keydown.up.prevent="move(-1)"
        @keydown.enter.prevent="onEnter"
        @keydown.esc.prevent="close"
        @keydown.tab="close"
      >
      <button
        v-if="allowClear && hasValue"
        type="button"
        class="search-select-clear"
        aria-label="Limpar"
        @mousedown.prevent="clear"
      >×</button>
    </div>

    <ul v-if="open" class="search-select-menu" role="listbox">
      <li v-if="loading && !rows.length" class="search-select-empty">Carregando...</li>
      <li v-else-if="!rows.length" class="search-select-empty">{{ emptyText }}</li>
      <li
        v-for="(row, i) in rows"
        :key="row.clear ? '__clear__' : row.value"
        class="search-select-option"
        :class="{ 'is-active': i === highlighted, 'is-selected': isSelected(row) }"
        role="option"
        :aria-selected="isSelected(row)"
        @mousedown.prevent="select(row)"
        @mousemove="highlighted = i"
      >
        <span class="search-select-option-label">{{ row.label }}</span>
        <span v-if="row.sub" class="search-select-option-sub">{{ row.sub }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Selecione...' },
  emptyText: { type: String, default: 'Nenhum resultado.' },
  allowClear: { type: Boolean, default: false },
  clearLabel: { type: String, default: 'Nenhum' },
  disabled: { type: Boolean, default: false },
  remote: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'search']);

defineOptions({ inheritAttrs: false });

const rootRef = ref(null);
const inputRef = ref(null);
const open = ref(false);
const query = ref('');
const highlighted = ref(0);

let debounceTimer = null;

function scheduleSearch(value) {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => emit('search', value), 250);
}

function normalize(value) {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();
}

const selectedOption = computed(
  () => props.options.find((o) => String(o.value) === String(props.modelValue)) ?? null,
);

const hasValue = computed(() => props.modelValue !== '' && props.modelValue !== null && props.modelValue !== undefined);

const displayText = computed(() => (open.value ? query.value : (selectedOption.value?.label ?? '')));

const filteredOptions = computed(() => {
  if (props.remote) {
    return props.options;
  }

  const q = normalize(query.value);

  if (!q) {
    return props.options;
  }

  return props.options.filter((o) => normalize(`${o.label} ${o.sub ?? ''}`).includes(q));
});

const rows = computed(() => {
  if (!props.allowClear || query.value) {
    return filteredOptions.value;
  }

  return [{ value: '', label: props.clearLabel, clear: true }, ...filteredOptions.value];
});

function isSelected(row) {
  if (row.clear) {
    return !hasValue.value;
  }

  return String(row.value) === String(props.modelValue);
}

function onFocus() {
  open.value = true;
  query.value = '';
  highlighted.value = rows.value.findIndex((row) => isSelected(row));
  if (highlighted.value < 0) {
    highlighted.value = 0;
  }

  if (props.remote) {
    emit('search', '');
  }
}

function onInput(event) {
  open.value = true;
  query.value = event.target.value;
  highlighted.value = 0;

  if (props.remote) {
    scheduleSearch(query.value);
  }
}

function move(delta) {
  if (!open.value) {
    onFocus();
    return;
  }

  if (!rows.value.length) {
    return;
  }

  highlighted.value = (highlighted.value + delta + rows.value.length) % rows.value.length;
}

function onEnter() {
  if (!open.value) {
    return;
  }

  select(rows.value[highlighted.value]);
}

function select(row) {
  if (!row) {
    return;
  }

  emit('update:modelValue', row.clear ? '' : row.value);
  close();
}

function clear() {
  emit('update:modelValue', '');
  close();
}

function close() {
  open.value = false;
  query.value = '';
}

function onDocumentMouseDown(event) {
  if (open.value && rootRef.value && !rootRef.value.contains(event.target)) {
    close();
  }
}

watch(() => props.disabled, (value) => {
  if (value) {
    close();
  }
});

watch(() => props.options, () => {
  if (open.value) {
    highlighted.value = 0;
  }
});

onMounted(() => document.addEventListener('mousedown', onDocumentMouseDown));
onBeforeUnmount(() => {
  clearTimeout(debounceTimer);
  document.removeEventListener('mousedown', onDocumentMouseDown);
});
</script>

<style scoped>
.search-select{position:relative;}
.search-select-control{position:relative;}
.search-select-input{padding-right:38px;cursor:pointer;}
.search-select.is-open .search-select-input{cursor:text;}
.search-select-clear{
  position:absolute;top:50%;right:8px;transform:translateY(-50%);
  width:24px;height:24px;border:none;border-radius:8px;background:var(--bg-card-alt);
  color:var(--text-muted);font-size:16px;line-height:1;cursor:pointer;display:grid;place-content:center;
  font-family:inherit;
}
.search-select-clear:hover{color:var(--text-primary);}
.search-select-menu{
  position:absolute;z-index:30;top:calc(100% + 6px);left:0;right:0;max-height:260px;overflow:auto;
  margin:0;padding:6px;list-style:none;background:var(--bg-card);border:1px solid var(--border);
  border-radius:12px;box-shadow:var(--shadow-sm);
}
.search-select-option{
  display:flex;flex-direction:column;gap:2px;padding:9px 12px;border-radius:9px;cursor:pointer;
}
.search-select-option.is-active{background:var(--bg-card-alt);}
.search-select-option.is-selected .search-select-option-label{color:var(--accent);font-weight:700;}
.search-select-option-label{
  font-size:13px;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.search-select-option-sub{font-size:11.5px;color:var(--text-muted);}
.search-select-empty{padding:10px 12px;font-size:12.5px;color:var(--text-muted);}
</style>
