<template>
  <div class="lesson-content">

    <!-- LEFT COLUMN -->
    <div class="lesson-main">

      <nav class="breadcrumb">
        <svg class="icon" width="13" height="13" viewBox="0 0 24 24" stroke-width="2"><path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v9a1 1 0 0 0 1 1h3v-6h4v6h3a1 1 0 0 0 1-1v-9"/></svg>
        <router-link to="/cursos">Meus cursos</router-link>
        <span class="sep">›</span>
        <router-link to="/aula">Introdução ao Mercado Financeiro</router-link>
        <span class="sep">›</span>
        <router-link to="/aula">Módulo 2</router-link>
        <span class="sep">›</span>
        <span class="current">Aula 5</span>
      </nav>

      <div class="lesson-head">
        <span class="lesson-head-icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5l12 7-12 7V5z"/></svg>
        </span>
        <div>
          <h1 class="lesson-title">Indicadores técnicos</h1>
          <p class="lesson-desc">Entenda como utilizar os principais indicadores técnicos na análise de ativos e identifique oportunidades no mercado.</p>
        </div>
      </div>

      <!-- VIDEO -->
      <div class="video-block">
        <div class="video-stage">
          <svg class="video-illustration" width="100%" height="100%" viewBox="0 0 900 420" preserveAspectRatio="xMidYMid slice" fill="none">
            <defs>
              <radialGradient id="vidGlow" cx="35%" cy="55%" r="55%">
                <stop offset="0%" stop-color="#17c993" stop-opacity="0.25"/>
                <stop offset="100%" stop-color="#17c993" stop-opacity="0"/>
              </radialGradient>
            </defs>
            <rect width="900" height="420" fill="#0c141e"/>
            <circle cx="330" cy="240" r="260" fill="url(#vidGlow)"/>
            <g fill="#141f2c">
              <circle cx="330" cy="200" r="70"/>
              <path d="M180 420c0-95 65-150 150-150s150 55 150 150z"/>
            </g>
            <g stroke="#1c2a38" stroke-width="1">
              <line x1="0" y1="140" x2="900" y2="140"/>
              <line x1="0" y1="280" x2="900" y2="280"/>
            </g>
          </svg>
          <div class="video-overlay-text">
            <div class="l1">DISCIPLINA</div>
            <div class="l2">Gera<br>RESULTADOS</div>
          </div>
          <button class="play-btn" aria-label="Reproduzir" @click="pulsePlay">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="#ffffff"><path d="M8 5l12 7-12 7V5z"/></svg>
          </button>
        </div>
        <div class="video-controls">
          <button aria-label="Reproduzir"><svg width="15" height="15" viewBox="0 0 24 24" fill="#fff"><path d="M8 5l12 7-12 7V5z"/></svg></button>
          <span class="time">00:00 / 18:24</span>
          <span class="video-seek"><span></span></span>
          <button aria-label="Volume"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" stroke="#fff"><path d="M4 9v6h4l5 4V5L8 9H4z"/></svg></button>
          <button aria-label="Configurações"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" stroke="#fff"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></svg></button>
          <button aria-label="Tela cheia"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" stroke="#fff"><path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5"/></svg></button>
        </div>
      </div>

      <!-- TABS -->
      <div class="tabs">
        <button class="tab" :class="{ active: activeTab === 'descricao' }" @click="activeTab = 'descricao'">Descrição</button>
        <button class="tab" :class="{ active: activeTab === 'materiais' }" @click="activeTab = 'materiais'">Materiais da aula</button>
        <button class="tab" :class="{ active: activeTab === 'comentarios' }" @click="activeTab = 'comentarios'">Comentários (24)</button>
      </div>

      <div v-if="activeTab === 'descricao'" style="display:flex;flex-direction:column;gap:18px;">
        <div class="desc-row">
          <p class="desc-text">Nesta aula, você vai aprender os principais indicadores técnicos, como médias móveis, RSI e MACD, e entender como utilizá-los para identificar tendências e possíveis pontos de entrada e saída no mercado.</p>
          <button class="btn btn-primary" :disabled="completed" @click="completed = true">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            <span>{{ completed ? 'Aula concluída' : 'Marcar como concluída' }}</span>
          </button>
        </div>
        <div class="objectives">
          <span class="objectives-title">O que você vai aprender:</span>
          <div class="objective" v-for="(item, i) in objectives" :key="i">
            <span class="check-badge">
              <svg class="bg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
              <svg class="mark" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            </span>
            <span class="objective-text">{{ item }}</span>
          </div>
        </div>
      </div>

      <div v-else-if="activeTab === 'materiais'" class="tab-panel simple active">Nenhum material adicional para esta aula.</div>
      <div v-else class="tab-panel simple active">24 comentários dos alunos aparecem aqui.</div>
    </div>

    <!-- RIGHT COLUMN -->
    <aside class="lesson-side">
      <div class="progress-card">

        <div>
          <div class="progress-head">
            <span class="label">Seu progresso no curso</span>
            <span class="pct">60%</span>
          </div>
          <div class="progress-bar" style="margin-top:8px;"><span style="width:60%;"></span></div>
          <div class="progress-sub" style="margin-top:6px;">12 de 20 aulas concluídas</div>
        </div>

        <div class="divider"></div>

        <div class="module-list">

          <button
            class="module-row done"
            :aria-expanded="openModule === 'm1'"
            @click="toggleModule('m1')"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="var(--accent)"><circle cx="12" cy="12" r="10"/></svg>
            <span class="module-title">Módulo 1 — Fundamentos do Mercado Financeiro</span>
            <span class="module-count">3/3</span>
            <svg class="icon module-chevron" width="14" height="14" viewBox="0 0 24 24" stroke-width="2.2"><path d="M6 9l6 6 6-6"/></svg>
          </button>

          <button
            class="module-row"
            :aria-expanded="openModule === 'm2'"
            @click="toggleModule('m2')"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
            <span class="module-title">Módulo 2 — Análise e Indicadores</span>
            <span class="module-count">1/3</span>
            <svg class="icon module-chevron" width="14" height="14" viewBox="0 0 24 24" stroke-width="2.2"><path d="M6 9l6 6 6-6"/></svg>
          </button>
          <div class="lesson-sub-list" :class="{ open: openModule === 'm2' }">
            <div class="lesson-row">
              <span class="lesson-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg></span>
              <span class="name">Aula 4 — Leitura de gráficos</span>
              <span class="mins">20 min</span>
            </div>
            <div class="lesson-row current">
              <span class="lesson-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                <svg class="play-mark" width="8" height="8" viewBox="0 0 24 24" fill="currentColor"><path d="M9 6l7 6-7 6V6z"/></svg>
              </span>
              <span class="name">Aula 5 — Indicadores técnicos</span>
              <span class="mins">18 min</span>
            </div>
            <div class="lesson-row locked">
              <span class="lesson-icon locked-icon"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="1.8"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg></span>
              <span class="name">Aula 6 — Análise avançada</span>
              <span class="mins">25 min</span>
            </div>
          </div>

          <button
            class="module-row todo"
            :aria-expanded="openModule === 'm3'"
            @click="toggleModule('m3')"
          >
            <svg class="icon" width="18" height="18" viewBox="0 0 24 24" stroke="var(--text-muted)" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
            <span class="module-title">Módulo 3 — Estratégias de Investimento</span>
            <span class="module-count">0/4</span>
            <svg class="icon module-chevron" width="14" height="14" viewBox="0 0 24 24" stroke-width="2.2"><path d="M6 9l6 6 6-6"/></svg>
          </button>

          <button
            class="module-row todo"
            :aria-expanded="openModule === 'm4'"
            @click="toggleModule('m4')"
          >
            <svg class="icon" width="18" height="18" viewBox="0 0 24 24" stroke="var(--text-muted)" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
            <span class="module-title">Módulo 4 — Prática no dia a dia</span>
            <span class="module-count">0/3</span>
            <svg class="icon module-chevron" width="14" height="14" viewBox="0 0 24 24" stroke-width="2.2"><path d="M6 9l6 6 6-6"/></svg>
          </button>

        </div>
      </div>

      <div class="lesson-nav">
        <router-link to="/aula" class="btn btn-outline">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
          Aula anterior
        </router-link>
        <router-link to="/aula" class="btn btn-primary">
          Próxima aula
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </router-link>
      </div>
    </aside>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const activeTab = ref('descricao');
const completed = ref(false);
const openModule = ref('m2');

function toggleModule(id) {
  openModule.value = openModule.value === id ? null : id;
}

function pulsePlay(e) {
  const btn = e.currentTarget;
  btn.style.transform = 'scale(0.9)';
  setTimeout(() => { btn.style.transform = ''; }, 120);
}

const objectives = [
  'O que são indicadores técnicos',
  'Como interpretar os principais indicadores (Médias Móveis, RSI e MACD)',
  'Exemplos práticos de aplicação',
  'Dicas para evitar erros comuns',
  'Como combinar indicadores para melhores resultados',
];
</script>
