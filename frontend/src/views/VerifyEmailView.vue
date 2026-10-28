<template>
  <form class="auth-card" @submit.prevent="submit" novalidate>
    <div class="auth-head">
      <h1>Confirme seu e-mail</h1>
      <p>
        Enviamos um código de 6 dígitos para
        <strong class="verification-email">{{ email || 'seu e-mail' }}</strong>.
      </p>
    </div>

    <div class="form-group">
      <label for="verification-code">Código de confirmação</label>
      <input
        class="form-control verification-code"
        type="text"
        id="verification-code"
        v-model="form.code"
        inputmode="numeric"
        autocomplete="one-time-code"
        maxlength="6"
        placeholder="000000"
        autofocus
        :disabled="auth.loading"
        @input="onCodeInput"
      >
      <span class="form-hint">O código expira em 15 minutos.</span>
    </div>

    <button type="submit" class="btn btn-primary btn-block" :disabled="auth.loading">
      {{ auth.loading ? 'Confirmando...' : 'Confirmar e-mail' }}
    </button>

    <div class="verification-actions">
      <button
        type="button"
        class="verification-resend"
        :disabled="resendIn > 0 || resending"
        @click="resend"
      >
        {{ resendLabel }}
      </button>
      <router-link :to="registerTarget" class="verification-link">Usar outro e-mail</router-link>
    </div>

    <p class="auth-switch">Já confirmou? <router-link :to="loginTarget">Entrar</router-link></p>
  </form>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useTenant } from '../composables/useTenant';
import { useToast } from '../composables/useToast';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toast = useToast();
const {
  companySlug,
  isTenant,
  loginRoute,
  publicRoute,
  registerRoute,
  safeNext,
} = useTenant();

const form = reactive({ code: '' });
const resendIn = ref(0);
const resending = ref(false);
let resendTimer = null;

const email = computed(() => (typeof route.query.email === 'string' ? route.query.email.trim() : ''));
const nextUrl = computed(() => safeNext(route.query.next));
const registerTarget = computed(() => registerRoute(nextUrl.value ? { next: nextUrl.value } : {}));
const loginTarget = computed(() => loginRoute());
const resendLabel = computed(() => {
  if (resending.value) {
    return 'Enviando novo código...';
  }

  return resendIn.value > 0
    ? `Reenviar código em ${resendIn.value}s`
    : 'Reenviar código';
});

onMounted(() => {
  if (!email.value) {
    toast.error('Informe o e-mail para confirmar a conta.');
    router.replace(registerTarget.value);

    return;
  }

  if (route.query.sent === '1') {
    startCooldown();
  }
});

onBeforeUnmount(() => {
  stopCooldown();
});

function onCodeInput() {
  form.code = form.code.replace(/\D/g, '').slice(0, 6);
}

async function submit() {
  if (!/^\d{6}$/.test(form.code)) {
    toast.error('Informe o código de 6 dígitos.');
    return;
  }

  try {
    await auth.verifyEmail({ email: email.value, code: form.code }, companySlug.value);
    toast.success('E-mail confirmado! Bem-vindo(a).');

    if (nextUrl.value) {
      await router.push(nextUrl.value);
    } else if (isTenant.value) {
      await router.push(publicRoute('public-loja'));
    } else {
      await router.push({ name: 'dashboard' });
    }
  } catch (e) {
    const firstError = e?.errors ? Object.values(e.errors).flat()[0] : null;
    toast.error(firstError || e?.message || 'Não foi possível confirmar o e-mail.');
  }
}

async function resend() {
  if (!email.value || resendIn.value > 0 || resending.value) {
    return;
  }

  resending.value = true;

  try {
    const data = await auth.resendVerification(email.value, companySlug.value);
    toast.success(data?.data?.message || 'Se a conta estiver pendente, enviaremos um novo código.');
    startCooldown();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível reenviar o código.');
  } finally {
    resending.value = false;
  }
}

function startCooldown() {
  stopCooldown();
  resendIn.value = 60;
  resendTimer = window.setInterval(() => {
    resendIn.value -= 1;

    if (resendIn.value <= 0) {
      stopCooldown();
    }
  }, 1000);
}

function stopCooldown() {
  if (resendTimer !== null) {
    window.clearInterval(resendTimer);
    resendTimer = null;
  }
}
</script>

<style scoped>
.verification-email{color:var(--text-primary);word-break:break-word;}
.verification-code{
  text-align:center;font-size:24px;font-weight:800;letter-spacing:.35em;
  padding:13px 12px;font-variant-numeric:tabular-nums;
}
.verification-actions{display:flex;flex-direction:column;align-items:center;gap:10px;}
.verification-resend,
.verification-link{
  background:none;border:none;padding:0;font-size:12.5px;font-weight:700;
  color:var(--accent);cursor:pointer;font-family:inherit;
}
.verification-resend:disabled{color:var(--text-muted);cursor:default;}
.verification-resend:not(:disabled):hover,
.verification-link:hover{text-decoration:underline;}
</style>
