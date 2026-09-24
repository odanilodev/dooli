<template>
  <form class="auth-card" @submit.prevent="submit" novalidate>
    <div class="auth-head">
      <h1>Bem-vindo de volta</h1>
      <p>Entre com sua conta para continuar seus estudos.</p>
    </div>

    <div class="form-success" :class="{ show: success }">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
      Login efetuado! Redirecionando...
    </div>

    <div class="form-error" :class="{ show: error }">{{ error }}</div>

    <div class="form-group">
      <label for="login-email">E-mail</label>
      <input class="form-control" type="email" id="login-email" v-model="form.email" placeholder="voce@email.com" required>
    </div>

    <div class="form-group">
      <label for="login-password">Senha</label>
      <input class="form-control" type="password" id="login-password" v-model="form.password" placeholder="••••••••" required>
    </div>

    <div class="form-links">
      <label class="form-check">
        <input type="checkbox" v-model="form.remember">
        Lembrar de mim
      </label>
      <a href="#" @click.prevent>Esqueci minha senha</a>
    </div>

    <button type="submit" class="btn btn-primary btn-block" :disabled="success || auth.loading">
      {{ auth.loading ? 'Entrando...' : 'Entrar' }}
    </button>

    <p class="auth-switch">Não tem conta? <router-link to="/cadastro">Cadastre-se</router-link></p>
  </form>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const form = reactive({ email: '', password: '', remember: false });
const success = ref(false);
const error = ref('');

async function submit() {
  error.value = '';

  if (!form.email.trim() || !form.password.trim()) {
    error.value = 'Informe e-mail e senha.';
    return;
  }

  try {
    await auth.login({ email: form.email.trim(), password: form.password });
    success.value = true;

    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/';
    setTimeout(() => router.push(redirect), 500);
  } catch (e) {
    error.value = e?.message || 'Não foi possível entrar.';
  }
}
</script>
