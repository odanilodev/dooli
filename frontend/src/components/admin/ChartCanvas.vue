<template>
  <div class="chart-wrap">
    <canvas ref="canvas"></canvas>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
  type: { type: String, required: true },
  data: { type: Object, required: true },
  options: { type: Object, default: () => ({}) },
});

const canvas = ref(null);
let instance = null;

function render() {
  if (!canvas.value) {
    return;
  }

  instance?.destroy();

  instance = new Chart(canvas.value, {
    type: props.type,
    data: props.data,
    options: props.options,
  });
}

onMounted(render);
watch(() => [props.type, props.data], render, { deep: true });
onBeforeUnmount(() => instance?.destroy());
</script>

<style scoped>
.chart-wrap{position:relative;height:260px;width:100%;}
</style>
