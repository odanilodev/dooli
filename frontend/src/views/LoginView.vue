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
      <router-link :to="forgotRoute">Esqueci minha senha</router-link>
    </div>

    <button type="submit" class="btn btn-primary btn-block" :disabled="auth.loading">
      {{ auth.loading ? 'Entrando...' : 'Entrar' }}
    </button>

    <p v-if="isTenant" class="auth-switch"><router-link :to="registerTarget">Criar uma conta</router-link></p>
    <p v-else class="auth-switch">Para criar uma conta, use o link da sua empresa.</p>
  </form>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useTenant } from '../composables/useTenant';
import { useToast } from '../composables/useToast';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toast = useToast();
const {
  legacySlug,
  isTenant,
  publicRoute,
  registerRoute,
  verifyEmailRoute,
  safeNext,
} = useTenant();

const companySlug = computed(() => legacySlug.value);
const forgotRoute = computed(() => ({
  path: '/recuperar-senha',
  query: legacySlug.value ? { company: legacySlug.value } : {},
}));
// Destino pós-login: `redirect` (guard de rota) ou `next` (vindo do botão Comprar → cadastro → entrar).
const target = computed(() => {
  const redirect = route.query.redirect;

  if (typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')) {
    return redirect;
  }

  return safeNext(route.query.next);
});
const registerTarget = computed(() => {
  const next = safeNext(target.value);

  return registerRoute(next ? { next } : {});
});

const form = reactive({ email: '', password: '', remember: false });

async function submit() {
  if (!form.email.trim() || !form.password.trim()) {
    toast.error('Informe e-mail e senha.');
    return;
  }

  try {
    await auth.login({ email: form.email.trim(), password: form.password }, companySlug.value);
    toast.success('Login efetuado! Redirecionando...');

    const redirect = target.value
      ? target.value
      : companySlug.value
        ? publicRoute('public-loja')
        : { name: 'dashboard' };
    setTimeout(() => router.push(redirect), 500);
  } catch (e) {
    if (e?.code === 'email_not_verified') {
      const next = safeNext(target.value);

      toast.info('Confirme seu e-mail para continuar.');

      await router.push(verifyEmailRoute({
        email: form.email.trim(),
        ...(next ? { next } : {}),
      }));

      return;
    }

    toast.error(e?.message || 'Não foi possível entrar.');
  }
}
</script>
