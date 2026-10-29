<template>
  <main class="content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Vendedores</h1>
        <p class="page-subtitle">Links de indicação, vendas e comissões por vendedor.</p>
      </div>
      <div class="admin-head-actions">
        <router-link :to="{ name: 'admin-comissoes' }" class="btn btn-outline">Comissões</router-link>
        <button class="btn btn-primary" type="button" @click="toggleForm">{{ showForm ? 'Cancelar' : '+ Novo vendedor' }}</button>
      </div>
    </div>

    <form v-if="showForm" class="panel" @submit.prevent="save">
      <h2 class="panel-title">{{ editingId ? `Editar vendedor #${editingId}` : 'Novo vendedor' }}</h2>
      <p class="panel-desc">O vendedor não precisa de login. Ele recebe um kit de links para compartilhar.</p>

      <div class="form-row">
        <div class="form-group">
          <label for="seller-name">Nome</label>
          <input id="seller-name" v-model="form.name" class="form-control" required placeholder="Nome do vendedor">
        </div>
        <div class="form-group">
          <label for="seller-status">Status</label>
          <select id="seller-status" v-model="form.status" class="form-control">
            <option value="active">Ativo</option>
            <option value="inactive">Inativo</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="seller-email">E-mail</label>
          <input id="seller-email" v-model="form.email" class="form-control" type="email" placeholder="vendedor@email.com">
        </div>
        <div class="form-group">
          <label for="seller-phone">Telefone</label>
          <input id="seller-phone" v-model="form.phone" class="form-control" type="text" placeholder="(11) 99999-9999">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="seller-code">Código de indicação</label>
          <input id="seller-code" v-model="form.code" class="form-control" placeholder="gerado pelo nome quando vazio">
          <span class="form-hint">Aparece nos links como <code>?ref=codigo</code>. Alterar o código invalida links antigos.</span>
        </div>
        <div class="form-group">
          <label for="seller-commission">Comissão (%)</label>
          <input id="seller-commission" v-model="form.commission_percent" class="form-control" type="number" min="0" max="100" step="0.01" required>
        </div>
      </div>

      <div class="form-group">
        <label>Usuário vinculado (opcional)</label>
        <SearchSelect
          v-model="form.user_id"
          :options="userOptions"
          :loading="usersLoading"
          remote
          allow-clear
          clear-label="Nenhum usuário"
          placeholder="Buscar aluno ou funcionário..."
          @search="loadUsers"
        />
        <span class="form-hint">O vínculo identifica a pessoa, mas não concede acesso ao painel.</span>
      </div>

      <div class="form-group">
        <label for="seller-notes">Observações</label>
        <textarea id="seller-notes" v-model="form.notes" class="form-control" rows="3" placeholder="Informações internas sobre o vendedor"></textarea>
      </div>

      <div class="admin-head-actions">
        <button class="btn btn-primary" type="submit" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar vendedor' }}</button>
        <button class="btn btn-outline" type="button" :disabled="saving" @click="toggleForm">Cancelar</button>
      </div>
    </form>

    <template v-if="!showForm">
      <form class="sellers-filters" @submit.prevent="reload">
        <input v-model="filters.search" class="form-control" type="search" placeholder="Buscar por nome, e-mail ou código">
        <select v-model="filters.status" class="form-control">
          <option value="">Todos os status</option>
          <option value="active">Ativos</option>
          <option value="inactive">Inativos</option>
        </select>
        <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
        <button v-if="hasFilters" class="btn btn-outline btn-sm" type="button" @click="clearFilters">Limpar</button>
      </form>

      <div v-if="loading" class="empty-state"><strong>Carregando...</strong></div>
      <div v-else-if="!sellers.length" class="empty-state">
        <strong>Nenhum vendedor</strong>
        <span>Crie o primeiro vendedor para gerar links de indicação.</span>
      </div>
      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Vendedor</th>
              <th>Comissão</th>
              <th>Status</th>
              <th>Alunos</th>
              <th>Pedidos</th>
              <th>Faturamento</th>
              <th>A pagar</th>
              <th>Kit</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="seller in sellers" :key="seller.id">
              <td>
                <strong>{{ seller.name }}</strong>
                <span class="admin-sub">{{ seller.email || 'Sem e-mail' }} · {{ seller.code }}</span>
              </td>
              <td>{{ Number(seller.commission_percent).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) }}%</td>
              <td><span class="status-pill" :class="seller.status">{{ statusLabel(seller.status) }}</span></td>
              <td>{{ seller.metrics?.students_count ?? 0 }}</td>
              <td>{{ seller.metrics?.paid_orders_count ?? 0 }}</td>
              <td>{{ formatCurrency(seller.metrics?.gross_revenue) }}</td>
              <td>{{ formatCurrency(seller.metrics?.commission_pending) }}</td>
              <td class="admin-actions">
                <button class="btn btn-outline btn-sm" type="button" :disabled="!seller.kit_url" @click="copyKit(seller)">Copiar</button>
                <button class="btn btn-outline btn-sm" type="button" :disabled="!seller.kit_url" @click="openKit(seller)">Abrir</button>
              </td>
              <td class="admin-actions">
                <button class="btn btn-outline btn-sm" type="button" @click="openDetail(seller)">Detalhes</button>
                <button class="btn btn-outline btn-sm" type="button" @click="openEdit(seller)">Editar</button>
                <button class="btn btn-danger btn-sm" type="button" :disabled="busyId === seller.id" @click="remove(seller)">Excluir</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="meta.last_page > 1" class="admin-pager">
        <button class="btn btn-outline btn-sm" type="button" :disabled="meta.current_page <= 1" @click="go(meta.current_page - 1)">Anterior</button>
        <span class="admin-muted">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <button class="btn btn-outline btn-sm" type="button" :disabled="meta.current_page >= meta.last_page" @click="go(meta.current_page + 1)">Próxima</button>
      </div>
    </template>

    <SellerDetailDrawer
      :open="drawerOpen"
      :seller-id="selectedSellerId"
      @close="drawerOpen = false"
      @updated="load"
    />
  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';
