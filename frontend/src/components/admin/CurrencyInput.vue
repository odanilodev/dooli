<template>
  <input
    :id="id"
    class="form-control"
    type="text"
    inputmode="numeric"
    autocomplete="off"
    :value="display"
    :placeholder="placeholder"
    :required="required"
    @input="onInput"
  >
</template>

<script setup>
import { computed } from 'vue';
import { formatCurrency } from '../../utils/format';

const props = defineProps({
  modelValue: { type: [Number, String], default: null },
  id: { type: String, default: undefined },
  required: { type: Boolean, default: false },
  placeholder: { type: String, default: 'R$ 0,00' },
});

const emit = defineEmits(['update:modelValue']);

const display = computed(() => {
  if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') {
    return '';
  }

  const value = Number(props.modelValue);

  return Number.isFinite(value) ? formatCurrency(value) : '';
});

function onInput(event) {
  const digits = event.target.value.replace(/\D/g, '');

  if (!digits) {
    event.target.value = '';
    emit('update:modelValue', null);

    return;
  }

  const value = Number(digits) / 100;

  event.target.value = formatCurrency(value);
  emit('update:modelValue', value);
}
</script>
