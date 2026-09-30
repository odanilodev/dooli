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
        <span class="profile-avatar">{{ auth.initials }}</span>
      </div>
      <div class="profile-info">
        <span class="profile-name">{{ form.name || auth.user?.name }}</span>
        <span class="profile-role">{{ form.email || auth.user?.email }}</span>
        <span class="profile-plan" v-if="profile?.user_type?.name">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 6.6L21 10l-5 4.3L17.4 21 12 17.6 6.6 21 8 14.3 3 10l6.6-1.4z"/></svg>
          {{ profile.user_type.name }}
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

    <div v-if="store.loading && !store.loaded" class="tab-panel simple active">Carregando perfil...</div>

    <template v-else>
      <form class="panel" @submit.prevent="save">
        <div>
          <h2 class="panel-title">Informações pessoais</h2>
          <p class="panel-desc">Essas informações aparecem no seu perfil e certificados.</p>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="p-name">Nome completo</label>
            <input class="form-control" type="text" id="p-name" v-model="form.name" required>
          </div>
          <div class="form-group">
            <label for="p-username">Usuário</label>
            <input class="form-control" type="text" id="p-username" v-model="form.username">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="p-email">E-mail</label>
            <input class="form-control" type="email" id="p-email" v-model="form.email" required>
          </div>
          <div class="form-group">
            <label for="p-phone">Telefone</label>
            <input class="form-control" type="tel" id="p-phone" v-model="form.phone">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="p-city">Cidade</label>
            <input class="form-control" type="text" id="p-city" v-model="form.city">
          </div>
          <div class="form-group">
            <label for="p-address">Endereço</label>
            <input class="form-control" type="text" id="p-address" v-model="form.address">
          </div>
        </div>

        <div>
          <button type="submit" class="btn btn-primary" :disabled="store.saving">
            {{ store.saving ? 'Salvando...' : 'Salvar alterações' }}
          </button>
        </div>
      </form>

      <form class="panel" @submit.prevent="changePassword">
        <div>
          <h2 class="panel-title">Trocar senha</h2>
          <p class="panel-desc">Ao trocar a senha, os outros dispositivos conectados serão desconectados.</p>
        </div>

        <div class="form-group">
          <label for="p-current">Senha atual</label>
          <input class="form-control" type="password" id="p-current" v-model="passwordForm.current_password" autocomplete="current-password">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="p-new">Nova senha</label>
            <input class="form-control" type="password" id="p-new" v-model="passwordForm.password" autocomplete="new-password">
          </div>
          <div class="form-group">
            <label for="p-confirm">Confirmar nova senha</label>
            <input class="form-control" type="password" id="p-confirm" v-model="passwordForm.password_confirmation" autocomplete="new-password">
          </div>
        </div>

        <div>
          <button type="submit" class="btn btn-primary" :disabled="savingPassword">
            {{ savingPassword ? 'Alterando...' : 'Alterar senha' }}
          </button>
        </div>
      </form>
    </template>

  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useProfileStore } from '../stores/profile';
import { useToast } from '../composables/useToast';

const auth = useAuthStore();
const store = useProfileStore();
const toast = useToast();

const profile = computed(() => store.profile);

const form = reactive({ name: '', username: '', email: '', phone: '', city: '', address: '' });
const passwordForm = reactive({ current_password: '', password: '', password_confirmation: '' });

const savingPassword = ref(false);

function fillForm(source) {
  form.name = source?.name ?? '';
  form.username = source?.username ?? '';
  form.email = source?.email ?? '';
  form.phone = source?.phone ?? '';
  form.city = source?.city ?? '';
  form.address = source?.address ?? '';
}

function firstError(error) {
  const errors = error?.errors;

  if (!errors) {
    return '';
  }

  const first = Object.values(errors)[0];

  return Array.isArray(first) ? first[0] : String(first);
}

onMounted(async () => {
  const fetched = await store.fetch();
  fillForm(fetched);
});

async function save() {
  if (!form.name.trim() || !form.email.trim()) {
    toast.error('Informe nome e e-mail.');
    return;
  }

  const payload = { name: form.name.trim(), email: form.email.trim() };

  if (form.username.trim()) payload.username = form.username.trim();
  if (form.phone.trim()) payload.phone = form.phone.trim();
  if (form.city.trim()) payload.city = form.city.trim();
  if (form.address.trim()) payload.address = form.address.trim();

  try {
    const updated = await store.update(payload);
    fillForm(updated);
    toast.success('Alterações salvas.');
  } catch (e) {
    toast.error(firstError(e) || e?.message || 'Não foi possível salvar suas informações.');
  }
}

async function changePassword() {
  if (!passwordForm.current_password || !passwordForm.password) {
    toast.error('Informe a senha atual e a nova senha.');
    return;
  }

  if (passwordForm.password !== passwordForm.password_confirmation) {
    toast.error('As senhas não conferem.');
    return;
  }

  savingPassword.value = true;

  try {
    await store.updatePassword({ ...passwordForm });
    passwordForm.current_password = '';
    passwordForm.password = '';
    passwordForm.password_confirmation = '';
    toast.success('Senha alterada.');
  } catch (e) {
    toast.error(firstError(e) || e?.message || 'Não foi possível alterar a senha.');
  } finally {
    savingPassword.value = false;
  }
}
</script>
