<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Aulas ao vivo</h1>
        <p class="page-subtitle">Transmissões liberadas para as suas turmas.</p>
      </div>
    </div>

    <p v-if="store.loading && !store.loaded" class="page-subtitle">Carregando...</p>

    <template v-else>
      <div v-if="store.items.length === 0" class="empty-state">
        <strong>Nenhuma aula ao vivo por aqui</strong>
        <span>Quando sua turma tiver uma transmissão, ela aparecerá nesta página.</span>
      </div>

      <template v-else>
        <section v-if="store.upcoming.length" class="section">
          <div class="section-head"><h2 class="section-title">Próximas</h2></div>
          <div class="live-grid">
            <article v-for="item in store.upcoming" :key="item.id" class="live-card">
              <div class="live-cover" :style="coverStyle(item)">
                <span v-if="item.isLive" class="live-badge">AO VIVO</span>
                <span v-else class="live-soon-badge">EM BREVE</span>
              </div>
              <div class="live-body">
                <h3 class="live-title">{{ item.title }}</h3>
                <p v-if="item.description" class="live-desc">{{ item.description }}</p>
                <span class="live-meta">{{ formatDateTime(item.startsAt) }}<template v-if="item.duration"> · {{ item.duration }} min</template></span>
              </div>
              <a class="btn btn-primary live-action" :href="item.link" target="_blank" rel="noopener">
                {{ item.isLive ? 'Entrar na sala' : 'Acessar link' }}
              </a>
            </article>
          </div>
        </section>

        <section v-if="store.past.length" class="section">
          <div class="section-head"><h2 class="section-title">Anteriores</h2></div>
          <div class="live-grid">
            <article v-for="item in store.past" :key="item.id" class="live-card is-past">
              <div class="live-cover" :style="coverStyle(item)"></div>
              <div class="live-body">
                <h3 class="live-title">{{ item.title }}</h3>
                <p v-if="item.description" class="live-desc">{{ item.description }}</p>
                <span class="live-meta">{{ formatDateTime(item.startsAt) }}</span>
              </div>
              <a class="btn btn-outline live-action" :href="item.link" target="_blank" rel="noopener">Acessar link</a>
            </article>
          </div>
        </section>
      </template>
    </template>

  </main>
</template>

<script setup>
import { onMounted } from 'vue';
import { formatDateTime } from '../utils/format';
import { useLiveClassesStore } from '../stores/liveClasses';

const store = useLiveClassesStore();

function coverStyle(item) {
  if (item.image) {
    return {
      backgroundImage: `linear-gradient(180deg, rgba(8,14,22,.15), rgba(8,14,22,.75)), url('${item.image}')`,
    };
  }

  return { background: 'linear-gradient(135deg, var(--accent), var(--accent-2))' };
}

onMounted(() => store.fetch());
</script>

<style scoped>
.live-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px;}
.live-card{
  display:flex;flex-direction:column;background:var(--bg-card);border:1px solid var(--border);
  border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-sm);
}
.live-card.is-past{opacity:.72;}
.live-cover{position:relative;height:132px;background-size:cover;background-position:center;}
.live-soon-badge{position:absolute;top:12px;right:12px;z-index:2;background:#f59e0b;color:#1a1205;font-size:11px;font-weight:800;letter-spacing:.06em;padding:4px 9px;border-radius:4px;}
.live-body{display:flex;flex-direction:column;gap:6px;padding:16px 16px 8px;flex:1;}
.live-title{font-size:16px;font-weight:800;}
.live-desc{font-size:13px;color:var(--text-secondary);}
.live-meta{font-size:12.5px;color:var(--text-muted);font-weight:600;}
.live-action{margin:12px 16px 16px;text-align:center;}
</style>
