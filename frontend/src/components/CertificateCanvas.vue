<template>
  <div
    ref="canvasRef"
    class="certificate-canvas"
    :class="{ 'is-filled': hasBackground, 'is-editable': editable }"
    :style="canvasStyle"
  >
    <div
      v-for="field in fields"
      v-show="isVisible(field)"
      :key="field.key"
      class="certificate-field"
      :class="[
        `field-${field.key}`,
        {
          'is-selected': editable && selected === field.key,
          'is-placeholder': editable && isPlaceholder(field.key),
          'is-draggable': editable,
        },
      ]"
      :style="fieldStyle(field)"
      :tabindex="editable ? 0 : undefined"
      :role="editable ? 'button' : undefined"
      :aria-label="editable ? `Mover ${field.label}` : undefined"
      @pointerdown="startDrag($event, field)"
      @click.stop="editable && emit('select', field.key)"
      @keydown="onKeydown($event, field)"
    >
      <template v-if="field.type === 'image'">
        <img
          v-if="certificate?.signature_url"
          :src="certificate.signature_url"
          alt="Assinatura"
          class="certificate-signature-img"
        >
        <span v-else-if="editable" class="certificate-image-placeholder">Assinatura</span>
      </template>
      <template v-else-if="field.type === 'line'">
        <span class="certificate-signature-line"></span>
      </template>
      <template v-else-if="field.type === 'modules'">
        <span v-if="moduleFormat(field) === 'inline' && moduleList.length" class="certificate-modules-inline">
          <template v-for="(module, index) in moduleList" :key="module.id">
            <span class="certificate-module-name">{{ module.name }}</span>
            <span v-if="index < moduleList.length - 1" class="certificate-module-separator">{{ inlineModuleSeparator(field) }}</span>
          </template>
        </span>
        <ol v-else-if="moduleList.length" class="certificate-modules">
          <li v-for="(module, index) in moduleList" :key="module.id">
            <span v-if="moduleMarker(field, index)" class="certificate-module-index">{{ moduleMarker(field, index) }}</span>
            <span class="certificate-module-name">{{ module.name }}</span>
          </li>
        </ol>
        <span v-else-if="editable" class="certificate-modules-placeholder">Módulos do curso</span>
      </template>
      <template v-else>
        {{ textFor(field.key) }}
      </template>

      <span
        v-for="handle in resizeHandles(field)"
        :key="handle"
        class="certificate-resize-handle"
        :class="`handle-${handle}`"
        @pointerdown.stop.prevent="startResize($event, field, handle)"
        @click.stop
      ></span>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
  LAYOUT_FIELDS,
  MODULES_PAGE_FIELDS,
  modulesSeparatorText,
  normalizeLayout,
  normalizeModulesPageLayout,
} from '../utils/certificateLayout';

const DEFAULT_BODY = 'Certificamos que {aluno} concluiu com êxito o curso {curso}, '
  + 'com carga horária de {carga_horaria} horas, em {data}.';

const props = defineProps({
  certificate: { type: Object, default: () => ({}) },
  values: { type: Object, default: () => ({}) },
  modules: { type: Array, default: () => [] },
  layout: { type: Object, default: null },
  editable: { type: Boolean, default: false },
  selected: { type: String, default: null },
  mode: { type: String, default: 'certificate' },
  backgroundUrl: { type: String, default: null },
  hiddenFields: { type: Array, default: () => [] },
});

const emit = defineEmits(['select', 'update:layout']);

const SIZE_HANDLES = ['nw', 'ne', 'sw', 'se'];
const WRAP_FIELDS = ['title', 'body', 'modules', 'heading', 'list'];
const canvasRef = ref(null);
const canvasWidth = ref(1000);

let observer = null;