import { formatCurrency } from '../utils/format';
import SearchSelect from '../components/admin/SearchSelect.vue';
import SellerDetailDrawer from '../components/admin/SellerDetailDrawer.vue';

const toast = useToast();
const sellers = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(true);
const saving = ref(false);
const busyId = ref(null);
const showForm = ref(false);
const editingId = ref(null);
const drawerOpen = ref(false);
const selectedSellerId = ref(null);
const userOptions = ref([]);
const usersLoading = ref(false);
const filters = reactive({ search: '', status: '' });
const form = reactive({
  name: '',
  email: '',
  phone: '',
  code: '',
  commission_percent: 0,
  status: 'active',
  user_id: '',
  notes: '',
});

const hasFilters = computed(() => Boolean(filters.search || filters.status));

onMounted(() => {
  load();
  loadUsers('');
});

async function load() {
  loading.value = true;

  try {
    const { data } = await api.get('/admin/sellers', {
      params: {
        page: meta.value.current_page,
        per_page: 20,
        search: filters.search || undefined,
        status: filters.status || undefined,
      },
    });

    sellers.value = data.data ?? [];
    meta.value = data.meta ?? meta.value;
  } finally {
    loading.value = false;
  }
}

function reload() {
  meta.value.current_page = 1;
  load();
}

function go(page) {
  meta.value.current_page = page;
  load();
}

function clearFilters() {
  filters.search = '';
  filters.status = '';
  reload();
}

async function loadUsers(search = '') {
  usersLoading.value = true;

  try {
    const { data } = await api.get('/admin/users', {
      params: { search: search || undefined, per_page: 30 },
    });

    const rows = (data.data ?? []).map((user) => ({
      value: user.id,
      label: user.name,
      sub: user.email,
    }));

    userOptions.value = rows;
  } catch (e) {
    userOptions.value = [];
  } finally {
    usersLoading.value = false;
  }
}

function toggleForm() {
  if (showForm.value) {
    showForm.value = false;

    return;
  }

  openCreate();
}

function openCreate() {
  editingId.value = null;
  Object.assign(form, {
    name: '',
    email: '',
    phone: '',
    code: '',
    commission_percent: 0,
    status: 'active',
    user_id: '',
    notes: '',
  });
  showForm.value = true;
}

function openEdit(seller) {
  editingId.value = seller.id;
  Object.assign(form, {
    name: seller.name || '',
    email: seller.email || '',
    phone: seller.phone || '',
    code: seller.code || '',
    commission_percent: Number(seller.commission_percent || 0),
    status: seller.status || 'active',
    user_id: seller.user?.id || '',
    notes: seller.notes || '',
  });

  if (seller.user?.id && !userOptions.value.some((option) => String(option.value) === String(seller.user.id))) {
    userOptions.value = [
      { value: seller.user.id, label: seller.user.name, sub: seller.user.email },
      ...userOptions.value,
    ];
  }

  showForm.value = true;
}

async function save() {
  saving.value = true;

  try {
    const payload = {
      name: form.name,
      email: form.email || null,
      phone: form.phone || null,
      code: form.code || null,
      commission_percent: Number(form.commission_percent || 0),
      status: form.status,
      user_id: form.user_id || null,
      notes: form.notes || null,
    };

    if (editingId.value) {
      await api.put(`/admin/sellers/${editingId.value}`, payload);
      toast.success('Vendedor atualizado.');
    } else {
      await api.post('/admin/sellers', payload);
      toast.success('Vendedor criado.');
    }

    showForm.value = false;
    await load();
  } catch (e) {
    const firstError = e?.errors ? Object.values(e.errors).flat()[0] : null;
    toast.error(firstError || e?.message || 'Não foi possível salvar o vendedor.');
  } finally {
    saving.value = false;
  }
}

async function remove(seller) {
  if (!window.confirm(`Excluir o vendedor ${seller.name}?`)) {
    return;
  }

  busyId.value = seller.id;

  try {
    await api.delete(`/admin/sellers/${seller.id}`);
    toast.success('Vendedor excluído.');
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível excluir o vendedor.');
  } finally {
    busyId.value = null;
  }
}

function openDetail(seller) {
  selectedSellerId.value = seller.id;
  drawerOpen.value = true;
}

async function copyKit(seller) {
  try {
    await navigator.clipboard.writeText(seller.kit_url);
    toast.success('Link do kit copiado.');
  } catch (e) {
    toast.error('Não foi possível copiar o link.');
  }
}

function openKit(seller) {
  window.open(seller.kit_url, '_blank', 'noopener');
}

function statusLabel(value) {
  return value === 'active' ? 'Ativo' : 'Inativo';
}
</script>

<style scoped>
.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.sellers-filters{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:14px;margin-bottom:18px;border:1px solid var(--border);border-radius:14px;background:var(--bg-card);}
.sellers-filters input[type="search"]{flex:1 1 260px;}
.sellers-filters .form-control{min-width:150px;}
.sellers-filters .btn{flex-shrink:0;}
</style>
