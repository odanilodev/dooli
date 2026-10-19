<template>
  <img v-if="dataUrl" :src="dataUrl" :alt="alt" class="qr-code-image">
</template>

<script setup>
import { ref, watch } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
  value: { type: String, default: '' },
  alt: { type: String, default: 'QR code' },
});

const dataUrl = ref('');

watch(
  () => props.value,
  async (value) => {
    if (!value) {
      dataUrl.value = '';
      return;
    }

    try {
      dataUrl.value = await QRCode.toDataURL(value, {
        errorCorrectionLevel: 'M',
        margin: 1,
        width: 512,
      });
    } catch (e) {
      dataUrl.value = '';
    }
  },
  { immediate: true },
);
</script>

<style scoped>
.qr-code-image{display:block;max-width:100%;height:auto;background:#fff;}
</style>
