export const FONT_OPTIONS = [
  { value: '"Manrope", system-ui, sans-serif', label: 'Manrope' },
  { value: '"Playfair Display", Georgia, serif', label: 'Playfair Display' },
  { value: '"Lora", Georgia, serif', label: 'Lora' },
  { value: '"Great Vibes", cursive', label: 'Great Vibes (assinatura)' },
  { value: 'Georgia, serif', label: 'Georgia' },
  { value: '"Times New Roman", Times, serif', label: 'Times New Roman' },
  { value: 'Arial, Helvetica, sans-serif', label: 'Arial' },
  { value: 'Verdana, Geneva, sans-serif', label: 'Verdana' },
  { value: '"Courier New", Courier, monospace', label: 'Courier New' },
];

const DEFAULT_FONT = FONT_OPTIONS[0].value;
const ALIGNMENTS = ['left', 'center', 'right'];

export const LAYOUT_FIELDS = [
  {
    key: 'eyebrow',
    label: 'Selo "Certificado"',
    type: 'text',
    defaults: { x: 50, y: 36, size: 11, align: 'center', bold: true, italic: false, color: null, font: DEFAULT_FONT },
  },
  {
    key: 'title',
    label: 'Título',
    type: 'text',
    widthRange: [20, 90],
    defaults: { x: 50, y: 43, width: 80, size: 32, align: 'center', bold: true, italic: false, color: null, font: DEFAULT_FONT },
  },
  {
    key: 'body',
    label: 'Texto',
    type: 'text',
    widthRange: [20, 90],
    defaults: { x: 50, y: 52, width: 80, size: 15, align: 'center', bold: false, italic: false, color: null, font: DEFAULT_FONT },
  },
  {
    key: 'signature',
    label: 'Imagem da assinatura',
    type: 'image',
    defaults: { x: 50, y: 67, size: 46, align: 'center', bold: false, italic: false, color: null, font: DEFAULT_FONT },
  },
  {
    key: 'signature_line',
    label: 'Linha da assinatura',
    type: 'line',
    widthRange: [5, 90],
    defaults: { x: 50, y: 74, width: 18, size: 0, align: 'center', bold: false, italic: false, color: null, font: DEFAULT_FONT },
  },
  {
    key: 'signatory_name',
    label: 'Nome do assinante',
    type: 'text',
    defaults: { x: 50, y: 77, size: 12.5, align: 'center', bold: true, italic: false, color: null, font: DEFAULT_FONT },
  },
  {
    key: 'signatory_title',
    label: 'Cargo do assinante',
    type: 'text',
    defaults: { x: 50, y: 80, size: 11, align: 'center', bold: false, italic: false, color: null, font: DEFAULT_FONT },
  },
  {
    key: 'code',
    label: 'Código',
    type: 'text',
    defaults: { x: 96, y: 94, size: 10, align: 'right', bold: false, italic: false, color: null, font: DEFAULT_FONT },
  },
];

export function layoutField(key) {
  return LAYOUT_FIELDS.find((field) => field.key === key) ?? null;
}

function clamp(value, min, max) {
  return Math.min(max, Math.max(min, value));
}

function toNumber(value, fallback) {
  if (value === null || value === undefined || value === '') {
    return fallback;
  }

  const number = Number(value);

  return Number.isFinite(number) ? number : fallback;
}

function isHex(value) {
  return typeof value === 'string' && /^#[0-9a-fA-F]{6}$/.test(value);
}

export function normalizeLayout(input) {
  const source = input && typeof input === 'object' && !Array.isArray(input) ? input : {};
  const result = {};

  for (const field of LAYOUT_FIELDS) {
    const saved = source[field.key] && typeof source[field.key] === 'object' ? source[field.key] : {};
    const base = field.defaults;
    const width = field.widthRange
      ? Math.round(clamp(toNumber(saved.width, base.width), field.widthRange[0], field.widthRange[1]) * 10) / 10
      : null;

    result[field.key] = {
      x: clamp(toNumber(saved.x, base.x), 0, 100),
      y: clamp(toNumber(saved.y, base.y), 0, 100),
      width,
      color: Object.prototype.hasOwnProperty.call(saved, 'color')
        ? (isHex(saved.color) ? saved.color.toLowerCase() : null)
        : base.color,
      font: typeof saved.font === 'string' && saved.font.trim() !== '' ? saved.font.trim() : base.font,
      size: clamp(toNumber(saved.size, base.size), 4, 160),
      align: ALIGNMENTS.includes(saved.align) ? saved.align : base.align,
      bold: typeof saved.bold === 'boolean' ? saved.bold : base.bold,
      italic: typeof saved.italic === 'boolean' ? saved.italic : base.italic,
    };
  }

  return result;
}

export function cloneLayout(input) {
  return normalizeLayout(input);
}
