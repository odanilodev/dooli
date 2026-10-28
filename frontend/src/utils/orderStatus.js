const ORDER_LABELS = {
  pending: 'Aguardando pagamento',
  paid: 'Pago',
  canceled: 'Cancelado',
  refunded: 'Estornado',
};

const PAYMENT_LABELS = {
  pending: 'Pendente',
  paid: 'Aprovado',
  failed: 'Recusado',
  refunded: 'Estornado',
};

const REFUND_LABELS = {
  pending: 'Pendente',
  refunded: 'Estornado',
  failed: 'Falhou',
};

const METHOD_LABELS = {
  pix: 'PIX',
  credit_card: 'Cartão de crédito',
  debit_card: 'Cartão de débito',
  boleto: 'Boleto',
};

export function orderStatusLabel(status) {
  return ORDER_LABELS[status] || status || '—';
}

export function orderStatusClass(status) {
  return ['paid', 'canceled', 'refunded'].includes(status) ? status : 'pending';
}

export function paymentStatusLabel(status) {
  return PAYMENT_LABELS[status] || status || '—';
}

export function paymentStatusClass(status) {
  return status === 'paid' ? 'paid' : status || 'pending';
}

export function refundStatusLabel(status) {
  return REFUND_LABELS[status] || status || '—';
}

export function paymentMethodLabel(method) {
  return METHOD_LABELS[method] || method || 'Pagamento';
}

export function orderTypeLabel(type) {
  return type === 'subscription' ? 'Assinatura' : 'Compra única';
}

export function orderEventTone(type) {
  if (!type) return 'neutral';
  if (type.includes('canceled') || type.includes('failed') || type.includes('blocked')) return 'danger';
  if (type.includes('refund')) return 'warning';
  if (type.includes('approved') || type.includes('granted')) return 'success';
  if (type.includes('started') || type.includes('created')) return 'neutral';
  return 'neutral';
}
