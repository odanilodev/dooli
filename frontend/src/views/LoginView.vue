<template>
  <form class="auth-card" @submit.prevent="submit" novalidate>
    <div class="auth-head">
      <h1>Bem-vindo de volta</h1>
      <p>Entre com sua conta para continuar seus estudos.</p>
    </div>

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
      <router-link :to="{ path: '/recuperar-senha', query: companySlug ? { company: companySlug } : {} }">Esqueci minha senha</router-link>
    </div>

    <button type="submit" class="btn btn-primary btn-block" :disabled="auth.loading">
      {{ auth.loading ? 'Entrando...' : 'Entrar' }}
    </button>

    <p v-if="companySlug" class="auth-switch">Ainda não tem conta? <router-link :to="{ name: 'public-loja', params: { companySlug } }">Compre um curso para criar sua conta.</router-link></p>
    <p v-else class="auth-switch">Para criar uma conta, use o link da sua empresa.</p>
  </form>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useToast } from '../composables/useToast';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toast = useToast();

const companySlug = computed(() => (typeof route.params.companySlug === 'string' ? route.params.companySlug : null));

const form = reactive({ email: '', password: '', remember: false });

async function submit() {
  if (!form.email.trim() || !form.password.trim()) {
    toast.error('Informe e-mail e senha.');
    return;
  }

  try {
    await auth.login({ email: form.email.trim(), password: form.password }, companySlug.value);
    toast.success('Login efetuado! Redirecionando...');

    const redirect = typeof route.query.redirect === 'string'
      ? route.query.redirect
      : companySlug.value
        ? { name: 'public-loja', params: { companySlug: companySlug.value } }
        : '/';
    setTimeout(() => router.push(redirect), 500);
  } catch (e) {
    toast.error(e?.message || 'Não foi possível entrar.');
  }
}
</script>
