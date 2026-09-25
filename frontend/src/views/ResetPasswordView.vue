<template>
  <form class="auth-card" @submit.prevent="submit" novalidate>
    <div class="auth-head">
      <h1>Redefinir senha</h1>
      <p>Crie uma nova senha para acessar sua conta.</p>
    </div>

    <div class="form-success" :class="{ show: success }">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
      Senha redefinida! Redirecionando para o login...
    </div>

    <div class="form-error" :class="{ show: error }">{{ error }}</div>

    <template v-if="!success">
      <div class="form-group">
        <label for="reset-email">E-mail</label>
        <input class="form-control" type="email" id="reset-email" v-model="form.email" placeholder="voce@email.com" required>
      </div>

      <div class="form-group">
        <label for="reset-password">Nova senha</label>
        <input class="form-control" type="password" id="reset-password" v-model="form.password" placeholder="••••••••" autocomplete="new-password" required>
      </div>

      <div class="form-group">
        <label for="reset-password-confirm">Confirmar nova senha</label>
        <input class="form-control" type="password" id="reset-password-confirm" v-model="form.password_confirmation" placeholder="••••••••" autocomplete="new-password" required>
      </div>

      <button type="submit" class="btn btn-primary btn-block" :disabled="loading">
        {{ loading ? 'Salvando...' : 'Redefinir senha' }}
      </button>
    </template>

    <p class="auth-switch">Lembrou a senha? <router-link to="/login">Voltar para o login</router-link></p>
  </form>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const form = reactive({
  token: typeof route.query.token === 'string' ? route.query.token : '',
  email: typeof route.query.email === 'string' ? route.query.email : '',
  password: '',
  password_confirmation: '',
});

const loading = ref(false);
const success = ref(false);
const error = ref('');

async function submit() {
  error.value = '';

  if (!form.token) {
    error.value = 'Link inválido ou expirado. Solicite um novo.';
    return;
  }

  if (!form.email.trim() || !form.password.trim()) {
    error.value = 'Informe o e-mail e a nova senha.';
    return;
  }

  if (form.password !== form.password_confirmation) {
    error.value = 'As senhas não conferem.';
    return;
  }

  loading.value = true;

  try {
    await auth.resetPassword({ ...form });
    success.value = true;
    setTimeout(() => router.push('/login'), 1500);
  } catch (e) {
    error.value = e?.message || 'Não foi possível redefinir a senha.';
  } finally {
    loading.value = false;
  }
}
</script>
