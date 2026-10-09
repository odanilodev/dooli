export function normalizeHex(hex) {
  if (!hex) {
    return null;
  }

  let value = String(hex).trim().replace('#', '');

  if (/^[0-9a-fA-F]{3}$/.test(value)) {
    value = value
      .split('')
      .map((char) => char + char)
      .join('');
  }

  if (!/^[0-9a-fA-F]{6}$/.test(value)) {
    return null;
  }

  return `#${value.toLowerCase()}`;
}

function toRgb(hex) {
  const value = normalizeHex(hex);

  if (!value) {
    return null;
  }

  return {
    r: parseInt(value.slice(1, 3), 16),
    g: parseInt(value.slice(3, 5), 16),
    b: parseInt(value.slice(5, 7), 16),
  };
}

function clamp(value) {
  return Math.max(0, Math.min(255, Math.round(value)));
}

function toHex({ r, g, b }) {
  return `#${[r, g, b].map((part) => clamp(part).toString(16).padStart(2, '0')).join('')}`;
}

export function lighten(hex, amount) {
  const rgb = toRgb(hex);

  if (!rgb) {
    return null;
  }

  return toHex({
    r: rgb.r + 255 * amount,
    g: rgb.g + 255 * amount,
    b: rgb.b + 255 * amount,
  });
}

export function darken(hex, amount) {
  const rgb = toRgb(hex);

  if (!rgb) {
    return null;
  }

  return toHex({
    r: rgb.r * (1 - amount),
    g: rgb.g * (1 - amount),
    b: rgb.b * (1 - amount),
  });
}

export function rgba(hex, alpha) {
  const rgb = toRgb(hex);

  if (!rgb) {
    return null;
  }

  return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha})`;
}

export function contrastInk(hex) {
  const rgb = toRgb(hex);

  if (!rgb) {
    return '#ffffff';
  }

  const luminance = (0.2126 * rgb.r + 0.7152 * rgb.g + 0.0722 * rgb.b) / 255;

  return luminance > 0.6 ? '#06281d' : '#ffffff';
}
