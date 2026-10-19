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

export const MODULES_PLACEMENT_OPTIONS = [
  { value: 'front', label: 'Na frente do certificado' },
  { value: 'extra', label: 'Folha extra (página separada)' },
  { value: 'back', label: 'Verso do certificado (página separada)' },
  { value: 'none', label: 'Sem grade' },
];

export const MODULES_PLACEMENTS = MODULES_PLACEMENT_OPTIONS.map((option) => option.value);

export const MODULES_FORMAT_OPTIONS = [
  { value: 'numbered', label: 'Numerada (1., 2.)' },
  { value: 'bulleted', label: 'Com marcadores (•)' },
  { value: 'plain', label: 'Sem marcador' },
  { value: 'inline', label: 'Em linha' },
];

export const MODULES_FORMAT_VALUES = MODULES_FORMAT_OPTIONS.map((option) => option.value);

export const MODULES_COLUMN_OPTIONS = [1, 2, 3, 4].map((value) => ({
  value,
  label: value === 1 ? '1 coluna' : `${value} colunas`,
}));

export const MODULES_SEPARATOR_OPTIONS = [
  { value: 'bullet', label: 'Bullet (•)', text: '\u00A0•\u00A0' },
  { value: 'comma', label: 'Vírgula (,)', text: ',\u00A0' },
  { value: 'pipe', label: 'Barra (|)', text: '\u00A0|\u00A0' },
  { value: 'dash', label: 'Traço (–)', text: '\u00A0–\u00A0' },
];

export const MODULES_SEPARATOR_VALUES = MODULES_SEPARATOR_OPTIONS.map((option) => option.value);

export function modulesSeparatorText(value) {
  return MODULES_SEPARATOR_OPTIONS.find((option) => option.value === value)?.text ?? '\u00A0•\u00A0';
}

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
    key: 'modules',
    label: 'Módulos do curso',
    type: 'modules',
    widthRange: [20, 90],
    defaults: {
      x: 50, y: 60, width: 80, size: 9, align: 'center', bold: false, italic: false,
      color: null, font: DEFAULT_FONT, format: 'numbered', columns: 2, separator: 'bullet',
    },
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

export const MODULES_PAGE_FIELDS = [
  {
    key: 'heading',
    label: 'Título da grade',
    type: 'text',
    widthRange: [20, 90],
    defaults: { x: 50, y: 22, width: 80, size: 28, align: 'center', bold: true, italic: false, color: null, font: DEFAULT_FONT },
  },
  {
    key: 'list',
    label: 'Lista de módulos',
    type: 'modules',
    widthRange: [20, 90],
    defaults: {
      x: 50, y: 34, width: 86, size: 10, align: 'center', bold: false, italic: false,
      color: null, font: DEFAULT_FONT, format: 'numbered', columns: 2, separator: 'bullet',
    },
  },
];

export function layoutField(key) {
  return LAYOUT_FIELDS.find((field) => field.key === key) ?? null;
}

export function modulesPageField(key) {
  return MODULES_PAGE_FIELDS.find((field) => field.key === key) ?? null;
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

export function normalizeFields(fields, input) {
  const source = input && typeof input === 'object' && !Array.isArray(input) ? input : {};
  const result = {};

  for (const field of fields) {
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

    if (field.type === 'modules') {
      result[field.key].format = MODULES_FORMAT_VALUES.includes(saved.format)
        ? saved.format
        : (base.format ?? 'numbered');
      result[field.key].columns = Math.round(clamp(toNumber(saved.columns, base.columns ?? 2), 1, 4));
      result[field.key].separator = MODULES_SEPARATOR_VALUES.includes(saved.separator)
        ? saved.separator
        : (base.separator ?? 'bullet');
    }
  }

  return result;
}

export function normalizeLayout(input) {
  return normalizeFields(LAYOUT_FIELDS, input);
}

export function normalizeModulesPageLayout(input) {
  return normalizeFields(MODULES_PAGE_FIELDS, input);
}

export function cloneLayout(input) {
  return normalizeLayout(input);
}
