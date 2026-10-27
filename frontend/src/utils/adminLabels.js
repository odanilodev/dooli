// Rótulos amigáveis (PT) para a área administrativa de cursos.

export const TYPE_LABELS = {
  course: 'Curso',
  module: 'Módulo',
  topic: 'Tópico',
  lesson: 'Aula',
};

export function typeLabel(type) {
  return TYPE_LABELS[type] ?? '';
}

export const STATUS_LABELS = {
  active: 'Publicado',
  inactive: 'Rascunho',
};

export function statusLabel(status) {
  return STATUS_LABELS[status] ?? status ?? '';
}

export const TEAM_STATUS_LABELS = {
  active: 'Ativa',
  inactive: 'Inativa',
};

export function teamStatusLabel(status) {
  return TEAM_STATUS_LABELS[status] ?? status ?? '';
}

export const PLAYER_LABELS = {
  vdocipher: 'VdoCipher',
  vimeo: 'Vimeo',
  fastpix: 'FastPix',
  external: 'Link externo',
};

export function playerLabel(player) {
  return PLAYER_LABELS[player] ?? player ?? '—';
}
