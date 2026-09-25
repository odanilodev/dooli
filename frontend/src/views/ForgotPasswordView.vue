<template>
  <form class="auth-card" @submit.prevent="submit" novalidate>
    <div class="auth-head">
      <h1>Recuperar senha</h1>
      <p>Informe seu e-mail e enviaremos um link para redefinir sua senha.</p>
    </div>

    <div class="form-success" :class="{ show: success }">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
      Se o e-mail estiver cadastrado, enviaremos o link de redefinição.
    </div>

    <div class="form-error" :class="{ show: error }">{{ error }}</div>

    <div class="form-group">
      <label for="forgot-email">E-mail</label>
      <input class="form-control" type="email" id="forgot-email" v-model="email" placeholder="voce@email.com" required :disabled="success">
    </div>

    <button type="submit" class="btn btn-primary btn-block" :disabled="loading || success">
      {{ loading ? 'Enviando...' : 'Enviar link' }}
    </button>

    <p class="auth-switch">Lembrou a senha? <router-link to="/login">Voltar para o login</router-link></p>
  </form>
</template>

<script setup>
import { ref } from 'vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();

const email = ref('');
const loading = ref(false);
const success = ref(false);
const error = ref('');

async function submit() {
  error.value = '';

  if (!email.value.trim()) {
    error.value = 'Informe seu e-mail.';
    return;
  }

  loading.value = true;

  try {
    await auth.forgotPassword(email.value.trim());
    success.value = true;
  } catch (e) {
    error.value = e?.message || 'Não foi possível enviar o link.';
  } finally {
    loading.value = false;
  }
}
</script>
