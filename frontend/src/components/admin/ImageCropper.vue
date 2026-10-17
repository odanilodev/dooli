<template>
  <Teleport to="body">
    <div class="admin-modal-backdrop" @mousedown="onBackdropDown" @click.self="backdropDown && $emit('cancel')">
      <div class="panel admin-modal crop-modal">
        <div class="admin-panel-head">
          <h2 class="panel-title">{{ title }}</h2>
          <button type="button" class="admin-modal-close" @click="$emit('cancel')">×</button>
        </div>

        <p v-if="checkerboard" class="form-hint">
          Ajuste o recorte da assinatura. O fundo xadrez representa a área transparente.
        </p>
        <p v-else class="form-hint">
          Ajuste o enquadramento arrastando a área e usando a roda do mouse para o zoom.
        </p>

        <div class="crop-stage" :class="{ 'is-checkerboard': checkerboard }">
          <img ref="imageEl" :src="sourceUrl" alt="Imagem para recortar" @load="onImageLoad">
        </div>

        <div class="admin-modal-actions">
          <button type="button" class="btn btn-outline" :disabled="busy" @click="$emit('cancel')">Cancelar</button>
          <button v-if="allowOriginal" type="button" class="btn btn-outline" :disabled="busy" @click="confirmOriginal">Usar sem recortar</button>
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
  title: { type: String, default: 'Recortar imagem' },
  aspect: { type: Number, default: 16 / 9 },
  outputWidth: { type: Number, default: 1280 },
  outputHeight: { type: Number, default: 720 },
  format: {
    type: String,
    default: 'jpeg',
    validator: (value) => ['jpeg', 'png'].includes(value),
  },
  quality: { type: Number, default: 0.9 },
  fillColor: { type: String, default: '#ffffff' },
  checkerboard: { type: Boolean, default: false },
  allowOriginal: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'original', 'cancel']);

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
    aspectRatio: props.aspect ?? NaN,
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

function outputSize() {
  if (props.aspect) {
    return { width: props.outputWidth, height: props.outputHeight };
  }

  const data = cropper.getData(true);
  const width = Math.max(1, data.width);
  const height = Math.max(1, data.height);
  const scale = Math.min(1, props.outputWidth / width, props.outputHeight / height);

  return {
    width: Math.max(1, Math.round(width * scale)),
    height: Math.max(1, Math.round(height * scale)),
  };
}

function confirm() {
  if (!cropper || busy.value) {
    return;
  }

  busy.value = true;

  const { width, height } = outputSize();
  const canvas = cropper.getCroppedCanvas({
    width,
    height,
    imageSmoothingEnabled: true,
    imageSmoothingQuality: 'high',
    fillColor: props.fillColor || 'transparent',
  });

  if (!canvas) {
    busy.value = false;

    return;
  }

  const mime = props.format === 'png' ? 'image/png' : 'image/jpeg';
  const extension = props.format === 'png' ? 'png' : 'jpg';

  canvas.toBlob((blob) => {
    if (blob) {
      const base = (props.file?.name || 'imagem').replace(/\.[^.]+$/, '') || 'imagem';
      emit('confirm', new File([blob], `${base}.${extension}`, { type: mime }));
    }

    busy.value = false;
  }, mime, props.quality);
}

function confirmOriginal() {
  if (busy.value) {
    return;
  }

  emit('original', props.file);
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
.crop-stage.is-checkerboard{
  background-color:#e2e8f0;
  background-image:repeating-conic-gradient(#cbd5e1 0% 25%, #f8fafc 0% 50%);
  background-size:22px 22px;
}
.crop-stage img{display:block;max-width:100%;}
</style>