const isModulesPage = computed(() => props.mode === 'modules');
const fields = computed(() => (isModulesPage.value ? MODULES_PAGE_FIELDS : LAYOUT_FIELDS));
const layout = computed(() => (
  isModulesPage.value ? normalizeModulesPageLayout(props.layout) : normalizeLayout(props.layout)
));
const scale = computed(() => canvasWidth.value / 1000);
const background = computed(() => {
  if (isModulesPage.value) {
    return props.backgroundUrl;
  }

  return props.backgroundUrl !== null && props.backgroundUrl !== undefined
    ? props.backgroundUrl
    : props.certificate?.background_url;
});
const hasBackground = computed(() => Boolean(background.value));

const moduleList = computed(() => {
  const source = Array.isArray(props.modules) ? props.modules : [];

  return source
    .map((module, index) => ({
      id: module?.id ?? `module-${index}`,
      name: typeof module?.name === 'string' ? module.name.trim() : '',
    }))
    .filter((module) => module.name !== '');
});

function moduleConfig(field) {
  return layout.value[field.key] ?? {};
}

function moduleFormat(field) {
  return moduleConfig(field).format ?? 'numbered';
}

function moduleMarker(field, index) {
  const format = moduleFormat(field);

  if (format === 'bulleted') {
    return '•';
  }

  if (format === 'plain' || format === 'inline') {
    return '';
  }

  return `${index + 1}.`;
}

function inlineModuleSeparator(field) {
  return modulesSeparatorText(moduleConfig(field).separator);
}

const canvasStyle = computed(() => ({
  '--certificate-scale': scale.value,
  ...(hasBackground.value ? { backgroundImage: `url('${background.value}')` } : {}),
}));

const renderedBody = computed(() => {
  const template = props.certificate?.body?.trim() || DEFAULT_BODY;

  const map = {
    '{aluno}': props.values?.aluno || 'Nome do Aluno',
    '{curso}': props.values?.curso || 'Nome do Curso',
    '{data}': props.values?.data || '00/00/0000',
    '{carga_horaria}': props.values?.carga_horaria !== undefined && props.values?.carga_horaria !== null
      ? String(props.values.carga_horaria)
      : '00',
    '{codigo}': props.values?.codigo || 'CÓDIGO',
  };

  return template.replace(/\{(aluno|curso|data|carga_horaria|codigo)\}/g, (token) => map[token] ?? token);
});

const hasSignatureBlock = computed(() => Boolean(
  props.certificate?.signature_url
  || props.certificate?.signatory_name
  || props.certificate?.signatory_title,
));

function textFor(key) {
  switch (key) {
    case 'eyebrow':
      return 'Certificado';
    case 'title':
      return props.certificate?.title || 'Certificado de Conclusão';
    case 'heading':
      return props.certificate?.modules_title || 'Módulos do curso';
    case 'body':
      return renderedBody.value;
    case 'signatory_name':
      return props.certificate?.signatory_name || 'Nome do assinante';
    case 'signatory_title':
      return props.certificate?.signatory_title || 'Cargo do assinante';
    case 'code':
      return props.values?.codigo ? `Código: ${props.values.codigo}` : 'Código: XXXX-XXXX-XXXX';
    default:
      return '';
  }
}

function isVisible(field) {
  if (props.hiddenFields.includes(field.key)) {
    return false;
  }

  if (props.editable) {
    return true;
  }

  switch (field.key) {
    case 'modules':
    case 'list':
      return moduleList.value.length > 0;
    case 'signature':
      return Boolean(props.certificate?.signature_url);
    case 'signature_line':
      return hasSignatureBlock.value;
    case 'signatory_name':
      return Boolean(props.certificate?.signatory_name);
    case 'signatory_title':
      return Boolean(props.certificate?.signatory_title);
    case 'code':
      return Boolean(props.values?.codigo);
    default:
      return true;
  }
}

function isPlaceholder(key) {
  switch (key) {
    case 'modules':
    case 'list':
      return moduleList.value.length === 0;
    case 'signature':
      return !props.certificate?.signature_url;
    case 'signatory_name':
      return !props.certificate?.signatory_name;
    case 'signatory_title':
      return !props.certificate?.signatory_title;
    case 'code':
      return !props.values?.codigo;
    default:
      return false;
  }
}

