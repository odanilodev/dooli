<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Empresas</h1>
        <p class="page-subtitle">{{ companies.items.length }} empresa(s) cadastrada(s)</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">{{ showForm ? 'Cancelar' : 'Nova empresa' }}</button>
    </div>

    <form v-if="showForm" class="panel" @submit.prevent="save">
      <h2 class="panel-title">{{ editingId ? `Editar empresa #${editingId}` : 'Nova empresa' }}</h2>
      <p v-if="!editingId" class="panel-desc">
        A empresa é criada com os tipos de usuário padrão (Administrador, Instrutor, Aluno) e o primeiro admin.
      </p>

      <div class="form-row">
        <div class="form-group">
          <label for="co-name">Nome da empresa</label>
          <input id="co-name" class="form-control" v-model="form.name" required placeholder="Minha Escola">
        </div>
        <div class="form-group">
          <label for="co-status">Status</label>
          <select id="co-status" class="form-control" v-model="form.status">
            <option value="active">Ativa</option>
            <option value="inactive">Inativa</option>
          </select>
        </div>
      </div>

      <template v-if="!editingId">
        <div class="form-row">
          <div class="form-group">
            <label for="co-admin-name">Admin — nome</label>
            <input id="co-admin-name" class="form-control" v-model="form.admin_name" required placeholder="Nome do responsável">
          </div>
          <div class="form-group">
            <label for="co-admin-username">Admin — usuário</label>
            <input id="co-admin-username" class="form-control" v-model="form.admin_username" required placeholder="usuario">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="co-admin-email">Admin — e-mail</label>
            <input id="co-admin-email" type="email" class="form-control" v-model="form.admin_email" required placeholder="admin@empresa.com">
          </div>
          <div class="form-group">
            <label for="co-admin-password">Admin — senha</label>
            <input id="co-admin-password" type="password" class="form-control" v-model="form.admin_password" :required="!editingId" placeholder="••••••••">
          </div>
        </div>
      </template>

      <h3 class="form-section-title">Identidade visual</h3>

      <div class="form-row">
        <div class="form-group">
          <label for="co-color">Cor primária</label>
          <div class="brand-color-row">
            <input type="color" id="co-color" class="brand-color-input" v-model="form.primary_color">
            <input class="form-control" type="text" v-model="form.primary_color" maxlength="7" placeholder="#17c993">
          </div>
          <span class="form-hint">Valor hexadecimal, ex.: #17c993.</span>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Logo — tema claro</label>
          <FilePicker
            accept="image/png,image/jpeg,image/webp"
            title="Escolher logo claro"
            hint="PNG, JPG ou WEBP — até 4MB"
            @change="onLogoLightPicked"
          />
          <div v-if="logoLightPreview" class="brand-logo-preview is-light">
            <img :src="logoLightPreview" alt="Prévia do logo do tema claro">
          </div>
        </div>

        <div class="form-group">
          <label>Logo — tema escuro</label>
          <FilePicker
            accept="image/png,image/jpeg,image/webp"
            title="Escolher logo escuro"
            hint="PNG, JPG ou WEBP — até 4MB"
            @change="onLogoDarkPicked"
          />
          <div v-if="logoDarkPreview" class="brand-logo-preview is-dark">
            <img :src="logoDarkPreview" alt="Prévia do logo do tema escuro">
          </div>
        </div>
      </div>

      <span class="form-hint">Se apenas um logo for enviado, ele será usado nos dois temas.</span>

      <div>
        <button class="btn btn-primary" :disabled="saving">
          {{ saving ? 'Salvando...' : (editingId ? 'Salvar empresa' : 'Criar empresa') }}
        </button>
      </div>
    </form>

    <template v-if="!showForm">

      <p v-if="companies.loading && !companies.items.length" class="page-subtitle">Carregando...</p>

      <div v-else-if="companies.items.length === 0" class="empty-state">
        <strong>Nenhuma empresa</strong>
        <span>Cadastre a primeira empresa.</span>
      </div>

      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Nome</th><th>Status</th><th>Criada em</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="company in companies.items" :key="company.id">
              <td><strong>{{ company.name }}</strong></td>
              <td><span class="status-pill" :class="company.status">{{ statusLabel(company.status) }}</span></td>
              <td class="admin-muted">{{ formatDate(company.created_at) }}</td>
              <td class="admin-actions">
                <button class="btn btn-outline btn-sm" @click="openEdit(company)">Editar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </template>

  </main>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useToast } from '../composables/useToast';
