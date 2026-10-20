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
  webhook_secret: '',
});

const apiBase = computed(() => String(api.defaults.baseURL).replace(/\/$/, ''));
const webhookUrl = computed(() => companySlug.value
  ? `${apiBase.value}/webhooks/companies/${companySlug.value}/payments/mercadopago`
  : '');
const subscriptionWebhookUrl = computed(() => companySlug.value
  ? `${apiBase.value}/webhooks/companies/${companySlug.value}/subscriptions/mercadopago`
  : '');

onMounted(async () => {
  await Promise.all([load(), loadCompany()]);
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
  Object.assign(form, { name: 'Mercado Pago', code: 'mercadopago', status: 'active', access_token: '', webhook_secret: '' });
  showForm.value = true;
}

function edit(gateway) {
  editingId.value = gateway.id;
  Object.assign(form, {
    name: gateway.name,
    code: gateway.code,
    status: gateway.status,
    access_token: '',
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
