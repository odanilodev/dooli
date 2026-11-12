<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Configurações</h1>
        <p class="page-subtitle">Gerencie sua conta, segurança, notificações e preferências.</p>
      </div>
    </div>

    <div class="settings-grid">

      <form class="panel" @submit.prevent="saveAccount">
        <div>
          <h2 class="panel-title">Conta</h2>
          <p class="panel-desc">Dados usados para login e comunicação.</p>
        </div>
        <div class="form-group">
          <label for="c-email">E-mail</label>
          <input class="form-control" type="email" id="c-email" v-model="account.email" required>
        </div>
        <div class="form-group">
          <label for="c-phone">Telefone</label>
          <input class="form-control" type="tel" id="c-phone" v-model="account.phone">
        </div>
        <div>
          <button type="submit" class="btn btn-primary" :disabled="store.saving">Salvar conta</button>
        </div>
      </form>

      <form class="panel" @submit.prevent="changePassword">
        <div>
          <h2 class="panel-title">Segurança</h2>
          <p class="panel-desc">Altere sua senha periodicamente para manter a conta segura.</p>
        </div>
        <div class="form-group">
          <label for="c-current-pass">Senha atual</label>
          <input class="form-control" type="password" id="c-current-pass" v-model="password.current" placeholder="••••••••" autocomplete="current-password" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="c-new-pass">Nova senha</label>
            <input class="form-control" type="password" id="c-new-pass" v-model="password.next" placeholder="••••••••" required>
          </div>
          <div class="form-group">
            <label for="c-new-pass-confirm">Confirmar</label>
            <input class="form-control" type="password" id="c-new-pass-confirm" v-model="password.confirm" placeholder="••••••••" required>
          </div>
        </div>
        <span class="form-error" :class="{ show: passwordMismatch }">As senhas não coincidem.</span>
        <div>
          <button type="submit" class="btn btn-primary" :disabled="savingPassword">Alterar senha</button>
        </div>
      </form>

      <div class="panel">
        <div>
          <h2 class="panel-title">Notificações</h2>
          <p class="panel-desc">Escolha como você quer ser avisado.</p>
        </div>
        <div class="settings-row">
          <div class="settings-row-text">
            <span class="settings-row-title">Novidades por e-mail</span>
            <span class="settings-row-desc">Lançamentos de cursos e promoções.</span>
          </div>
          <button class="switch" type="button" role="switch" :aria-checked="notifications.newsletter" :class="{ on: notifications.newsletter }" :disabled="!store.loaded" @click="toggleNotification('notify_newsletter', 'newsletter')"><span class="knob"></span></button>
        </div>
        <div class="settings-row">
          <div class="settings-row-text">
            <span class="settings-row-title">Lembretes de aula</span>
            <span class="settings-row-desc">Avisos para continuar de onde parou.</span>
          </div>
          <button class="switch" type="button" role="switch" :aria-checked="notifications.reminders" :class="{ on: notifications.reminders }" :disabled="!store.loaded" @click="toggleNotification('notify_lesson_reminders', 'reminders')"><span class="knob"></span></button>
        </div>
      </div>

      <div class="panel">
        <div>
          <h2 class="panel-title">Preferências</h2>
          <p class="panel-desc">Personalize a aparência da plataforma.</p>
        </div>
        <div class="theme-options">
          <button class="theme-option" :class="{ active: theme === 'dark' }" type="button" @click="setTheme('dark')">
            <span class="theme-option-swatch" style="background:linear-gradient(135deg,#0b1420,#141f30);"></span>
            <span>Escuro</span>
          </button>
          <button class="theme-option" :class="{ active: theme === 'light' }" type="button" @click="setTheme('light')">
            <span class="theme-option-swatch" style="background:linear-gradient(135deg,#f3f5f8,#ffffff);border:1px solid var(--border);"></span>
            <span>Claro</span>
          </button>
        </div>
      </div>

      <div class="panel danger-zone">
        <div>
          <h2 class="panel-title">Zona de perigo</h2>
          <p class="panel-desc">Ações permanentes — não podem ser desfeitas.</p>
        </div>
        <div class="settings-row">
          <div class="settings-row-text">
            <span class="settings-row-title">Excluir conta</span>
            <span class="settings-row-desc">Remove todos os seus dados e progresso permanentemente.</span>
          </div>
          <button class="btn btn-danger" type="button" @click="deleteAccount">Excluir conta</button>
        </div>
      </div>

    </div>

  </main>
</template>

<script setup>
import { reactive, computed, ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useTenant } from '../composables/useTenant';
import { useTheme } from '../composables/useTheme';
import { useToast } from '../composables/useToast';
import { useProfileStore } from '../stores/profile';

const router = useRouter();
const { loginRoute } = useTenant();
const { theme, setTheme } = useTheme();
const toast = useToast();

const store = useProfileStore();

const account = reactive({ email: '', phone: '' });
const notifications = reactive({ newsletter: true, reminders: true });
const savingPassword = ref(false);

function firstError(error) {
  const errors = error?.errors;

  if (!errors) {
    return '';
  }

  const first = Object.values(errors)[0];

  return Array.isArray(first) ? first[0] : String(first);
}

function fillFromProfile(profile) {
  account.email = profile?.email ?? '';
  account.phone = profile?.phone ?? '';
  notifications.newsletter = profile?.notify_newsletter ?? true;
  notifications.reminders = profile?.notify_lesson_reminders ?? true;
}

onMounted(async () => {
  const profile = await store.fetch();

  if (profile) {
    fillFromProfile(profile);
  } else {
    toast.error(store.error || 'Não foi possível carregar suas configurações.');
  }
});

async function saveAccount() {
  const payload = { email: account.email.trim(), phone: account.phone.trim() || null };

  try {
    const updated = await store.update(payload);
    fillFromProfile(updated);
    toast.success('Conta atualizada.');
  } catch (e) {
    toast.error(firstError(e) || e?.message || 'Não foi possível atualizar a conta.');
  }
}

const password = reactive({ current: '', next: '', confirm: '' });
const passwordMismatch = computed(() => password.next && password.confirm && password.next !== password.confirm);
async function changePassword() {
  if (passwordMismatch.value) return;

  savingPassword.value = true;

  try {
    await store.updatePassword({
      current_password: password.current,
      password: password.next,
      password_confirmation: password.confirm,
    });
    password.current = '';
    password.next = '';
    password.confirm = '';
    toast.success('Senha alterada.');
  } catch (e) {
    toast.error(firstError(e) || e?.message || 'Não foi possível alterar a senha.');
  } finally {
    savingPassword.value = false;
  }
}

// Atualização otimista: aplica na hora e reverte se a API falhar.
async function toggleNotification(field, key) {
  const previous = notifications[key];
  notifications[key] = !previous;

  try {
    await store.update({ [field]: notifications[key] });
  } catch (e) {
    notifications[key] = previous;
    toast.error(firstError(e) || e?.message || 'Não foi possível salvar a preferência.');
  }
}

function deleteAccount() {
  if (window.confirm('Tem certeza que deseja excluir sua conta? Essa ação não pode ser desfeita.')) {
    router.push(loginRoute());
  }
}
</script>


