<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Configurações</h1>
        <p class="page-subtitle">Gerencie sua conta, segurança, notificações e preferências.</p>
      </div>
    </div>

    <div class="settings-grid">

      <form v-if="auth.isAdmin" class="panel" @submit.prevent="saveBranding">
        <div>
          <h2 class="panel-title">Identidade visual</h2>
          <p class="panel-desc">Cor primária e logo exibidos para você e seus alunos.</p>
        </div>
        <div class="form-group">
          <label for="brand-color">Cor primária</label>
          <div class="brand-color-row">
            <input type="color" id="brand-color" class="brand-color-input" v-model="brandingForm.primary_color">
            <input class="form-control" type="text" v-model="brandingForm.primary_color" maxlength="7" placeholder="#17c993">
          </div>
          <span class="form-hint">Valor hexadecimal, ex.: #17c993.</span>
        </div>
        <div class="form-group">
          <label>Logo</label>
          <FilePicker accept="image/png,image/jpeg,image/webp" title="Escolher logo" hint="PNG, JPG ou WEBP — até 4MB" @change="onLogoPicked" />
          <div v-if="logoPreview" class="brand-logo-preview">
            <img :src="logoPreview" alt="Prévia do logo">
          </div>
        </div>
        <div class="brand-actions">
          <button type="submit" class="btn btn-primary" :disabled="brandingSaving">Salvar identidade visual</button>
          <button type="button" class="btn btn-outline" :disabled="brandingSaving" @click="resetBranding">Redefinir padrão</button>
        </div>
      </form>

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
import { reactive, computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api/http';
import { useAuthStore } from '../stores/auth';
import { DEFAULT_PRIMARY_COLOR, useBrandingStore } from '../stores/branding';
import { useTheme } from '../composables/useTheme';
import { useToast } from '../composables/useToast';
import FilePicker from '../components/admin/FilePicker.vue';

const router = useRouter();
const { theme, setTheme } = useTheme();
const toast = useToast();
const auth = useAuthStore();
const branding = useBrandingStore();

const brandingForm = reactive({ primary_color: branding.primaryColor || DEFAULT_PRIMARY_COLOR });
const logoPreview = ref(branding.logoUrl || null);
const logoFile = ref(null);
const brandingSaving = ref(false);

function onLogoPicked(file) {
  logoFile.value = file || null;

  if (file) {
    logoPreview.value = URL.createObjectURL(file);
  }
}

async function saveBranding() {
  brandingSaving.value = true;

  try {
    const payload = new FormData();
    payload.append('_method', 'PUT');

    if (brandingForm.primary_color) {
      payload.append('primary_color', brandingForm.primary_color);
    }

    if (logoFile.value) {
      payload.append('logo', logoFile.value);
    }

    const { data } = await api.post('/admin/company/settings', payload);
    const saved = data.data;

    branding.set(saved);
    brandingForm.primary_color = saved.primary_color || brandingForm.primary_color;
    logoPreview.value = saved.logo_url || logoPreview.value;
    logoFile.value = null;
    toast.success('Identidade visual atualizada.');
  } catch (error) {
    toast.error(error.message || 'Não foi possível salvar a identidade visual.');
  } finally {
    brandingSaving.value = false;
  }
}

async function resetBranding() {
  brandingSaving.value = true;

  try {
    const { data } = await api.put('/admin/company/settings', { primary_color: null });

    branding.set(data.data);
    brandingForm.primary_color = DEFAULT_PRIMARY_COLOR;
    logoFile.value = null;
    logoPreview.value = data.data.logo_url || null;
    toast.success('Cor padrão restaurada.');
  } catch (error) {
    toast.error(error.message || 'Não foi possível redefinir a cor.');
  } finally {
    brandingSaving.value = false;
  }
}

onMounted(async () => {
  if (!auth.isAdmin) {
    return;
  }

  try {
    const { data } = await api.get('/admin/company/settings');
    brandingForm.primary_color = data.data.primary_color || DEFAULT_PRIMARY_COLOR;
    logoPreview.value = data.data.logo_url || null;
  } catch (error) {
    /* mantém os valores atuais em caso de falha */
  }
});

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

<style scoped>
.brand-actions{display:flex;gap:10px;flex-wrap:wrap;}
.brand-color-row{display:flex;align-items:center;gap:10px;}
.brand-color-input{
  width:46px;height:42px;padding:0;border:1px solid var(--border);border-radius:10px;
  background:var(--bg-input);cursor:pointer;flex-shrink:0;
}
.form-hint{font-size:11.5px;color:var(--text-muted);}
.brand-logo-preview{
  display:flex;align-items:center;justify-content:center;margin-top:10px;padding:12px;
  border:1px solid var(--border);border-radius:10px;background:var(--bg-card-alt);
}
.brand-logo-preview img{max-height:48px;max-width:100%;object-fit:contain;}
</style>
