<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Usuários</h1>
        <p class="page-subtitle">{{ users.meta.total ?? users.items.length }} usuário(s) cadastrado(s)</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">{{ showForm ? 'Cancelar' : 'Novo usuário' }}</button>
    </div>

    <form v-if="showForm" class="panel" @submit.prevent="save">
      <h2 class="panel-title">{{ editingId ? `Editar usuário #${editingId}` : 'Novo usuário' }}</h2>
      <p class="panel-desc">
        {{ editingId ? 'Deixe a senha em branco para manter a atual.' : 'A senha é obrigatória no cadastro.' }}
      </p>

      <div class="form-row">
        <div class="form-group">
          <label for="u-name">Nome</label>
          <input id="u-name" class="form-control" v-model="form.name" required>
        </div>
        <div class="form-group">
          <label for="u-username">Usuário (login)</label>
          <input id="u-username" class="form-control" v-model="form.username" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="u-email">E-mail</label>
          <input id="u-email" type="email" class="form-control" v-model="form.email" required>
        </div>
        <div class="form-group">
          <label for="u-password">Senha</label>
          <input
            id="u-password"
            type="password"
            class="form-control"
            v-model="form.password"
            :required="!editingId"
            autocomplete="new-password"
          >
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="u-type">Tipo de usuário</label>
          <select id="u-type" class="form-control" v-model="form.user_type_id" required>
            <option value="">— Selecione —</option>
            <option v-for="type in userTypes.items" :key="type.id" :value="type.id">{{ type.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label for="u-status">Status</label>
          <select id="u-status" class="form-control" v-model="form.status">
            <option value="active">Ativo</option>
            <option value="inactive">Inativo</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="u-phone">Telefone</label>
          <input id="u-phone" class="form-control" v-model="form.phone">
        </div>
        <div class="form-group">
          <label for="u-image">Imagem (URL)</label>
          <input id="u-image" class="form-control" v-model="form.image" placeholder="https://...">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="u-city">Cidade</label>
          <input id="u-city" class="form-control" v-model="form.city">
        </div>
        <div class="form-group">
          <label for="u-address">Endereço</label>
          <input id="u-address" class="form-control" v-model="form.address">
        </div>
      </div>

      <div>
        <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar usuário' }}</button>
      </div>
    </form>

    <template v-if="!showForm">

    <div class="catalog-toolbar">
      <form class="filters" @submit.prevent="applyFilters">
        <input class="form-control filter-search" v-model="search" placeholder="Buscar por nome, e-mail ou login...">
        <select class="form-control filter-select" v-model="typeFilter" @change="applyFilters">
          <option value="">Todos os tipos</option>
          <option v-for="type in userTypes.items" :key="type.id" :value="type.id">{{ type.name }}</option>
        </select>
        <select class="form-control filter-select" v-model="statusFilter" @change="applyFilters">
          <option value="">Todos os status</option>
          <option value="active">Ativos</option>
          <option value="inactive">Inativos</option>
        </select>
        <button class="btn btn-outline btn-sm" type="submit">Buscar</button>
      </form>
    </div>

    <div v-if="bulkCount" class="bulk-bar">
      <strong>{{ bulkCount }} usuário(s) selecionado(s)</strong>
      <div class="bulk-bar-actions">
        <button type="button" class="btn btn-outline btn-sm" :disabled="bulkBusy" @click="openPicker">Adicionar a turma(s)</button>
        <button type="button" class="btn btn-outline btn-sm" :disabled="bulkBusy" @click="bulkStatus('activate')">Ativar</button>
        <button type="button" class="btn btn-outline btn-sm" :disabled="bulkBusy" @click="bulkStatus('deactivate')">Inativar</button>
        <button type="button" class="btn btn-danger btn-sm" :disabled="bulkBusy" @click="bulkDelete">Excluir</button>
        <button type="button" class="btn btn-outline btn-sm" :disabled="bulkBusy" @click="clearSelection">Limpar</button>
      </div>
    </div>

    <p v-if="users.loading" class="page-subtitle">Carregando...</p>

    <div v-else-if="users.items.length === 0" class="empty-state">
      <strong>Nenhum usuário encontrado</strong>
      <span>Cadastre o primeiro usuário.</span>
    </div>

    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th class="bulk-col">
              <input
                class="admin-check"
                type="checkbox"
                :checked="isAllSelected(pageIds)"
                :indeterminate.prop="isPartial(pageIds)"
                aria-label="Selecionar todos"
                @change="toggleAll(pageIds, $event.target.checked)"
              >
            </th>
            <th>Usuário</th><th>E-mail</th><th>Tipo</th><th>Status</th><th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users.items" :key="user.id">
            <td class="bulk-col">
              <input
                class="admin-check"
                type="checkbox"
                :checked="isSelected(user.id)"
                :aria-label="`Selecionar ${user.name}`"
                @change="toggle(user.id)"
              >
            </td>
            <td>
              <span class="user-cell">
                <span class="user-avatar">
                  <img v-if="user.image" :src="user.image" :alt="user.name">
                  <span v-else>{{ initials(user.name) }}</span>
                </span>
                <span>
                  <strong>{{ user.name }}</strong>
                  <span class="admin-sub">{{ user.username }}</span>
                </span>
              </span>
            </td>
            <td class="admin-muted">{{ user.email }}</td>
            <td class="admin-muted">{{ user.user_type?.name || '—' }}</td>
            <td><span class="status-pill" :class="user.status">{{ statusLabel(user.status) }}</span></td>
            <td class="admin-actions">
              <button class="btn btn-outline btn-sm" @click="openEdit(user)">Editar</button>
              <button
                class="btn btn-danger btn-sm"
                :disabled="busyId === user.id || user.id === auth.user?.id"
                :title="user.id === auth.user?.id ? 'Você não pode excluir a si mesmo' : ''"
                @click="remove(user)"
              >Excluir</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="users.meta.last_page > 1" class="admin-pager">
      <button class="btn btn-outline btn-sm" :disabled="users.meta.current_page <= 1" @click="goPage(users.meta.current_page - 1)">Anterior</button>
      <span class="admin-muted">Página {{ users.meta.current_page }} de {{ users.meta.last_page }}</span>
      <button class="btn btn-outline btn-sm" :disabled="users.meta.current_page >= users.meta.last_page" @click="goPage(users.meta.current_page + 1)">Próxima</button>
    </div>

    </template>

    <BulkPickerModal
      :open="picker.open"
      title="Adicionar a turmas"
      label="Turma"
      placeholder="Buscar turma..."
      confirm-label="Adicionar"
      search-endpoint="/admin/teams"
      :map-results="mapPickerResult"
      @close="picker.open = false"
      @confirm="confirmPicker"
    />

  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';
import { useBulkSelection } from '../composables/useBulkSelection';
import { useAuthStore } from '../stores/auth';
import { useUsersStore } from '../stores/users';
import { useUserTypesStore } from '../stores/userTypes';
import BulkPickerModal from '../components/admin/BulkPickerModal.vue';

const toast = useToast();
const auth = useAuthStore();
const users = useUsersStore();
const userTypes = useUserTypesStore();

const showForm = ref(false);
const editingId = ref(null);
const saving = ref(false);
const busyId = ref(null);

const { selectedIds, count: bulkCount, isSelected, toggle, toggleAll, isAllSelected, isPartial, clear: clearSelection } = useBulkSelection();
const bulkBusy = ref(false);
const picker = reactive({ open: false });
const pageIds = computed(() => users.items.map((user) => user.id));

const search = ref('');
const typeFilter = ref('');
const statusFilter = ref('');

const form = reactive({
  name: '',
  username: '',
  email: '',
  password: '',
  user_type_id: '',
  status: 'active',
  image: '',
  phone: '',
  address: '',
  city: '',
});

function resetForm() {
  form.name = '';
  form.username = '';
  form.email = '';
  form.password = '';
  form.user_type_id = '';
  form.status = 'active';
  form.image = '';
  form.phone = '';
  form.address = '';
  form.city = '';
}

function statusLabel(status) {
  return status === 'active' ? 'Ativo' : 'Inativo';
}

function initials(name) {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean);

  if (!parts.length) {
    return '?';
  }

  return ((parts[0][0] || '') + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
}

function load(page = 1) {
  return users.fetchUsers({
    page,
    per_page: 15,
    search: search.value.trim() || undefined,
    user_type_id: typeFilter.value || undefined,
    status: statusFilter.value || undefined,
  });
}

function applyFilters() {
  load(1);
}

function goPage(page) {
  load(page);
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

function openEdit(user) {
  editingId.value = user.id;
  form.name = user.name ?? '';
  form.username = user.username ?? '';
  form.email = user.email ?? '';
  form.password = '';
  form.user_type_id = user.user_type?.id ?? '';
  form.status = user.status ?? 'active';
  form.image = user.image ?? '';
  form.phone = user.phone ?? '';
  form.address = user.address ?? '';
  form.city = user.city ?? '';
  showForm.value = true;
}

async function save() {
  saving.value = true;

  try {
    const payload = {
      name: form.name,
      username: form.username,
      email: form.email,
      user_type_id: Number(form.user_type_id),
      status: form.status,
      image: form.image || null,
      phone: form.phone || null,
      address: form.address || null,
      city: form.city || null,
    };

    if (form.password?.trim()) {
      payload.password = form.password.trim();
    }

    await users.saveUser(payload, editingId.value);

    toast.success('Usuário salvo.');
    showForm.value = false;
    editingId.value = null;
    resetForm();
    await load(users.meta.current_page || 1);
  } catch (e) {
    toast.error(e?.message || 'Não foi possível salvar o usuário.');
  } finally {
    saving.value = false;
  }
}

async function remove(user) {
  if (!window.confirm(`Excluir o usuário "${user.name}"?`)) {
    return;
  }

  busyId.value = user.id;

  try {
    await users.removeUser(user.id);
    toast.success('Usuário excluído.');
    await load(1);
  } catch (e) {
    toast.error(e?.message || 'Não foi possível excluir o usuário.');
  } finally {
    busyId.value = null;
  }
}

function openPicker() {
  picker.open = true;
}

function mapPickerResult(item) {
  return { value: item.id, label: item.name, sub: item.status === 'active' ? 'Ativa' : 'Inativa' };
}

function reportSummary(summary) {
  const processed = summary?.processed ?? 0;
  const failed = summary?.failed ?? [];

  if (failed.length) {
    toast.error(`${processed} aplicado(s), ${failed.length} falha(s).`);
    return;
  }

  toast.success(`${processed} aplicado(s).`);
}

async function runBulk(payload) {
  bulkBusy.value = true;

  try {
    const { data } = await api.post('/admin/users/bulk', payload);
    reportSummary(data.data);
    await load(users.meta.current_page || 1);
    clearSelection();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível concluir a ação.');
  } finally {
    bulkBusy.value = false;
  }
}

function confirmPicker(items) {
  const teamIds = items.map((item) => item.value);
  picker.open = false;

  return runBulk({ action: 'add-to-teams', user_ids: selectedIds.value, team_ids: teamIds });
}

function bulkStatus(action) {
  return runBulk({ action, user_ids: selectedIds.value });
}

function bulkDelete() {
  if (!window.confirm(`Excluir ${bulkCount.value} usuário(s) selecionado(s)?`)) {
    return;
  }

  return runBulk({ action: 'delete', user_ids: selectedIds.value });
}

onMounted(async () => {
  await userTypes.fetchUserTypes();
  await load(1);
});
</script>

<style scoped>
.filters {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  width: 100%;
}
.filter-search {
  flex: 1 1 260px;
  min-width: 200px;
}
.filter-select {
  flex: 0 0 auto;
  width: auto;
  min-width: 170px;
}
.user-cell {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}
.user-avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  flex-shrink: 0;
  overflow: hidden;
  background: var(--accent-soft);
  color: var(--accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 800;
}
.user-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.bulk-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin: 12px 0;
  padding: 10px 14px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--bg-card-alt);
}
.bulk-bar strong {
  font-size: 13px;
}
.bulk-bar-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.bulk-col {
  width: 44px;
  text-align: center;
  vertical-align: middle;
}
</style>
