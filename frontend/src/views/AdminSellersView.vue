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
        <input v-model="filters.search" class="form-control sellers-filter-search" type="search" placeholder="Buscar vendedor...">
        <select v-model="filters.status" class="form-control sellers-filter-status">
          <option value="">Todos os status</option>
          <option value="active">Ativos</option>
          <option value="inactive">Inativos</option>
        </select>
        <button class="btn btn-primary sellers-filter-submit" type="submit">Filtrar</button>
        <button v-if="hasFilters" class="btn btn-outline sellers-filter-clear" type="button" @click="clearFilters">Limpar</button>
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
              <th class="seller-actions-head">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="seller in sellers" :key="seller.id">
              <td>
                <div class="seller-cell">
                  <div class="seller-cell-main">
                    <strong>{{ seller.name }}</strong>
                    <span class="admin-sub">{{ seller.email || 'Sem e-mail' }} · {{ seller.code }}</span>
                  </div>
                  <button
                    v-if="seller.kit_url"
                    class="kit-copy-btn"
                    type="button"
                    title="Copiar link do kit"
                    aria-label="Copiar link do kit"
                    @click="copyKit(seller)"
                  >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.07 0l2.12-2.12a5 5 0 0 0-7.07-7.07L11 5"/><path d="M14 11a5 5 0 0 0-7.07 0L4.81 13.12a5 5 0 0 0 7.07 7.07L13 19"/></svg>
                  </button>
                </div>
              </td>
              <td>{{ Number(seller.commission_percent).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) }}%</td>
              <td><span class="status-pill" :class="seller.status">{{ statusLabel(seller.status) }}</span></td>
              <td>{{ seller.metrics?.students_count ?? 0 }}</td>
              <td>{{ seller.metrics?.paid_orders_count ?? 0 }}</td>
              <td>{{ formatCurrency(seller.metrics?.gross_revenue) }}</td>
              <td>{{ formatCurrency(seller.metrics?.commission_pending) }}</td>
              <td class="seller-actions-cell">
                <button
                  class="seller-actions-btn"
                  :class="{ 'is-open': openMenuSeller?.id === seller.id }"
                  type="button"
                  :disabled="busyId === seller.id"
                  aria-label="Ações do vendedor"
                  aria-haspopup="menu"
                  :aria-expanded="openMenuSeller?.id === seller.id"
                  @click.stop="toggleActions(seller, $event)"
                >
                  <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/></svg>
                </button>
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
    <Teleport to="body">
      <div
        v-if="openMenuSeller"
        class="seller-actions-menu"
        :style="actionsMenuStyle"
        role="menu"
        @click.stop
      >
        <button type="button" role="menuitem" @click="runAction('details')">Detalhes</button>
        <button type="button" role="menuitem" @click="runAction('edit')">Editar</button>
        <button type="button" role="menuitem" :disabled="!openMenuSeller.kit_url" @click="runAction('open-kit')">Abrir kit</button>
        <div class="seller-menu-sep"></div>
        <button type="button" role="menuitem" class="danger" :disabled="busyId === openMenuSeller.id" @click="runAction('delete')">Excluir</button>
      </div>
    </Teleport>
  </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
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
const openMenuSeller = ref(null);
const actionsMenuPosition = ref({ top: 0, left: 0 });
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
const actionsMenuStyle = computed(() => ({
  top: `${actionsMenuPosition.value.top}px`,
  left: `${actionsMenuPosition.value.left}px`,
}));

onMounted(() => {
  load();
  loadUsers('');
  document.addEventListener('click', closeActionsMenu);
  document.addEventListener('keydown', handleGlobalKeydown);
  window.addEventListener('scroll', closeActionsMenu, true);
  window.addEventListener('resize', closeActionsMenu);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', closeActionsMenu);
  document.removeEventListener('keydown', handleGlobalKeydown);
  window.removeEventListener('scroll', closeActionsMenu, true);
  window.removeEventListener('resize', closeActionsMenu);
});

