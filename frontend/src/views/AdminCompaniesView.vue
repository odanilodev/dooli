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

      <div class="form-row">
        <div class="form-group">
          <label for="co-slug">Slug da loja</label>
          <input id="co-slug" class="form-control" v-model="form.slug" placeholder="minha-escola">
          <span class="form-hint">Fallback local/legado em /e/{slug}/loja. O domínio é o endereço principal da empresa.</span>
        </div>
        <div class="form-group">
          <label for="co-domain">Domínio</label>
          <input id="co-domain" class="form-control" v-model="form.domain" placeholder="minhaescola.com.br">
          <span class="form-hint">Opcional. Resolve a loja, o login e o painel da empresa. Informe apenas o host, sem https://, www. ou caminhos.</span>
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

      <template v-if="editingId">
        <h3 class="form-section-title">E-mail transacional (Resend)</h3>
        <p class="panel-desc">
          A conta Resend desta empresa envia verificação, recuperação de senha e notificações.
          Sem configuração válida, a plataforma usa a conta padrão.
        </p>

        <div class="form-row">
          <div class="form-group">
            <label for="mail-from-name">Nome do remetente</label>
            <input id="mail-from-name" class="form-control" v-model="mailForm.from_name" placeholder="Minha Escola">
          </div>
          <div class="form-group">
            <label for="mail-from-address">E-mail do remetente</label>
            <input id="mail-from-address" type="email" class="form-control" v-model="mailForm.from_address" placeholder="contato@minhaescola.com.br">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="mail-reply-to">Responder para</label>
            <input id="mail-reply-to" type="email" class="form-control" v-model="mailForm.reply_to" placeholder="suporte@minhaescola.com.br">
          </div>
          <div class="form-group">
            <label for="mail-domain">Domínio de envio</label>
            <input id="mail-domain" class="form-control" v-model="mailForm.email_domain" placeholder="minhaescola.com.br">
            <span class="form-hint">Domínio verificado no Resend. O remetente precisa pertencer a ele.</span>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="mail-api-key">API key do Resend</label>
            <input id="mail-api-key" type="password" class="form-control" v-model="mailApiKey" :placeholder="mailForm.has_credentials ? 'Chave já configurada — deixe vazio para manter' : 're_...'" autocomplete="new-password">
            <span class="form-hint">Criptografada no banco. Use uma chave com permissão de domínio e envio.</span>
          </div>
          <div class="form-group">
            <label for="mail-region">Região</label>
            <select id="mail-region" class="form-control" v-model="mailForm.region">
              <option value="sa-east-1">São Paulo</option>
              <option value="us-east-1">Virgínia</option>
              <option value="eu-west-1">Irlanda</option>
              <option value="ap-northeast-1">Tóquio</option>
            </select>
          </div>
        </div>

        <div class="mail-status-row">
          <span class="status-pill" :class="mailForm.domain_status === 'verified' ? 'active' : 'inactive'">
            {{ mailStatusLabel(mailForm.domain_status) }}
          </span>
          <span class="form-hint">
            {{ mailForm.has_credentials ? 'Credenciais configuradas' : 'Sem credenciais' }} ·
            {{ mailForm.is_ready ? 'Pronto para enviar' : 'Ainda não pronto' }}
          </span>
        </div>

        <div class="form-row mail-actions">
          <button type="button" class="btn btn-outline btn-sm" :disabled="mailBusy" @click="saveMail">Salvar e-mail</button>
          <button type="button" class="btn btn-outline btn-sm" :disabled="mailBusy || !mailForm.has_credentials" @click="validateMail">Validar chave</button>
          <button type="button" class="btn btn-outline btn-sm" :disabled="mailBusy || !mailForm.has_credentials || !mailForm.email_domain" @click="registerMail">Registrar domínio</button>
          <button type="button" class="btn btn-outline btn-sm" :disabled="mailBusy || !mailForm.domain_id" @click="checkMail">Verificar domínio</button>
        </div>

        <div v-if="mailForm.dns_records?.length" class="mail-dns">
          <strong>Registros DNS</strong>
          <div v-for="(record, index) in mailForm.dns_records" :key="index" class="mail-dns-row">
            <span class="mail-dns-type">{{ record.type || record.record }}</span>
            <code>{{ record.name }}</code>
            <code class="mail-dns-value">{{ record.value }}</code>
          </div>
        </div>

        <p v-if="mailForm.last_error" class="mail-error">{{ mailForm.last_error }}</p>

        <div class="mail-test-row">
          <input class="form-control" type="email" v-model="testEmail" placeholder="enviar teste para...">
          <button type="button" class="btn btn-outline btn-sm" :disabled="mailBusy || !mailForm.is_ready" @click="sendTest">Enviar teste</button>
        </div>
      </template>

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
            <tr><th>Nome</th><th>Domínio</th><th>Status</th><th>Criada em</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="company in companies.items" :key="company.id">
              <td><strong>{{ company.name }}</strong></td>
              <td class="admin-muted">{{ company.domain || '—' }}</td>
              <td><span class="status-pill" :class="company.status">{{ statusLabel(company.status) }}</span></td>
              <td class="admin-muted">{{ formatDate(company.created_at) }}</td>
              <td class="admin-actions">
                <button v-if="company.public_store_url" class="btn btn-outline btn-sm" @click="copyPublicLink(company)">Copiar link</button>
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

const mailForm = reactive({
  from_name: '',
  from_address: '',
  reply_to: '',
  email_domain: '',
  region: 'sa-east-1',
  domain_id: null,
  domain_status: 'not_started',
  dns_records: null,
  has_credentials: false,
  is_ready: false,
  last_error: null,
});

