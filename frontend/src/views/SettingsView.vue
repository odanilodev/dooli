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
          <button type="submit" class="btn btn-primary">Salvar conta</button>
        </div>
      </form>

      <form class="panel" @submit.prevent="changePassword">
        <div>
          <h2 class="panel-title">Segurança</h2>
          <p class="panel-desc">Altere sua senha periodicamente para manter a conta segura.</p>
        </div>
        <div class="form-group">
          <label for="c-current-pass">Senha atual</label>
          <input class="form-control" type="password" id="c-current-pass" v-model="password.current" placeholder="••••••••" required>
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
          <button type="submit" class="btn btn-primary">Alterar senha</button>
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
          <button class="switch" :class="{ on: notifications.newsletter }" @click="notifications.newsletter = !notifications.newsletter"><span class="knob"></span></button>
        </div>
        <div class="settings-row">
          <div class="settings-row-text">
            <span class="settings-row-title">Lembretes de aula</span>
            <span class="settings-row-desc">Avisos para continuar de onde parou.</span>
          </div>
          <button class="switch" :class="{ on: notifications.reminders }" @click="notifications.reminders = !notifications.reminders"><span class="knob"></span></button>
        </div>
        <div class="settings-row">
          <div class="settings-row-text">
            <span class="settings-row-title">Notificações push</span>
            <span class="settings-row-desc">Alertas em tempo real no navegador.</span>
          </div>
          <button class="switch" :class="{ on: notifications.push }" @click="notifications.push = !notifications.push"><span class="knob"></span></button>
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
import { reactive, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useTheme } from '../composables/useTheme';
import { useToast } from '../composables/useToast';

const router = useRouter();
const { theme, setTheme } = useTheme();
const toast = useToast();

const account = reactive({
  email: 'victorhugomarquezin@gmail.com',
  phone: '(11) 99999-0000',
});
function saveAccount() {
  toast.success('Conta atualizada.');
}

const password = reactive({ current: '', next: '', confirm: '' });
const passwordMismatch = computed(() => password.next && password.confirm && password.next !== password.confirm);
function changePassword() {
  if (passwordMismatch.value) return;
  password.current = '';
  password.next = '';
  password.confirm = '';
  toast.success('Senha alterada.');
}

const notifications = reactive({ newsletter: true, reminders: true, push: false });

function deleteAccount() {
  if (window.confirm('Tem certeza que deseja excluir sua conta? Essa ação não pode ser desfeita.')) {
    router.push('/login');
  }
}
</script>
