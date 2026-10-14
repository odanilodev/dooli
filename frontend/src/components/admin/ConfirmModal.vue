<template>
  <div class="admin-modal-backdrop" @mousedown="onBackdropDown" @click.self="backdropDown && !busy && emit('cancel')">
    <div class="panel admin-modal">
      <div class="admin-panel-head">
        <h2 class="panel-title">{{ title }}</h2>
        <button class="admin-modal-close" :disabled="busy" @click="emit('cancel')">×</button>
      </div>

      <p v-if="message" class="page-subtitle">{{ message }}</p>

      <slot />

      <div class="admin-modal-actions">
        <button class="btn btn-outline" :disabled="busy" @click="emit('cancel')">Cancelar</button>
        <button
          class="btn"
          :class="variant === 'danger' ? 'btn-danger' : 'btn-primary'"
          :disabled="busy"
          @click="emit('confirm')"
        >{{ busy ? 'Processando...' : confirmLabel }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
  title: { type: String, required: true },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Confirmar' },
  variant: { type: String, default: 'danger' },
  busy: { type: Boolean, default: false },
});

const emit = defineEmits(['cancel', 'confirm']);

const backdropDown = ref(false);

function onBackdropDown(e) {
  backdropDown.value = e.target === e.currentTarget;
}
</script>