function transformFor(align) {
  if (align === 'left') {
    return 'translate(0, -50%)';
  }

  if (align === 'right') {
    return 'translate(-100%, -50%)';
  }

  return 'translate(-50%, -50%)';
}

function fieldStyle(field) {
  const item = layout.value[field.key];
  const style = {
    left: `${item.x}%`,
    top: `${item.y}%`,
    transform: transformFor(item.align),
    textAlign: item.align,
  };

  if (item.color) {
    style.color = item.color;
  }

  if (item.width !== null && item.width !== undefined) {
    style.width = `${item.width}%`;
  }

  if (field.type === 'text' || field.type === 'modules') {
    style.fontFamily = item.font;
    style.fontSize = `${(item.size * scale.value).toFixed(2)}px`;
    style.fontWeight = item.bold ? 800 : 500;
    style.fontStyle = item.italic ? 'italic' : 'normal';
  }

  if (field.type === 'modules') {
    style['--module-justify'] = item.align === 'center'
      ? 'center'
      : (item.align === 'right' ? 'flex-end' : 'flex-start');
    style['--module-columns'] = item.columns ?? 2;
  }

  if (field.type === 'image') {
    style['--field-size'] = `${(item.size * scale.value).toFixed(2)}px`;
  }

  return style;
}

function clamp(value) {
  return Math.min(100, Math.max(0, value));
}

function round(value) {
  return Math.round(value * 10) / 10;
}

function normalizeCurrentLayout(input) {
  return isModulesPage.value ? normalizeModulesPageLayout(input) : normalizeLayout(input);
}

function startDrag(event, field) {
  if (!props.editable) {
    return;
  }

  if (typeof event.button === 'number' && event.button !== 0) {
    return;
  }

  const rect = canvasRef.value?.getBoundingClientRect();

  if (!rect || rect.width === 0 || rect.height === 0) {
    return;
  }

  event.preventDefault();
  emit('select', field.key);

  const draft = normalizeCurrentLayout(props.layout);
  const item = draft[field.key];
  const pointerX = ((event.clientX - rect.left) / rect.width) * 100;
  const pointerY = ((event.clientY - rect.top) / rect.height) * 100;
  const offsetX = pointerX - item.x;
  const offsetY = pointerY - item.y;
  const startX = event.clientX;
  const startY = event.clientY;
  let moved = false;

  const onMove = (moveEvent) => {
    if (!moved && Math.abs(moveEvent.clientX - startX) + Math.abs(moveEvent.clientY - startY) < 3) {
      return;
    }

    moved = true;

    const next = {
      ...draft,
      [field.key]: {
        ...draft[field.key],
        x: round(clamp(((moveEvent.clientX - rect.left) / rect.width) * 100 - offsetX)),
        y: round(clamp(((moveEvent.clientY - rect.top) / rect.height) * 100 - offsetY)),
      },
    };

    draft[field.key] = next[field.key];
    emit('update:layout', next);
  };

  const onUp = () => {
    window.removeEventListener('pointermove', onMove);
    window.removeEventListener('pointerup', onUp);
    window.removeEventListener('pointercancel', onUp);
  };

  window.addEventListener('pointermove', onMove);
  window.addEventListener('pointerup', onUp);
  window.addEventListener('pointercancel', onUp);
}

function sideHandlesFor(align) {
  if (align === 'left') {
    return ['e'];
  }

  if (align === 'right') {
    return ['w'];
  }

  return ['w', 'e'];
}

function resizeHandles(field) {
  if (!props.editable || props.selected !== field.key) {
    return [];
  }

  const item = layout.value[field.key];

  if (field.type === 'line') {
    return sideHandlesFor(item.align);
  }

  if (field.type === 'image') {
    return SIZE_HANDLES;
  }

  return WRAP_FIELDS.includes(field.key)
    ? [...SIZE_HANDLES, ...sideHandlesFor(item.align)]
    : SIZE_HANDLES;
}

function sizeLimits(field) {
  return field.type === 'image' ? [10, 160] : [4, 160];
}

