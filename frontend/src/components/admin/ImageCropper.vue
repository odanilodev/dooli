<template>
  <Teleport to="body">
    <div class="admin-modal-backdrop" @mousedown="onBackdropDown" @click.self="backdropDown && $emit('cancel')">
      <div class="panel admin-modal crop-modal">
        <div class="admin-panel-head">
          <h2 class="panel-title">Recortar imagem</h2>
          <button type="button" class="admin-modal-close" @click="$emit('cancel')">×</button>
        </div>

        <p class="form-hint">Ajuste o enquadramento arrastando a área e usando a roda do mouse para o zoom.</p>

        <div class="crop-stage">
          <img ref="imageEl" :src="sourceUrl" alt="Imagem para recortar" @load="onImageLoad">
        </div>

        <div class="admin-modal-actions">
          <button type="button" class="btn btn-outline" :disabled="busy" @click="$emit('cancel')">Cancelar</button>
          <button type="button" class="btn btn-primary" :disabled="busy" @click="confirm">Recortar</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const props = defineProps({
  file: { type: [File, Object], required: true },
  aspect: { type: Number, default: 16 / 9 },
  outputWidth: { type: Number, default: 1280 },
  outputHeight: { type: Number, default: 720 },
});

const emit = defineEmits(['confirm', 'cancel']);

const imageEl = ref(null);
const sourceUrl = ref(URL.createObjectURL(props.file));
const busy = ref(false);
const backdropDown = ref(false);

let cropper = null;

function onBackdropDown(event) {
  backdropDown.value = event.target === event.currentTarget;
}

function onImageLoad() {
  if (cropper || !imageEl.value) {
    return;
  }

  cropper = new Cropper(imageEl.value, {
    aspectRatio: props.aspect,
    viewMode: 1,
    autoCropArea: 1,
    background: false,
    responsive: true,
    guides: true,
    dragMode: 'move',
    cropBoxMovable: true,
    cropBoxResizable: true,
    toggleDragModeOnDblclick: false,
  });
}

function confirm() {
  if (!cropper || busy.value) {
    return;
  }

  busy.value = true;

  const canvas = cropper.getCroppedCanvas({
    width: props.outputWidth,
    height: props.outputHeight,
    imageSmoothingEnabled: true,
    imageSmoothingQuality: 'high',
    fillColor: '#ffffff',
  });

  if (!canvas) {
    busy.value = false;

    return;
  }

  canvas.toBlob((blob) => {
    if (blob) {
      const base = (props.file?.name || 'imagem').replace(/\.[^.]+$/, '') || 'imagem';
      emit('confirm', new File([blob], `${base}.jpg`, { type: 'image/jpeg' }));
    }

    busy.value = false;
  }, 'image/jpeg', 0.9);
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    emit('cancel');
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown));

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown);

  if (cropper) {
    cropper.destroy();
    cropper = null;
  }

  URL.revokeObjectURL(sourceUrl.value);
});
</script>

<style scoped>
.crop-modal{max-width:760px;}
.crop-stage{margin:10px 0 4px;background:#0b1220;border:1px solid var(--border);border-radius:12px;overflow:hidden;}
.crop-stage img{display:block;max-width:100%;}
</style>
