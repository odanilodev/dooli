// Regras de "ativos operáveis" do DeltaStats. O ativo só passa se cumprir todas as
// regras ligadas. Os valores padrão vêm do backend (DeltaScreenerRobot::defaultSettings).

export const ASSET_RULES = [
  {
    key: 'min_trades',
    label: 'Operações',
    comparison: 'mais de',
    unit: '',
    step: 1,
    min: 0,
    passes: (row, v) => Number(row.total_trades) > v,
  },
  {
    key: 'min_win_rate',
    label: '% de acerto',
    comparison: 'mais de',
    unit: '%',
    step: 1,
    min: 0,
    max: 100,
    passes: (row, v) => Number(row.win_rate) > v,
  },
  {
    key: 'min_avg_result',
    label: 'Média por operação',
    comparison: 'mais de',
    unit: '%',
    step: 0.01,
    decimals: 2,
    passes: (row, v) => Number(row.avg_result) > v,
  },
  {
    key: 'max_worst_loss',
    label: 'Pior perda',
    comparison: 'menos de',
    unit: '%',
    step: 0.1,
    min: 0,
    max: 100,
    // worst_trade é o pior resultado (negativo numa perda). Sem perda nenhuma, passa.
    passes: (row, v) => Math.max(0, -Number(row.worst_trade)) < v,
  },
];

const fmtNumber = (v, decimals = 0) => Number(v).toLocaleString('pt-BR', {
  minimumFractionDigits: decimals,
  maximumFractionDigits: Math.max(decimals, 2),
});

/** Ex.: "Operações: mais de 10" / "Média por operação: mais de 0,20%". */
export function describeRule(rule, value) {
  return `${rule.label}: ${rule.comparison} ${fmtNumber(value, rule.decimals)}${rule.unit}`;
}

/** Regras ligadas e com valor válido. */
export function activeRules(rules) {
  return ASSET_RULES.filter((r) => rules?.[r.key]?.enabled && Number.isFinite(Number(rules[r.key].value)));
}

/** Lista das regras que o ativo não cumpre (vazia = ativo operável). */
export function failedRules(row, rules) {
  return activeRules(rules).filter((r) => !r.passes(row, Number(rules[r.key].value)));
}

export function passesRules(row, rules) {
  return failedRules(row, rules).length === 0;
}

export function cloneRules(rules) {
  return JSON.parse(JSON.stringify(rules ?? {}));
}

export function sameRules(a, b) {
  return ASSET_RULES.every((r) => Boolean(a?.[r.key]?.enabled) === Boolean(b?.[r.key]?.enabled)
    && Number(a?.[r.key]?.value) === Number(b?.[r.key]?.value));
}
