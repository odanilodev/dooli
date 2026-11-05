<template>
  <main class="content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Robôs</h1>
        <p class="page-subtitle">Ferramentas de análise incluídas no seu acesso à plataforma.</p>
      </div>
    </div>

    <div class="robot-grid" v-if="robots.length">
      <router-link
        v-for="robot in robots"
        :key="robot.id"
        :to="{ name: 'robo', params: { slug: robot.slug } }"
        class="robot-card"
      >
        <span class="robot-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/></svg>
        </span>
        <strong class="robot-name">{{ robot.name }}</strong>
        <span class="robot-desc">{{ robot.description }}</span>
        <span class="robot-markets">{{ marketLabel(robot.market) }}</span>
      </router-link>
    </div>

    <div class="empty-state" v-else-if="!loading">
      <strong>Nenhum robô disponível ainda</strong>
      <span>Assim que novos robôs forem liberados, eles aparecem aqui.</span>
    </div>
  </main>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api/http';

const robots = ref([]);
const loading = ref(true);

const LABELS = { b3: 'B3', us: 'EUA', crypto: 'Cripto' };

function marketLabel(market) {
  return (market || '').split(',').filter(Boolean).map((m) => LABELS[m] || m).join(' · ');
}

onMounted(async () => {
  try {
    const { data } = await api.get('/me/robots');
    robots.value = data.data;
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.robot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px; }
.robot-card {
  display: flex; flex-direction: column; gap: 8px; padding: 20px;
  background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px;
  color: inherit; text-decoration: none; transition: border-color .15s, transform .15s;
}
.robot-card:hover { border-color: var(--accent); transform: translateY(-3px); }
.robot-icon { display: inline-flex; width: 42px; height: 42px; align-items: center; justify-content: center; border-radius: 11px; background: var(--accent-grad); color: var(--accent-ink); }
.robot-name { font-size: 15px; }
.robot-desc { font-size: 12.5px; color: var(--text-muted); line-height: 1.5; }
.robot-markets { font-size: 11px; font-weight: 700; color: var(--accent); letter-spacing: .04em; }
</style>
