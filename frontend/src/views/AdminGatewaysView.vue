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