import { DEFAULT_PRIMARY_COLOR } from '../stores/branding';
import { useCompaniesStore } from '../stores/companies';
import FilePicker from '../components/admin/FilePicker.vue';

const toast = useToast();
const companies = useCompaniesStore();

const showForm = ref(false);
const editingId = ref(null);
const saving = ref(false);
const logoLightPreview = ref(null);
const logoLightFile = ref(null);
const logoDarkPreview = ref(null);
const logoDarkFile = ref(null);

const form = reactive({
  name: '',
  status: 'active',
  admin_name: '',
  admin_username: '',
  admin_email: '',
  admin_password: '',
  primary_color: DEFAULT_PRIMARY_COLOR,
});

function revokePreview(value) {
  if (value?.startsWith('blob:')) {
    URL.revokeObjectURL(value);
  }
}

function onLogoLightPicked(file) {
  revokePreview(logoLightPreview.value);
  logoLightFile.value = file || null;
  logoLightPreview.value = file ? URL.createObjectURL(file) : null;
}

function onLogoDarkPicked(file) {
  revokePreview(logoDarkPreview.value);
  logoDarkFile.value = file || null;
  logoDarkPreview.value = file ? URL.createObjectURL(file) : null;
}

function statusLabel(status) {
  return status === 'active' ? 'Ativa' : 'Inativa';
}

function formatDate(value) {
  if (!value) {
    return '—';
  }

  return new Date(value).toLocaleDateString('pt-BR');
}

function resetForm() {
  form.name = '';
  form.status = 'active';
  form.admin_name = '';
  form.admin_username = '';
  form.admin_email = '';
  form.admin_password = '';
  form.primary_color = DEFAULT_PRIMARY_COLOR;
  revokePreview(logoLightPreview.value);
  revokePreview(logoDarkPreview.value);
  logoLightFile.value = null;
  logoLightPreview.value = null;
  logoDarkFile.value = null;
  logoDarkPreview.value = null;
}

function openCreate() {
  if (showForm.value) {
    showForm.value = false;
    return;
  }

  editingId.value = null;
  resetForm();
  showForm.value = true;
}

async function openEdit(company) {
  editingId.value = company.id;
  form.name = company.name ?? '';
  form.status = company.status ?? 'active';
  form.primary_color = DEFAULT_PRIMARY_COLOR;
  revokePreview(logoLightPreview.value);
  revokePreview(logoDarkPreview.value);
  logoLightFile.value = null;
  logoDarkFile.value = null;
  logoLightPreview.value = null;
  logoDarkPreview.value = null;
  showForm.value = true;

  try {
    const branding = await companies.fetchBranding(company.id);
    form.primary_color = branding.primary_color || DEFAULT_PRIMARY_COLOR;
    logoLightPreview.value = branding.logo_light_url || branding.logo_url || null;
    logoDarkPreview.value = branding.logo_dark_url || null;
  } catch (e) {
    /* mantém os valores padrão em caso de falha */
  }
}

async function save() {
  saving.value = true;

  try {
    let companyId = editingId.value;

    if (editingId.value) {
      await companies.updateCompany(editingId.value, {
        name: form.name,
        status: form.status,
      });
    } else {
      const created = await companies.createCompany({
        name: form.name,
        status: form.status,
        admin_name: form.admin_name,
        admin_username: form.admin_username,
        admin_email: form.admin_email,
        admin_password: form.admin_password,
      });

      companyId = created.id;
    }

    await companies.saveBranding(companyId, {
      primary_color: form.primary_color,
      logo_light: logoLightFile.value,
      logo_dark: logoDarkFile.value,
    });

    toast.success(editingId.value ? 'Empresa atualizada.' : 'Empresa criada.');

    showForm.value = false;
    editingId.value = null;
    resetForm();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível salvar a empresa.');
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  companies.fetchCompanies();
});
</script>

<style scoped>
.form-section-title{
  margin:18px 0 4px;font-size:13px;font-weight:600;letter-spacing:.02em;
  text-transform:uppercase;color:var(--text-muted);
}
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
.brand-logo-preview.is-light{background:#f7f9fb;}
.brand-logo-preview.is-dark{background:#0b1420;border-color:#223247;}
.brand-logo-preview img{max-height:48px;max-width:100%;object-fit:contain;}
</style>
