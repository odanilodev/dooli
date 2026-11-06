<template>
  <main class="content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Gateways de pagamento</h1>
        <p class="page-subtitle">Credenciais usadas pela loja desta empresa.</p>
      </div>
      <button class="btn btn-primary" @click="toggleForm">{{ showForm ? 'Cancelar' : '+ Novo gateway' }}</button>
    </div>

    <form v-if="showForm" class="panel" @submit.prevent="save">
      <h2 class="panel-title">{{ editingId ? 'Editar gateway' : 'Novo gateway' }}</h2>

      <div class="form-row">
        <div class="form-group">
          <label for="g-name">Nome</label>
          <input id="g-name" class="form-control" v-model="form.name" required placeholder="Mercado Pago">
        </div>
        <div class="form-group">
          <label for="g-code">Código</label>
          <select id="g-code" class="form-control" v-model="form.code">
            <option value="mercadopago">Mercado Pago</option>
            <option value="manual">Manual</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="g-token">Access token</label>
          <input id="g-token" class="form-control" v-model="form.access_token" autocomplete="off" placeholder="APP_USR-...">
          <span class="form-hint">Deixe vazio para manter o token atual.</span>
        </div>
        <div class="form-group">
          <label for="g-public-key">Public Key</label>
          <input id="g-public-key" class="form-control" v-model="form.public_key" autocomplete="off" placeholder="APP_USR-... (ou TEST-...)">
          <span class="form-hint">Usada para o pagamento por cartão direto no site, sem redirecionar ao Mercado Pago. Também pode ficar vazia para manter a atual.</span>
        </div>
        <div class="form-group">
          <label for="g-secret">Segredo do webhook</label>
          <input id="g-secret" class="form-control" v-model="form.webhook_secret" autocomplete="off">
          <span class="form-hint">Também deixe vazio para manter o segredo atual.</span>
        </div>
      </div>

      <div v-if="companySlug" class="form-group">
        <label>URL de webhook de pagamentos</label>
        <input class="form-control" readonly :value="webhookUrl">
        <span class="form-hint">Cadastre esta URL nas notificações de pagamento do Mercado Pago.</span>
      </div>

      <div v-if="companySlug" class="form-group">
        <label>URL de webhook de assinaturas</label>
        <input class="form-control" readonly :value="subscriptionWebhookUrl">
        <span class="form-hint">Use esta URL para notificações de assinatura/preapproval.</span>
      </div>

      <details v-if="form.code === 'mercadopago'" class="gateway-help">
        <summary>Como encontrar as credenciais do Mercado Pago</summary>
        <div class="gateway-help-body">
          <div>
            <strong>Access Token</strong>
            <ol>
              <li>Acesse <a href="https://www.mercadopago.com.br/developers/panel/app" target="_blank" rel="noopener noreferrer">Suas integrações</a> e selecione a aplicação da loja.</li>
              <li>No menu lateral, abra <strong>Credenciais de teste</strong> ou <strong>Credenciais de produção</strong>.</li>
              <li>Copie o <strong>Access Token</strong>. Use o de teste durante a validação e o de produção somente após publicar a loja.</li>
            </ol>
          </div>

          <div>
            <strong>Segredo do webhook</strong>
            <ol>
              <li>Na mesma aplicação, abra <strong>Webhooks → Configurar notificações</strong>.</li>
              <li>Cadastre as URLs de pagamentos e assinaturas exibidas acima.</li>
              <li>Salve e revele a <strong>assinatura secreta</strong> gerada.</li>
            </ol>
          </div>

          <p class="gateway-help-note">
            O Access Token é uma credencial secreta. Não compartilhe esse valor fora do painel de integrações.
          </p>

          <div class="gateway-help-links">
            <a href="https://www.mercadopago.com.br/developers/panel/app" target="_blank" rel="noopener noreferrer">Abrir painel</a>
            <a href="https://www.mercadopago.com.br/developers/pt/docs/your-integrations/credentials" target="_blank" rel="noopener noreferrer">Documentação de credenciais</a>
            <a href="https://www.mercadopago.com.br/developers/pt/docs/your-integrations/notifications/webhooks" target="_blank" rel="noopener noreferrer">Documentação de webhooks</a>
          </div>
        </div>
      </details>

      <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar gateway' }}</button>
    </form>

    <template v-if="!showForm">
      <div v-if="loading" class="empty-state"><strong>Carregando...</strong></div>
      <div v-else-if="!gateways.length" class="empty-state">
        <strong>Nenhum gateway configurado</strong>
        <span>Cadastre o Mercado Pago para vender na loja.</span>
      </div>
      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Gateway</th><th>Código</th><th>Credenciais</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <tr v-for="gateway in gateways" :key="gateway.id">
              <td><strong>{{ gateway.name }}</strong></td>
              <td class="admin-muted">{{ gateway.code }}</td>
              <td class="admin-muted">{{ gateway.has_credentials ? 'Configuradas' : 'Pendentes' }}</td>
              <td><span class="status-pill" :class="gateway.status">{{ gateway.status === 'active' ? 'Ativo' : 'Inativo' }}</span></td>
              <td class="admin-actions">
                <button class="btn btn-outline btn-sm" @click="edit(gateway)">Editar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <section class="panel webhook-log">
        <div class="webhook-log-head">
          <div>
            <h2 class="panel-title">Eventos de webhook</h2>
            <p class="page-subtitle">Avisos de pagamento recebidos do Mercado Pago e o que o sistema fez com cada um.</p>
          </div>
          <button type="button" class="btn btn-outline btn-sm" :disabled="logsLoading" @click="loadLogs">
            {{ logsLoading ? 'Atualizando...' : 'Atualizar' }}
          </button>
        </div>

        <p v-if="!logsLoading && !logs.length" class="form-hint">
          Nenhum evento ainda. Quando o Mercado Pago avisar um pagamento, ele aparece aqui.
        </p>

        <div v-else class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Quando</th><th>Resultado</th><th>Detalhe</th></tr></thead>
            <tbody>
              <tr v-for="log in logs" :key="log.id">
                <td class="admin-muted">{{ formatLogDate(log.created_at) }}</td>
                <td><span class="webhook-pill" :class="`is-${log.action}`">{{ webhookActionLabel(log.action) }}</span></td>
                <td>{{ log.description }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';

const toast = useToast();
const gateways = ref([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const editingId = ref(null);
const companySlug = ref('');

const form = reactive({
  name: 'Mercado Pago',
  code: 'mercadopago',
  status: 'active',
  access_token: '',
  public_key: '',
  webhook_secret: '',
});

const apiBase = computed(() => {
  const base = String(api.defaults.baseURL).replace(/\/$/, '');

  // Base relativa (/api/v1): completa com o domínio atual para colar no Mercado Pago.
  return /^https?:\/\//i.test(base) ? base : `${window.location.origin}${base}`;
});
const webhookUrl = computed(() => companySlug.value
  ? `${apiBase.value}/webhooks/companies/${companySlug.value}/payments/mercadopago`
  : '');
const subscriptionWebhookUrl = computed(() => companySlug.value
  ? `${apiBase.value}/webhooks/companies/${companySlug.value}/subscriptions/mercadopago`
  : '');

const logs = ref([]);
const logsLoading = ref(false);

const WEBHOOK_LABELS = {
  receive: 'Recebido',
  processed: 'Processado',
  rejected: 'Recusado',
  not_found: 'Pagamento não encontrado',
  failed: 'Falhou',
};

function webhookActionLabel(action) {
  return WEBHOOK_LABELS[action] || action;
}

function formatLogDate(value) {
  if (!value) {
    return '';
  }

  try {
    return new Date(value).toLocaleString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
  } catch (e) {
    return '';
  }
}

async function loadLogs() {
  logsLoading.value = true;

  try {
    const { data } = await api.get('/admin/datalogs', { params: { module: 'webhook', per_page: 20 } });
    logs.value = data.data ?? [];
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar os eventos de webhook.');
  } finally {
    logsLoading.value = false;
  }
}

onMounted(async () => {
  await Promise.all([load(), loadCompany(), loadLogs()]);
});

async function load() {
  loading.value = true;

  try {
    const { data } = await api.get('/admin/gateways', { params: { per_page: 50 } });
    gateways.value = data.data ?? [];
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar os gateways.');
  } finally {
    loading.value = false;
  }
}

async function loadCompany() {
  try {
    const { data } = await api.get('/admin/company/public-link');
    companySlug.value = data.data?.slug || '';
  } catch (e) {
    /* optional */
  }
}

function toggleForm() {
  if (showForm.value) {
    showForm.value = false;
    return;
  }

  editingId.value = null;
  Object.assign(form, { name: 'Mercado Pago', code: 'mercadopago', status: 'active', access_token: '', public_key: '', webhook_secret: '' });
  showForm.value = true;
}

function edit(gateway) {
  editingId.value = gateway.id;
  Object.assign(form, {
    name: gateway.name,
    code: gateway.code,
    status: gateway.status,
    access_token: '',
    public_key: '',
    webhook_secret: '',
  });
  showForm.value = true;
}

async function save() {
  saving.value = true;

  try {
    const credentials = {};

    if (form.access_token.trim()) {
      credentials.access_token = form.access_token.trim();
    }

    if (form.public_key.trim()) {
      credentials.public_key = form.public_key.trim();
    }

    if (form.webhook_secret.trim()) {
      credentials.webhook_secret = form.webhook_secret.trim();
    }

    const payload = {
      name: form.name.trim(),
      code: form.code,
      status: form.status,
      ...(Object.keys(credentials).length ? { credentials } : {}),
    };

    if (editingId.value) {
      await api.put(`/admin/gateways/${editingId.value}`, payload);
    } else {
      await api.post('/admin/gateways', payload);
    }

    toast.success('Gateway salvo.');
    showForm.value = false;
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível salvar o gateway.');
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.webhook-log{margin-top:24px;}
.webhook-log-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:14px;}
.webhook-pill{
  display:inline-block;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:800;
  background:var(--bg-card-alt);color:var(--text-secondary);
}
.webhook-pill.is-processed,.webhook-pill.is-receive{background:var(--accent-soft);color:var(--accent);}
.webhook-pill.is-rejected,.webhook-pill.is-failed,.webhook-pill.is-not_found{background:rgba(255,107,107,.14);color:#ff6b6b;}
.gateway-help{
  margin:4px 0 18px;padding:0 14px;
  border:1px solid var(--border);border-radius:12px;
  background:var(--bg-card-alt);
}
.gateway-help summary{
  padding:12px 0;cursor:pointer;
  color:var(--text-primary);font-size:13px;font-weight:800;
}
.gateway-help-body{
  display:flex;flex-direction:column;gap:12px;
  padding:0 0 14px;color:var(--text-secondary);font-size:12.5px;line-height:1.5;
}
.gateway-help-body strong{color:var(--text-primary);}
.gateway-help-body ol{
  display:flex;flex-direction:column;gap:4px;
  margin:6px 0 0;padding-left:18px;
}
.gateway-help-note{color:var(--text-muted);font-size:11.5px;}
.gateway-help-links{display:flex;flex-wrap:wrap;gap:8px 14px;font-weight:700;}
.gateway-help-links a{color:var(--accent);}
.gateway-help-links a:hover{text-decoration:underline;}
</style>
