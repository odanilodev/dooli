const ORIGIN_LABELS = {
  store: 'Loja',
  manual: 'Manual',
  unknown: 'Não identificado',
};

export function originLabel(value) {
  return ORIGIN_LABELS[value] || ORIGIN_LABELS.unknown;
}

export function originClass(value) {
  return value === 'store' || value === 'manual' ? value : 'unknown';
}
