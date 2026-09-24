<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Minhas turmas</h1>
        <p class="page-subtitle">Turmas com encontros ao vivo e instrutor dedicado.</p>
      </div>
    </div>

    <div class="turma-list">
      <router-link v-for="turma in turmas" :key="turma.id" to="/aula" class="turma-card" style="text-decoration:none;color:inherit;">
        <div class="turma-thumb" :style="{ background: turma.grad }">
          <span class="glow"></span>
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-opacity=".55" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="position:relative;"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17.5" cy="9" r="2.4"/><path d="M15.6 14.3c2.5.5 4.4 2.7 4.4 5.7"/></svg>
        </div>

        <div class="turma-body">
          <div class="turma-top">
            <div>
              <span class="turma-course">{{ turma.course }}</span>
              <div class="turma-name">{{ turma.name }}</div>
            </div>
            <span v-if="turma.live" class="turma-live-badge"><span class="dot"></span>AO VIVO EM BREVE</span>
          </div>

          <div class="turma-meta">
            <span class="turma-instructor">
              <span class="turma-avatar">{{ turma.initials }}</span>
              {{ turma.instructor }}
            </span>
            <span class="turma-session">
              <svg class="icon" width="14" height="14" viewBox="0 0 24 24" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
              {{ turma.nextSession }}
            </span>
            <span>
              <svg class="icon" width="14" height="14" viewBox="0 0 24 24" stroke-width="1.8" style="display:inline;vertical-align:-2px;margin-right:4px;"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg>
              {{ turma.members }} participantes
            </span>
          </div>

          <div class="turma-actions">
            <div class="turma-progress">
              <span class="turma-progress-label"><span>Progresso</span><span>{{ turma.pct }}%</span></span>
              <span class="progress-bar"><span :style="{ width: turma.pct + '%' }"></span></span>
            </div>
            <span class="btn btn-primary">Entrar na turma</span>
          </div>
        </div>
      </router-link>
    </div>

  </main>
</template>

<script setup>
import { turmas } from '../data/turmas';
</script>
