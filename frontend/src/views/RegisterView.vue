<template>
  <form class="auth-card" @submit.prevent="submit" novalidate>
    <div class="auth-head">
      <h1>Crie sua conta</h1>
      <p>Comece a aprender gratuitamente em poucos minutos.</p>
    </div>

    <div class="form-group">
      <label for="reg-name">Nome completo</label>
      <input
        class="form-control"
        type="text"
        id="reg-name"
        v-model="form.name"
        placeholder="Seu nome"
        autocomplete="name"
        required
        :disabled="auth.loading"
      >
    </div>

    <div class="form-group">
      <label for="reg-email">E-mail</label>
      <input
        class="form-control"
        type="email"
        id="reg-email"
        v-model="form.email"
        placeholder="voce@email.com"
        autocomplete="email"
        required
        :disabled="auth.loading"
      >
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="reg-password">Senha</label>
        <input
          class="form-control"
          type="password"
          id="reg-password"
          v-model="form.password"
          placeholder="••••••••"
          autocomplete="new-password"
          required
          :disabled="auth.loading"
        >
      </div>
      <div class="form-group">
        <label for="reg-password-confirm">Confirmar senha</label>
        <input
          class="form-control"
          type="password"
          id="reg-password-confirm"
          v-model="form.passwordConfirm"
          placeholder="••••••••"
          autocomplete="new-password"
          required
          :disabled="auth.loading"
        >
      </div>
    </div>
    <span class="form-error" :class="{ show: mismatch }">As senhas não coincidem.</span>

    <div class="form-check terms-check">
      <input id="reg-terms" type="checkbox" v-model="form.terms" :disabled="auth.loading">
      <label class="terms-copy" for="reg-terms">
        Li e aceito os
        <router-link
          :to="termsLink('#termos')"
          target="_blank"
          rel="noopener noreferrer"
          class="terms-link"
          @click.stop
        >Termos de Uso</router-link>
        e a
        <router-link
          :to="termsLink('#privacidade')"
          target="_blank"
          rel="noopener noreferrer"
          class="terms-link"
          @click.stop
        >Política de Privacidade</router-link>.
      </label>
    </div>

    <button type="submit" class="btn btn-primary btn-block" :disabled="auth.loading">
      {{ auth.loading ? 'Criando conta...' : 'Criar conta' }}
    </button>

    <p v-if="isTenant" class="auth-switch">Já tem conta? <router-link :to="loginRoute">Entrar</router-link></p>
    <p v-else class="auth-switch">Já tem conta? <router-link to="/login">Entrar</router-link></p>
  </form>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useReferral } from '../composables/useReferral';
import { useTenant } from '../composables/useTenant';
import { useToast } from '../composables/useToast';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const referral = useReferral();
const toast = useToast();
const {
  legacySlug,
  isTenant,
  companySlug: tenantCompanySlug,
  publicRoute,
  loginRoute: tenantLoginRoute,
  verifyEmailRoute,
  safeNext,
} = useTenant();
const form = reactive({ name: '', email: '', password: '', passwordConfirm: '', terms: false });
const mismatch = computed(() => form.password && form.passwordConfirm && form.password !== form.passwordConfirm);
const companySlug = computed(() => legacySlug.value);
const referralCompany = computed(() => tenantCompanySlug.value);

watch(
  () => route.query.ref,
  (ref) => {
    if (typeof ref === 'string' && ref !== '' && referralCompany.value) {
      referral.capture(referralCompany.value, ref);
    }
  },
  { immediate: true },
);
const nextUrl = computed(() => safeNext(route.query.next));
// Mantém o destino (ex.: checkout do produto) ao trocar de cadastro para login.
const loginRoute = computed(() => tenantLoginRoute(nextUrl.value ? { redirect: nextUrl.value } : {}));

function termsLink(hash) {
  const target = publicRoute('public-termos');

  return hash ? { ...target, hash } : target;
}

async function submit() {
  const name = form.name.trim();
  const email = form.email.trim();

  if (!name || !email || !form.password || !form.passwordConfirm) {
    toast.error('Preencha todos os campos.');
    return;
  }

  if (form.password.length < 8) {
    toast.error('A senha deve ter pelo menos 8 caracteres.');
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

  try {
    const result = await auth.register({
      name,
      email,
      password: form.password,
      password_confirmation: form.passwordConfirm,
      terms: true,
      referral_code: referral.current(referralCompany.value) || null,
    }, companySlug.value);

    toast.success('Conta criada! Confirme seu e-mail para entrar.');

    await router.push(verifyEmailRoute({
      email: result?.user?.email ?? email,
      sent: '1',
      ...(nextUrl.value ? { next: nextUrl.value } : {}),
    }));
  } catch (e) {
    const firstError = e?.errors ? Object.values(e.errors).flat()[0] : null;
    toast.error(firstError || e?.message || 'Não foi possível criar a conta.');
  }
}
</script>

<style scoped>
.terms-check{align-items:flex-start;line-height:1.55;}
.terms-check input{margin-top:2px;flex-shrink:0;}
.terms-copy{cursor:pointer;}
.terms-link{color:var(--accent);font-weight:700;}
.terms-link:hover{text-decoration:underline;}
</style>
