<template>
  <form class="auth-card" @submit.prevent="submit" novalidate>
    <div class="auth-head">
      <h1>Recuperar senha</h1>
      <p>Informe seu e-mail e enviaremos um link para redefinir sua senha.</p>
    </div>

    <div class="form-group">
      <label for="forgot-email">E-mail</label>
      <input class="form-control" type="email" id="forgot-email" v-model="email" placeholder="voce@email.com" required :disabled="loading">
    </div>

    <button type="submit" class="btn btn-primary btn-block" :disabled="loading">
      {{ loading ? 'Enviando...' : 'Enviar link' }}
    </button>

    <p class="auth-switch">Lembrou a senha? <router-link :to="loginRoute">Voltar para o login</router-link></p>
  </form>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useTenant } from '../composables/useTenant';
import { useToast } from '../composables/useToast';

const route = useRoute();
const auth = useAuthStore();
const toast = useToast();
const { legacySlug, tenant } = useTenant();

const email = ref('');
const loading = ref(false);
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

async function submit() {
  if (!email.value.trim()) {
    toast.error('Informe seu e-mail.');
    return;
  }

  loading.value = true;

  try {
    await auth.forgotPassword(email.value.trim(), companySlug.value);
    toast.success('Se o e-mail estiver cadastrado, enviaremos o link de redefinição.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível enviar o link.');
  } finally {
    loading.value = false;
  }
}
</script>
