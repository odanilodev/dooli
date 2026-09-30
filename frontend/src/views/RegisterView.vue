<template>
  <form class="auth-card" @submit.prevent="submit" novalidate>
    <div class="auth-head">
      <h1>Crie sua conta</h1>
      <p>Comece a aprender gratuitamente em poucos minutos.</p>
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

    <button type="submit" class="btn btn-primary btn-block">Criar conta</button>

    <p class="auth-switch">Já tem conta? <router-link to="/login">Entrar</router-link></p>
  </form>
</template>

<script setup>
import { reactive, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from '../composables/useToast';

const router = useRouter();
const toast = useToast();
const form = reactive({ name: '', email: '', password: '', passwordConfirm: '', terms: false });
const mismatch = computed(() => form.password && form.passwordConfirm && form.password !== form.passwordConfirm);

function submit() {
  const filled = form.name.trim() && form.email.trim() && form.password.trim() && form.passwordConfirm.trim();

  if (!filled) {
    toast.error('Preencha todos os campos.');
    return;
  }

  if (mismatch.value) {
    toast.error('As senhas não coincidem.');
    return;
  }

  if (!form.terms) {
    toast.error('Aceite os termos de uso para continuar.');
    return;
  }

  toast.success('Conta criada! Redirecionando...');
  setTimeout(() => router.push('/'), 700);
}
</script>
