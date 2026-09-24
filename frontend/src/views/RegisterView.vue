<template>
  <form class="auth-card" @submit.prevent="submit" novalidate>
    <div class="auth-head">
      <h1>Crie sua conta</h1>
      <p>Comece a aprender gratuitamente em poucos minutos.</p>
    </div>

    <div class="form-success" :class="{ show: success }">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
      Conta criada! Redirecionando...
    </div>

    <div class="form-group">
      <label for="reg-name">Nome completo</label>
      <input class="form-control" type="text" id="reg-name" v-model="form.name" placeholder="Seu nome" required>
    </div>

    <div class="form-group">
      <label for="reg-email">E-mail</label>
      <input class="form-control" type="email" id="reg-email" v-model="form.email" placeholder="voce@email.com" required>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="reg-password">Senha</label>
        <input class="form-control" type="password" id="reg-password" v-model="form.password" placeholder="••••••••" required>
      </div>
      <div class="form-group">
        <label for="reg-password-confirm">Confirmar senha</label>
        <input class="form-control" type="password" id="reg-password-confirm" v-model="form.passwordConfirm" placeholder="••••••••" required>
      </div>
    </div>
    <span class="form-error" :class="{ show: mismatch }">As senhas não coincidem.</span>

    <label class="form-check">
      <input type="checkbox" v-model="form.terms" required>
      Concordo com os <a href="#" style="color:var(--accent);font-weight:700;" @click.prevent>termos de uso</a> e a <a href="#" style="color:var(--accent);font-weight:700;" @click.prevent>política de privacidade</a>.
    </label>

    <button type="submit" class="btn btn-primary btn-block" :disabled="success">Criar conta</button>

    <p class="auth-switch">Já tem conta? <router-link to="/login">Entrar</router-link></p>
  </form>
</template>

<script setup>
import { reactive, ref, computed } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const form = reactive({ name: '', email: '', password: '', passwordConfirm: '', terms: false });
const success = ref(false);
const mismatch = computed(() => form.password && form.passwordConfirm && form.password !== form.passwordConfirm);

function submit() {
  const filled = form.name.trim() && form.email.trim() && form.password.trim() && form.passwordConfirm.trim();
  if (!filled || mismatch.value || !form.terms) return;
  success.value = true;
  setTimeout(() => router.push('/'), 700);
}
</script>
