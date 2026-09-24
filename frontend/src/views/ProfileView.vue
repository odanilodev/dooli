<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Meu perfil</h1>
        <p class="page-subtitle">Gerencie suas informações pessoais e acompanhe sua jornada.</p>
      </div>
    </div>

    <div class="profile-header">
      <div class="profile-avatar-wrap">
        <span class="profile-avatar">LF</span>
        <button class="profile-avatar-edit" aria-label="Alterar foto" type="button">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
        </button>
      </div>
      <div class="profile-info">
        <span class="profile-name">{{ form.name }}</span>
        <span class="profile-role">{{ form.email }}</span>
        <span class="profile-plan">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 6.6L21 10l-5 4.3L17.4 21 12 17.6 6.6 21 8 14.3 3 10l6.6-1.4z"/></svg>
          Plano Pro
        </span>
      </div>
    </div>

    <div class="stat-grid">
      <div class="stat-card">
        <span class="stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></span>
        <span class="stat-value">3</span>
        <span class="stat-label">Cursos concluídos</span>
      </div>
      <div class="stat-card">
        <span class="stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></span>
        <span class="stat-value">47h</span>
        <span class="stat-label">Horas assistidas</span>
      </div>
      <div class="stat-card">
        <span class="stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M8.5 12.5 7 21l5-3 5 3-1.5-8.5"/></svg></span>
        <span class="stat-value">3</span>
        <span class="stat-label">Certificados</span>
      </div>
      <div class="stat-card">
        <span class="stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4 14h5l-1 8 9-12h-5z"/></svg></span>
        <span class="stat-value">12 dias</span>
        <span class="stat-label">Sequência de estudos</span>
      </div>
    </div>

    <form class="panel" @submit.prevent="save">
      <div>
        <h2 class="panel-title">Informações pessoais</h2>
        <p class="panel-desc">Essas informações aparecem no seu perfil e certificados.</p>
      </div>

      <div class="form-success" :class="{ show: showSuccess }">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
        Alterações salvas com sucesso.
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="p-name">Nome completo</label>
          <input class="form-control" type="text" id="p-name" v-model="form.name" required>
        </div>
        <div class="form-group">
          <label for="p-phone">Telefone</label>
          <input class="form-control" type="tel" id="p-phone" v-model="form.phone">
        </div>
      </div>
      <div class="form-group">
        <label for="p-email">E-mail</label>
        <input class="form-control" type="email" id="p-email" v-model="form.email" required>
      </div>
      <div class="form-group">
        <label for="p-bio">Sobre você</label>
        <textarea class="form-control" id="p-bio" v-model="form.bio" rows="3" style="resize:vertical;"></textarea>
      </div>

      <div>
        <button type="submit" class="btn btn-primary">Salvar alterações</button>
      </div>
    </form>

  </main>
</template>

<script setup>
import { reactive, ref } from 'vue';

const form = reactive({
  name: 'Luis Felipe',
  phone: '(11) 99999-0000',
  email: 'victorhugomarquezin@gmail.com',
  bio: 'Aprendendo a operar com disciplina e gestão de risco.',
});

const showSuccess = ref(false);
let hideTimer = null;

function save() {
  showSuccess.value = true;
  clearTimeout(hideTimer);
  hideTimer = setTimeout(() => { showSuccess.value = false; }, 2500);
}
</script>
