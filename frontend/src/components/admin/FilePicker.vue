<template>
  <label
    class="file-picker"
    :class="{ 'is-dragging': dragging, 'is-disabled': disabled }"
    @dragover.prevent="dragging = true"
    @dragleave.prevent="dragging = false"
    @drop.prevent="onDrop"
  >
    <input type="file" :accept="accept" class="file-picker__input" :disabled="disabled" @change="onChange">
    <span class="file-picker__icon">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 16V4m0 0L8 8m4-4 4 4"/>
        <path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
      </svg>
    </span>
    <span class="file-picker__body">
      <span class="file-picker__title">{{ fileName || title }}</span>
      <span class="file-picker__hint">{{ hint }}</span>
    </span>
  </label>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  accept: { type: String, default: '' },
  title: { type: String, default: 'Escolher arquivo' },
  hint: { type: String, default: '' },
  fileName: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['change']);

const dragging = ref(false);

function pick(file) {
  if (file && !props.disabled) {
    emit('change', file);
  }
}

function onChange(event) {
  pick(event.target.files?.[0] ?? null);
}

function onDrop(event) {
  dragging.value = false;
  pick(event.dataTransfer?.files?.[0] ?? null);
}
</script>

<style scoped>
.file-picker{
  position:relative;display:flex;align-items:center;gap:12px;cursor:pointer;
  border:1px dashed var(--border);border-radius:10px;background:var(--bg-input);padding:10px 12px;
  transition:border-color .15s ease,background .15s ease;
}
.file-picker:hover,.file-picker:focus-within,.file-picker.is-dragging{
  border-color:var(--accent);background:var(--accent-soft);
}
.file-picker.is-disabled{opacity:.6;pointer-events:none;}
.file-picker__input{position:absolute;width:1px;height:1px;opacity:0;}
.file-picker__icon{
  flex-shrink:0;display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:9px;
  background:var(--accent-soft);color:var(--accent);
}
.file-picker__body{display:flex;flex-direction:column;gap:1px;min-width:0;}
.file-picker__title{font-size:13px;font-weight:700;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.file-picker__hint{font-size:11.5px;color:var(--text-muted);}
</style>