function startResize(event, field, handle) {
  if (!props.editable) {
    return;
  }

  if (typeof event.button === 'number' && event.button !== 0) {
    return;
  }

  const canvasRect = canvasRef.value?.getBoundingClientRect();
  const fieldEl = event.currentTarget?.parentElement;

  if (!canvasRect || !fieldEl || canvasRect.width === 0) {
    return;
  }

  event.preventDefault();
  emit('select', field.key);

  const draft = normalizeCurrentLayout(props.layout);
  const item = draft[field.key];
  const fieldRect = fieldEl.getBoundingClientRect();
  const side = handle === 'w' || handle === 'e';
  const previousCursor = document.body.style.cursor;

  document.body.style.cursor = side
    ? 'ew-resize'
    : (handle === 'nw' || handle === 'se' ? 'nwse-resize' : 'nesw-resize');

  const startX = event.clientX;
  const startY = event.clientY;
  const startWidth = item.width ?? field.widthRange?.[0] ?? 18;
  const startSize = item.size;
  const reference = Math.max(40, side ? fieldRect.width : fieldRect.height);
  const directionY = handle === 'nw' || handle === 'ne' ? -1 : 1;
  const growth = item.align === 'center' ? 2 : 1;
  const [minSize, maxSize] = sizeLimits(field);

  const onMove = (moveEvent) => {
    let value;

    if (side) {
      const delta = (moveEvent.clientX - startX) * (handle === 'w' ? -1 : 1);
      const widthPx = Math.max(1, (startWidth / 100) * canvasRect.width + delta * growth);
      const nextWidth = (widthPx / canvasRect.width) * 100;
      const [minWidth, maxWidth] = field.widthRange ?? [5, 90];

      value = round(Math.min(maxWidth, Math.max(minWidth, nextWidth)));
    } else {
      const delta = (moveEvent.clientY - startY) * directionY;
      const factor = 1 + (delta * 2) / reference;
      const nextSize = startSize * factor;

      value = round(Math.min(maxSize, Math.max(minSize, nextSize)));
    }

    const next = {
      ...draft,
      [field.key]: {
        ...draft[field.key],
        [side ? 'width' : 'size']: value,
      },
    };

    draft[field.key] = next[field.key];
    emit('update:layout', next);
  };

  const onUp = () => {
    document.body.style.cursor = previousCursor;
    window.removeEventListener('pointermove', onMove);
    window.removeEventListener('pointerup', onUp);
    window.removeEventListener('pointercancel', onUp);
  };

  window.addEventListener('pointermove', onMove);
  window.addEventListener('pointerup', onUp);
  window.addEventListener('pointercancel', onUp);
}

function onKeydown(event, field) {
  if (!props.editable) {
    return;
  }

  const step = event.shiftKey ? 2 : 0.5;

  const deltas = {
    ArrowLeft: [-step, 0],
    ArrowRight: [step, 0],
    ArrowUp: [0, -step],
    ArrowDown: [0, step],
  };

  const delta = deltas[event.key];

  if (!delta) {
    return;
  }

  event.preventDefault();
  emit('select', field.key);

  const draft = normalizeCurrentLayout(props.layout);
  const item = draft[field.key];

  draft[field.key] = {
    ...item,
    x: round(clamp(item.x + delta[0])),
    y: round(clamp(item.y + delta[1])),
  };

  emit('update:layout', draft);
}

onMounted(() => {
  if (!canvasRef.value) {
    return;
  }

  canvasWidth.value = canvasRef.value.getBoundingClientRect().width || 1000;

  observer = new ResizeObserver((entries) => {
    const width = entries[0]?.contentRect?.width ?? 0;

    if (width > 0) {
      canvasWidth.value = width;
    }
  });

  observer.observe(canvasRef.value);
});

onBeforeUnmount(() => {
  observer?.disconnect();
});
</script>

