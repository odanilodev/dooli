import { ICON } from './icons';

// Lista única de cursos — fonte de dados para o Dashboard e o Catálogo.
// status: 'andamento' | 'concluido' | 'nao-iniciado'
export const courses = [
  { id: 'trader-zero-avancado', title: 'Trader do Zero ao Avançado', badge: 'TOURO', count: 32, pct: 60, status: 'andamento', icon: ICON.trendUp, grad: 'linear-gradient(150deg,#0f3d2e,#22c55e)' },
  { id: 'day-trade-pratica', title: 'Day Trade na Prática', badge: 'DAY TRADE', count: 28, pct: 35, status: 'andamento', icon: ICON.bolt, grad: 'linear-gradient(150deg,#101826,#1e293b)' },
  { id: 'swing-trade', title: 'Swing Trade - Estratégias', badge: 'SWING', count: 24, pct: 20, status: 'andamento', icon: ICON.wave, grad: 'linear-gradient(150deg,#122236,#134e4a)' },
  { id: 'gestao-risco', title: 'Gestão de Risco', badge: 'RISCO', count: 18, pct: 10, status: 'andamento', icon: ICON.shield, grad: 'linear-gradient(150deg,#3a2f12,#7c5a15)' },
  { id: 'psicologia-trader', title: 'Psicologia do Trader', badge: 'MINDSET', count: 16, pct: 0, status: 'nao-iniciado', icon: ICON.bulb, grad: 'linear-gradient(150deg,#0d1420,#1b2536)' },
  { id: 'opcoes-iniciantes', title: 'Opções para Iniciantes', badge: 'OPÇÕES', count: 14, pct: 0, status: 'nao-iniciado', icon: ICON.options, grad: 'linear-gradient(150deg,#101826,#1e293b)' },
  { id: 'introducao-mercado', title: 'Introdução ao Mercado Financeiro', badge: 'INTRO', count: 12, pct: 100, status: 'concluido', icon: ICON.magnifier, grad: 'linear-gradient(150deg,#0f2a3a,#134e4a)' },
  { id: 'leitura-graficos', title: 'Leitura de Gráficos', badge: 'GRÁFICOS', count: 10, pct: 100, status: 'concluido', icon: ICON.candles, grad: 'linear-gradient(150deg,#1e1b3a,#3b1f4a)' },
  { id: 'renda-fixa', title: 'Fundamentos de Renda Fixa', badge: 'RENDA FIXA', count: 9, pct: 100, status: 'concluido', icon: ICON.target, grad: 'linear-gradient(150deg,#1a2e1a,#0f3d2e)' },
];

// "Continuar assistindo" — nível de aula, não de curso.
export const continueWatching = [
  { id: 'analise-tecnica', title: 'Análise Técnica do Zero', sub: 'Aula 3 – Suporte e Resistência', dur: '24:15', pct: 55, icon: ICON.candles, grad: 'linear-gradient(150deg,#0f2a3a,#134e4a)' },
  { id: 'price-action', title: 'Price Action na Prática', sub: 'Aula 5 – Padrões de candle', dur: '18:40', pct: 40, icon: ICON.magnifier, grad: 'linear-gradient(150deg,#1e1b3a,#3b1f4a)' },
  { id: 'gestao-risco-aula', title: 'Gestão de Risco', sub: 'Aula 2 – Tamanho de posição', dur: '32:10', pct: 25, icon: ICON.shield, grad: 'linear-gradient(150deg,#1a2e1a,#0f3d2e)' },
  { id: 'psicologia-aula', title: 'Psicologia do Trader', sub: 'Aula 1 – Mentalidade vencedora', dur: '21:35', pct: 10, icon: ICON.bulb, grad: 'linear-gradient(150deg,#111827,#1f2937)' },
  { id: 'estrategias-op', title: 'Estratégias Operacionais', sub: 'Aula 4 – Entradas e saídas', dur: '28:50', pct: 60, icon: ICON.target, grad: 'linear-gradient(150deg,#102a2a,#0e3b34)' },
];

export const recommended = [
  { id: 'leitura-graficos-av', title: 'Leitura de Gráficos Avançada', icon: ICON.magnifier, grad: 'linear-gradient(150deg,#102a2a,#0e3b34)' },
  { id: 'opcoes-iniciantes-2', title: 'Opções para Iniciantes', icon: ICON.options, grad: 'linear-gradient(150deg,#101826,#1e293b)' },
  { id: 'nasdaq-bolsa', title: 'Nasdaq & Bolsa Americana', icon: ICON.flag, grad: 'linear-gradient(150deg,#1a1f2e,#2a2f3e)' },
  { id: 'estrategias-indicadores', title: 'Estratégias com Indicadores', icon: ICON.wave, grad: 'linear-gradient(150deg,#0f2a3a,#134e4a)' },
  { id: 'plano-trading', title: 'Plano de Trading do seu Jeito', icon: ICON.notebook, grad: 'linear-gradient(150deg,#1e1b3a,#3b1f4a)' },
];