const form = reactive({
  name: '',
  slug: '',
  domain: '',
  status: 'active',
  admin_name: '',
  admin_username: '',
  admin_email: '',
  admin_password: '',
  primary_color: DEFAULT_PRIMARY_COLOR,
});

const mailApiKey = ref('');
const testEmail = ref('');
const mailBusy = ref(false);

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

async function copyPublicLink(company) {
  try {
    await navigator.clipboard.writeText(company.public_store_url);
    toast.success('Link da loja copiado.');
  } catch (e) {
    toast.error('Não foi possível copiar o link.');
  }
}

function formatDate(value) {
  if (!value) {
    return '—';
  }

  return new Date(value).toLocaleDateString('pt-BR');
}

function resetForm() {
  form.name = '';
  form.slug = '';
  form.domain = '';
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
  form.slug = company.slug ?? '';
  form.domain = company.domain ?? '';
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

  mailApiKey.value = '';
  testEmail.value = '';

  try {
    applyMailSettings(await companies.fetchMailSettings(company.id));
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar a configuração de e-mail.');
  }
}

function applyMailSettings(data = {}) {
  mailForm.from_name = data.from_name ?? '';
  mailForm.from_address = data.from_address ?? '';
  mailForm.reply_to = data.reply_to ?? '';
  mailForm.email_domain = data.email_domain ?? '';
  mailForm.region = data.region ?? 'sa-east-1';
  mailForm.domain_id = data.domain_id ?? null;
  mailForm.domain_status = data.domain_status ?? 'not_started';
  mailForm.dns_records = Array.isArray(data.dns_records) ? data.dns_records : null;
  mailForm.has_credentials = Boolean(data.has_credentials);
  mailForm.is_ready = Boolean(data.is_ready);
  mailForm.last_error = data.last_error ?? null;
}

function mailStatusLabel(status) {
  return {
    verified: 'Domínio verificado',
    pending: 'Aguardando DNS',
    not_started: 'Domínio não registrado',
    failed: 'Falha na verificação',
  }[status] || 'Não configurado';
}

async function persistMail() {
  if (!editingId.value) {
    return null;
  }

  const payload = {
    from_name: mailForm.from_name.trim() || null,
    from_address: mailForm.from_address.trim() || null,
    reply_to: mailForm.reply_to.trim() || null,
    email_domain: mailForm.email_domain.trim() || null,
    region: mailForm.region,
  };

  if (mailApiKey.value.trim()) {
    payload.credentials = { api_key: mailApiKey.value.trim() };
  }

  const data = await companies.saveMailSettings(editingId.value, payload);
  applyMailSettings(data);
  mailApiKey.value = '';

  return data;
}

async function saveMail() {
  mailBusy.value = true;

  try {
    await persistMail();
    toast.success('Configuração de e-mail salva.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível salvar a configuração de e-mail.');
  } finally {
    mailBusy.value = false;
  }
}

async function validateMail() {
  mailBusy.value = true;

  try {
    await persistMail();
    applyMailSettings(await companies.validateMailCredentials(editingId.value));
    toast.success('Credenciais do Resend validadas.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível validar a chave do Resend.');
  } finally {
    mailBusy.value = false;
  }
}

async function registerMail() {
  mailBusy.value = true;

  try {
    await persistMail();
    applyMailSettings(await companies.registerMailDomain(editingId.value));
    toast.success('Domínio enviado ao Resend. Configure os registros DNS exibidos.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível registrar o domínio.');
  } finally {
    mailBusy.value = false;
  }
}

async function checkMail() {
  mailBusy.value = true;

  try {
    applyMailSettings(await companies.checkMailDomain(editingId.value));
    toast.success('Status do domínio atualizado.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível verificar o domínio.');
  } finally {
    mailBusy.value = false;
  }
}

async function sendTest() {
  const email = testEmail.value.trim() || mailForm.from_address.trim();

  if (!email) {
    toast.error('Informe o e-mail que receberá o teste.');

    return;
  }

  mailBusy.value = true;

  try {
    await persistMail();
    await companies.testMailSettings(editingId.value, email);
    toast.success('E-mail de teste enviado.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível enviar o e-mail de teste.');
  } finally {
    mailBusy.value = false;
  }
}

async function save() {
  saving.value = true;

  try {
    let companyId = editingId.value;

    if (editingId.value) {
      await companies.updateCompany(editingId.value, {
        name: form.name,
        slug: form.slug.trim(),
        domain: form.domain.trim() || null,
        status: form.status,
      });
    } else {
      const created = await companies.createCompany({
        name: form.name,
        ...(form.slug.trim() ? { slug: form.slug.trim() } : {}),
        domain: form.domain.trim() || null,
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
.mail-status-row,.mail-actions,.mail-test-row{display:flex;align-items:center;flex-wrap:wrap;gap:10px;}
.mail-status-row{margin:14px 0 10px;}
.mail-actions{margin-bottom:12px;}
.mail-test-row{margin-top:12px;}
.mail-test-row .form-control{max-width:320px;}
.mail-dns{margin-top:12px;padding:12px;border:1px solid var(--border);border-radius:10px;background:var(--bg-card-alt);}
.mail-dns>strong{display:block;margin-bottom:8px;font-size:12.5px;}
.mail-dns-row{display:flex;align-items:center;gap:8px;padding:6px 0;border-top:1px solid var(--border);font-size:11.5px;}
.mail-dns-row:first-of-type{border-top:0;}
.mail-dns-type{min-width:42px;font-weight:700;color:var(--text-secondary);}
.mail-dns-row code{font-size:11px;word-break:break-all;}
.mail-dns-value{color:var(--text-muted);}
.mail-error{margin-top:10px;font-size:12px;color:var(--danger,#e5484d);}
</style>