<style scoped>
.certificate-canvas{
  --certificate-scale:1;
  position:relative;width:100%;aspect-ratio:297 / 210;border-radius:14px;overflow:hidden;
  background:var(--bg-card-alt);background-size:cover;background-position:center;background-repeat:no-repeat;
  border:1px solid var(--border);color:var(--text-primary);
}
.certificate-canvas.is-filled{color:#0a1e2a;}

.certificate-field{
  position:absolute;max-width:90%;line-height:1.35;white-space:pre-line;
}
.certificate-canvas.is-editable .certificate-field{cursor:grab;touch-action:none;user-select:none;}
.certificate-canvas.is-editable .certificate-field:active{cursor:grabbing;}
.certificate-field.is-selected{outline:2px dashed var(--accent);outline-offset:6px;border-radius:4px;z-index:5;}
.certificate-field:focus-visible{outline:2px solid var(--accent);outline-offset:6px;}
.certificate-field.is-placeholder{opacity:.5;}
.certificate-field.is-placeholder:not(.field-signature){font-style:italic;}

.field-eyebrow{text-transform:uppercase;letter-spacing:.28em;white-space:nowrap;}
.certificate-canvas:not(.is-filled) .field-eyebrow{color:var(--accent);}
.certificate-canvas.is-filled .field-eyebrow{opacity:.8;}

.field-title{line-height:1.12;}
.field-body{line-height:1.55;}
.field-signatory_name,.field-signatory_title,.field-code{white-space:nowrap;}

.field-code{letter-spacing:.06em;opacity:.7;}

.certificate-signature-img{
  display:block;width:auto;height:auto;object-fit:contain;
  max-height:var(--field-size,calc(46px * var(--certificate-scale)));
  max-width:calc(var(--field-size,calc(46px * var(--certificate-scale))) * 3.913);
}
.certificate-image-placeholder{
  display:grid;place-items:center;
  width:calc(var(--field-size,calc(46px * var(--certificate-scale))) * 3.478);
  max-width:100%;
  height:var(--field-size,calc(46px * var(--certificate-scale)));
  border:1px dashed currentColor;border-radius:8px;
  font-size:calc(9px * var(--certificate-scale));letter-spacing:.08em;text-transform:uppercase;
}
.certificate-signature-line{
  display:block;width:100%;
  border-top:1px solid currentColor;opacity:.5;
}

.certificate-modules{
  margin:0;padding:0;list-style:none;
  display:grid;grid-template-columns:repeat(var(--module-columns,2),minmax(0,1fr));
  gap:calc(2px * var(--certificate-scale)) calc(18px * var(--certificate-scale));
  text-align:inherit;
}
.certificate-modules li{
  display:flex;min-width:0;gap:calc(4px * var(--certificate-scale));
  justify-content:var(--module-justify,flex-start);
  line-height:1.35;break-inside:avoid;
}
.certificate-modules-inline{
  display:block;line-height:1.35;text-align:inherit;
}
.certificate-module-index{opacity:.6;font-variant-numeric:tabular-nums;}
.certificate-module-separator{opacity:.6;white-space:pre;}
.certificate-module-name{min-width:0;overflow-wrap:anywhere;}
.certificate-modules-placeholder{
  display:inline-block;border:1px dashed currentColor;border-radius:8px;
  padding:calc(6px * var(--certificate-scale)) calc(10px * var(--certificate-scale));
  font-size:inherit;letter-spacing:.08em;text-transform:uppercase;
}

.certificate-resize-handle{
  position:absolute;width:12px;height:12px;border-radius:50%;
  background:#fff;border:2px solid var(--accent);box-shadow:0 1px 4px rgba(0,0,0,.35);
  z-index:3;touch-action:none;
}
.handle-nw{left:0;top:0;transform:translate(-50%,-50%);cursor:nwse-resize;}
.handle-ne{right:0;top:0;transform:translate(50%,-50%);cursor:nesw-resize;}
.handle-sw{left:0;bottom:0;transform:translate(-50%,50%);cursor:nesw-resize;}
.handle-se{right:0;bottom:0;transform:translate(50%,50%);cursor:nwse-resize;}
.handle-w{left:0;top:50%;transform:translate(-50%,-50%);cursor:ew-resize;}
.handle-e{right:0;top:50%;transform:translate(50%,-50%);cursor:ew-resize;}
</style>
