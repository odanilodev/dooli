<template>
  <form class="auth-card" @submit.prevent="submit" novalidate>
    <div class="auth-head">
      <h1>Redefinir senha</h1>
      <p>Crie uma nova senha para acessar sua conta.</p>
    </div>

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

    <p class="auth-switch">Lembrou a senha? <router-link :to="loginRoute">Voltar para o login</router-link></p>
  </form>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useTenant } from '../composables/useTenant';
import { useToast } from '../composables/useToast';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toast = useToast();
const { legacySlug, tenant } = useTenant();

const companySlug = computed(() => {
  if (legacySlug.value) {
    return legacySlug.value;
  }

  return !tenant.isDomainMode && typeof route.query.company === 'string'
    ? route.query.company
    : null;
});
const loginRoute = computed(() => {
  if (companySlug.value) {
    return { name: 'legacy-login', params: { companySlug: companySlug.value } };
  }

  return tenant.isDomainMode ? { name: 'public-login' } : { name: 'login' };
});

const form = reactive({
  token: typeof route.query.token === 'string' ? route.query.token : '',
  email: typeof route.query.email === 'string' ? route.query.email : '',
  password: '',
  password_confirmation: '',
});

const loading = ref(false);

async function submit() {
  if (!form.token) {
    toast.error('Link inválido ou expirado. Solicite um novo.');
    return;
  }

  if (!form.email.trim() || !form.password.trim()) {
    toast.error('Informe o e-mail e a nova senha.');
    return;
  }

  if (form.password !== form.password_confirmation) {
    toast.error('As senhas não conferem.');
    return;
  }

  loading.value = true;

  try {
    await auth.resetPassword({ ...form }, companySlug.value);
    toast.success('Senha redefinida! Redirecionando para o login...');
    setTimeout(() => router.push(loginRoute.value), 1500);
  } catch (e) {
    toast.error(e?.message || 'Não foi possível redefinir a senha.');
  } finally {
    loading.value = false;
  }
}
</script>
