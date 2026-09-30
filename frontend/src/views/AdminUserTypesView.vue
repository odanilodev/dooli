<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Tipos de usuário</h1>
        <p class="page-subtitle">{{ userTypes.items.length }} tipo(s) cadastrado(s)</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">{{ showForm ? 'Cancelar' : 'Novo tipo' }}</button>
    </div>

    <form v-if="showForm" class="panel" @submit.prevent="save">
      <h2 class="panel-title">{{ editingId ? `Editar tipo #${editingId}` : 'Novo tipo de usuário' }}</h2>
      <p class="panel-desc">Menus marcados limitam o que este tipo enxerga. Sem nenhum marcado, o tipo enxerga todos os menus.</p>

      <div class="form-row">
        <div class="form-group">
          <label for="ut-name">Nome</label>
          <input id="ut-name" class="form-control" v-model="form.name" required placeholder="Financeiro">
        </div>
        <div class="form-group">
          <label for="ut-code">Código</label>
          <select id="ut-code" class="form-control" v-model="form.code">
            <option value="">— Sem código —</option>
            <option value="admin">admin</option>
            <option value="instructor">instructor</option>
            <option value="student">student</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="ut-status">Status</label>
          <select id="ut-status" class="form-control" v-model="form.status">
            <option value="active">Ativo</option>
            <option value="inactive">Inativo</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Menus permitidos</label>
        <div v-if="orderedMenus.length" class="permission-box">
          <label v-for="menu in orderedMenus" :key="menu.id" class="permission-item" :style="{ paddingLeft: `${10 + (menu.depth || 0) * 18}px` }">
            <input type="checkbox" :value="menu.id" v-model="form.permission_menu">
            <span>{{ menu.name }}</span>
          </label>
        </div>
        <p v-else class="form-hint">Nenhum menu cadastrado ainda.</p>
      </div>

      <div>
        <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar tipo' }}</button>
      </div>
    </form>

    <template v-if="!showForm">

    <p v-if="userTypes.loading && !userTypes.items.length" class="page-subtitle">Carregando...</p>

    <div v-else-if="userTypes.items.length === 0" class="empty-state">
      <strong>Nenhum tipo de usuário</strong>
      <span>Cadastre o primeiro tipo de usuário.</span>
    </div>

    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>Nome</th><th>Código</th><th>Status</th><th>Menus</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="type in userTypes.items" :key="type.id">
            <td><strong>{{ type.name }}</strong></td>
            <td class="admin-muted">{{ type.code || '—' }}</td>
            <td><span class="status-pill" :class="type.status">{{ statusLabel(type.status) }}</span></td>
            <td class="admin-muted">{{ menuCountLabel(type) }}</td>
            <td class="admin-actions">
              <button class="btn btn-outline btn-sm" @click="openEdit(type)">Editar</button>
              <button class="btn btn-danger btn-sm" :disabled="busyId === type.id" @click="remove(type)">Excluir</button>
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
import { useToast } from '../composables/useToast';
import { useMenusStore } from '../stores/menus';
import { useUserTypesStore } from '../stores/userTypes';

const toast = useToast();
const userTypes = useUserTypesStore();
const menus = useMenusStore();

const showForm = ref(false);
const editingId = ref(null);
const saving = ref(false);
const busyId = ref(null);

const form = reactive({
  name: '',
  code: '',
  status: 'active',
  permission_menu: [],
});

function flatten(nodes) {
  return nodes.flatMap((node) => [node, ...flatten(node.children ?? [])]);
}

const orderedMenus = computed(() => flatten(menus.tree));

function statusLabel(status) {
  return status === 'active' ? 'Ativo' : 'Inativo';
}

function menuCountLabel(type) {
  const count = type.permission_menu?.length ?? 0;

  return count ? `${count} menu(s)` : 'Todos';
}

function resetForm() {
  form.name = '';
  form.code = '';
  form.status = 'active';
  form.permission_menu = [];
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

function openEdit(type) {
  editingId.value = type.id;
  form.name = type.name ?? '';
  form.code = type.code ?? '';
  form.status = type.status ?? 'active';
  form.permission_menu = [...(type.permission_menu ?? [])];
  showForm.value = true;
}

async function save() {
  saving.value = true;

  try {
    await userTypes.saveUserType({
      name: form.name,
      code: form.code || null,
      status: form.status,
      permission_menu: form.permission_menu,
    }, editingId.value);

    toast.success('Tipo salvo.');
    showForm.value = false;
    editingId.value = null;
    resetForm();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível salvar o tipo.');
  } finally {
    saving.value = false;
  }
}

async function remove(type) {
  if (!window.confirm(`Excluir o tipo "${type.name}"?`)) {
    return;
  }

  busyId.value = type.id;

  try {
    await userTypes.removeUserType(type.id);
    toast.success('Tipo excluído.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível excluir o tipo.');
  } finally {
    busyId.value = null;
  }
}

onMounted(async () => {
  await Promise.all([
    userTypes.fetchUserTypes(),
    menus.loaded ? Promise.resolve() : menus.fetchMenus(),
  ]);
});
</script>

<style scoped>
.permission-box {
  display: flex;
  flex-direction: column;
  gap: 2px;
  max-height: 240px;
  overflow-y: auto;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--bg-card-alt);
  padding: 10px;
}
.permission-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 7px 10px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.permission-item:hover {
  background: var(--bg-card);
}
</style>