async function load() {
  closeActionsMenu();
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
  closeActionsMenu();
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
  closeActionsMenu();
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
  closeActionsMenu();

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

function toggleActions(seller, event) {
  if (openMenuSeller.value?.id === seller.id) {
    closeActionsMenu();

    return;
  }

  const rect = event.currentTarget.getBoundingClientRect();
  const width = 190;
  const height = 190;
  const margin = 12;
  let left = rect.right - width;
  let top = rect.bottom + 6;
  const maxLeft = Math.max(margin, window.innerWidth - width - margin);

  left = Math.min(Math.max(left, margin), maxLeft);

  if (top + height > window.innerHeight - margin) {
    top = Math.max(margin, rect.top - height - 6);
  }

  actionsMenuPosition.value = { top, left };
  openMenuSeller.value = seller;
}

function closeActionsMenu() {
  openMenuSeller.value = null;
}

function handleGlobalKeydown(event) {
  if (event.key === 'Escape') {
    closeActionsMenu();
  }
}

function runAction(action) {
  const seller = openMenuSeller.value;

  closeActionsMenu();

  if (!seller) {
    return;
  }

  if (action === 'details') {
    openDetail(seller);

    return;
  }

  if (action === 'edit') {
    openEdit(seller);

    return;
  }

  if (action === 'open-kit') {
    openKit(seller);

    return;
  }

  if (action === 'delete') {
    remove(seller);
  }
}

function openDetail(seller) {
  closeActionsMenu();
  selectedSellerId.value = seller.id;
  drawerOpen.value = true;
}

async function copyKit(seller) {
  closeActionsMenu();

  if (!seller.kit_url) {
    return;
  }

  try {
    await copyText(seller.kit_url);
    toast.success('Link do kit copiado.');
  } catch (e) {
    toast.error('Não foi possível copiar o link.');
  }
}

async function copyText(value) {
  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(value);

    return;
  }

  const textarea = document.createElement('textarea');
  textarea.value = value;
  textarea.setAttribute('readonly', '');
  textarea.style.position = 'fixed';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.select();
  document.execCommand('copy');
  textarea.remove();
}

function openKit(seller) {
  closeActionsMenu();
  window.open(seller.kit_url, '_blank', 'noopener');
}

function statusLabel(value) {
  return value === 'active' ? 'Ativo' : 'Inativo';
}
</script>

<style scoped>
.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;}

.sellers-filters{
  display:grid;
  grid-template-columns:minmax(0,1fr) 170px max-content max-content;
  align-items:center;
  gap:8px;
  padding:10px 12px;
  margin-bottom:18px;
  border:1px solid var(--border);
  border-radius:14px;
  background:var(--bg-card);
}
.sellers-filters .form-control,
.sellers-filters .btn{height:42px;box-sizing:border-box;}
.sellers-filters .btn{padding:0 18px;font-size:13px;}
.sellers-filter-search{min-width:0;}
.sellers-filter-status{width:170px;}

.seller-cell{display:flex;align-items:center;gap:10px;min-width:0;}
.seller-cell-main{display:flex;flex-direction:column;min-width:0;}
.kit-copy-btn{
  display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;
  width:30px;height:30px;padding:0;border-radius:9px;cursor:pointer;
  background:transparent;border:1.5px solid var(--border);color:var(--text-muted);
  transition:border-color .14s ease,color .14s ease,background .14s ease;
}
.kit-copy-btn:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-soft);}
.kit-copy-btn:disabled{opacity:.45;cursor:default;}

.seller-actions-head{width:52px;text-align:right;}
.seller-actions-cell{width:52px;text-align:right;}
.seller-actions-btn{
  display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;padding:0;
  border-radius:9px;border:1.5px solid transparent;background:transparent;color:var(--text-muted);
  cursor:pointer;transition:border-color .14s ease,color .14s ease,background .14s ease;
}
.seller-actions-btn:hover,.seller-actions-btn.is-open{border-color:var(--border);background:var(--bg-card-alt);color:var(--text-primary);}
.seller-actions-btn:disabled{opacity:.45;cursor:wait;}

.seller-actions-menu{
  position:fixed;z-index:120;min-width:180px;max-width:calc(100vw - 24px);padding:6px;
  display:flex;flex-direction:column;gap:2px;
  background:var(--bg-card);border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);
}
.seller-actions-menu button{
  text-align:left;background:none;border:none;color:var(--text-secondary);font-family:inherit;
  font-size:12.5px;font-weight:600;padding:8px 10px;border-radius:8px;cursor:pointer;white-space:nowrap;
}
.seller-actions-menu button:hover{background:var(--bg-card-alt);color:var(--text-primary);}
.seller-actions-menu button:disabled{opacity:.45;cursor:wait;}
.seller-actions-menu button.danger{color:#ef4444;}
.seller-actions-menu button.danger:hover{background:rgba(239,68,68,.12);}
.seller-menu-sep{height:1px;background:var(--border);margin:4px 2px;}

@media(max-width:640px){
  .sellers-filters{display:flex;flex-wrap:wrap;}
  .sellers-filter-search{flex:1 1 100%;}
  .sellers-filter-status{flex:1 1 140px;width:auto;}
  .sellers-filters .btn{flex:0 0 auto;}
}
</style>
