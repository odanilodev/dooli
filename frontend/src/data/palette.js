import { ICON } from './icons';

// Paleta reaproveitada nos cards (cursos, continuar assistindo, loja...).
export const PALETTE = [
  { icon: ICON.trendUp, grad: 'linear-gradient(150deg,#0f3d2e,#22c55e)' },
  { icon: ICON.bolt, grad: 'linear-gradient(150deg,#101826,#1e293b)' },
  { icon: ICON.wave, grad: 'linear-gradient(150deg,#122236,#134e4a)' },
  { icon: ICON.shield, grad: 'linear-gradient(150deg,#3a2f12,#7c5a15)' },
  { icon: ICON.bulb, grad: 'linear-gradient(150deg,#0d1420,#1b2536)' },
  { icon: ICON.options, grad: 'linear-gradient(150deg,#101826,#1e293b)' },
  { icon: ICON.magnifier, grad: 'linear-gradient(150deg,#0f2a3a,#134e4a)' },
  { icon: ICON.candles, grad: 'linear-gradient(150deg,#1e1b3a,#3b1f4a)' },
  { icon: ICON.target, grad: 'linear-gradient(150deg,#1a2e1a,#0f3d2e)' },
];

export function paletteAt(index) {
  return PALETTE[index % PALETTE.length];
}
