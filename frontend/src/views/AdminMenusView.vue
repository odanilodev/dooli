<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Menus</h1>
        <p class="page-subtitle">{{ menus.items.length }} item(ns) — controlam o menu lateral do administrador.</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">{{ showForm ? 'Cancelar' : 'Novo item' }}</button>
    </div>

    <form v-if="showForm" class="panel" @submit.prevent="save">
      <h2 class="panel-title">{{ editingId ? `Editar item #${editingId}` : 'Novo item' }}</h2>
      <p class="panel-desc">Seção (marcação) mostra os filhos sempre visíveis; Dropdown abre/fecha os filhos; Link é um item simples.</p>

      <div class="form-row">
        <div class="form-group">
          <label for="m-name">Nome</label>
          <input id="m-name" class="form-control" v-model="form.name" required placeholder="Relatórios">
        </div>
        <div class="form-group">
          <label for="m-type">Tipo</label>
          <select id="m-type" class="form-control" v-model="form.type">
            <option value="link">Link (item simples)</option>
            <option value="section">Seção (marcação)</option>
            <option value="dropdown">Dropdown (abre/fecha)</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="m-parent">Menu pai</label>
          <select id="m-parent" class="form-control" v-model="form.sub">
            <option value="">— Item de topo —</option>
            <option v-for="option in parentOptions" :key="option.id" :value="String(option.id)">{{ '— '.repeat(option.depth || 0) }}{{ option.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label for="m-sort">Ordem</label>
          <input id="m-sort" type="number" min="0" class="form-control" v-model="form.sort_order">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="m-link">Link (rota)</label>
          <input id="m-link" class="form-control" v-model="form.link" placeholder="/admin/relatorios">
        </div>
        <div class="form-group">
          <label for="m-icon">Ícone</label>
          <select id="m-icon" class="form-control" v-model="form.icon">
            <option value="">— Sem ícone —</option>
            <option v-for="icon in iconOptions" :key="icon" :value="icon">{{ icon }}</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Pré-visualização</label>
        <span class="menu-preview">
          <NavIcon :name="form.icon" />
          <span>{{ form.name || 'Nome do item' }}</span>
        </span>
      </div>

      <div>
        <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar item' }}</button>
      </div>
    </form>

    <template v-if="!showForm">

    <p v-if="menus.loading && !menus.items.length" class="page-subtitle">Carregando...</p>

    <div v-else-if="menus.items.length === 0" class="empty-state">
      <strong>Nenhum item de menu</strong>
      <span>Cadastre o primeiro item do menu do administrador.</span>
    </div>

    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>Item</th><th>Tipo</th><th>Pai</th><th>Link</th><th>Ordem</th><th></th></tr>
        </thead>
        <tbody>
          <tr
            v-for="menu in orderedMenus"
            :key="menu.id"
            :class="{ 'menu-group-row': isGroup(menu), 'menu-child-row': Boolean(menu.sub) }"
          >
            <td>
              <span
                class="menu-name"
                :class="{ child: Boolean(menu.sub) }"
                :style="{ paddingLeft: `${8 + (menu.depth || 0) * 18}px` }"
              >
                <NavIcon :name="menu.icon" />
                <span>{{ menu.name }}</span>
              </span>
            </td>
            <td><span class="type-pill" :class="`type-${menu.type || 'link'}`">{{ typeLabel(menu.type) }}</span></td>
            <td class="admin-muted">{{ parentName(menu.sub) }}</td>
            <td class="admin-muted">{{ menu.link || '—' }}</td>
            <td class="admin-muted">{{ menu.sort_order }}</td>
            <td class="admin-actions">
              <button class="btn btn-outline btn-sm" @click="openEdit(menu)">Editar</button>
              <button class="btn btn-danger btn-sm" :disabled="busyId === menu.id" @click="remove(menu)">Excluir</button>
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
import NavIcon from '../components/NavIcon.vue';

const toast = useToast();
const menus = useMenusStore();

const iconOptions = [
  'dashboard', 'content', 'modules', 'live', 'teams', 'report', 'videos', 'documents', 'attachments',
  'banners', 'video-platforms', 'menu', 'users', 'user-types', 'profile', 'settings', 'home',
];

const showForm = ref(false);
const editingId = ref(null);
const saving = ref(false);
const busyId = ref(null);

const form = reactive({
  name: '',
  type: 'link',
  sub: '',
  link: '',
  icon: '',
  sort_order: 0,
});

const TYPE_LABELS = {
  link: 'Link',
  section: 'Seção',
  dropdown: 'Dropdown',
};

function flatten(nodes) {
  return nodes.flatMap((node) => [node, ...flatten(node.children ?? [])]);
}

const orderedMenus = computed(() => flatten(menus.tree));

function descendantIds(rootId) {
  const byParent = new Map();

  menus.items.forEach((menu) => {
    if (!menu.sub) {
      return;
    }

    const key = String(menu.sub);
    byParent.set(key, [...(byParent.get(key) ?? []), menu]);
  });

  const result = new Set();
  const stack = [...(byParent.get(String(rootId)) ?? [])];

  while (stack.length) {
    const node = stack.pop();

    if (result.has(String(node.id))) {
      continue;
    }

    result.add(String(node.id));
    stack.push(...(byParent.get(String(node.id)) ?? []));
  }

  return result;
}

const parentOptions = computed(() => {
  const blocked = editingId.value ? descendantIds(editingId.value) : new Set();

  return orderedMenus.value.filter(
    (menu) => ['section', 'dropdown'].includes(menu.type)
      && String(menu.id) !== String(editingId.value)
      && !blocked.has(String(menu.id)),
  );
});

function isGroup(menu) {
  return menu.type === 'section';
}

function typeLabel(type) {
  return TYPE_LABELS[type] ?? 'Link';
}

function parentName(sub) {
  if (!sub) {
    return '—';
  }

  const parent = menus.items.find((menu) => String(menu.id) === String(sub));

  return parent ? parent.name : '—';
}

function resetForm() {
  form.name = '';
  form.type = 'link';
  form.sub = '';
  form.link = '';
  form.icon = '';
  form.sort_order = 0;
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

function openEdit(menu) {
  editingId.value = menu.id;
  form.name = menu.name ?? '';
  form.type = menu.type ?? 'link';
  form.sub = menu.sub ? String(menu.sub) : '';
  form.link = menu.link ?? '';
  form.icon = menu.icon ?? '';
  form.sort_order = menu.sort_order ?? 0;
  showForm.value = true;
}

async function save() {
  saving.value = true;

  try {
    const payload = {
      name: form.name,
      type: form.type,
      sub: form.sub ? Number(form.sub) : null,
      link: form.link || null,
      icon: form.icon || null,
      sort_order: Number(form.sort_order) || 0,
    };

    await menus.saveMenu(payload, editingId.value);

    toast.success('Item salvo.');
    showForm.value = false;
    editingId.value = null;
    resetForm();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível salvar o item.');
  } finally {
    saving.value = false;
  }
}

async function remove(menu) {
  if (!window.confirm(`Excluir o item "${menu.name}"?`)) {
    return;
  }

  busyId.value = menu.id;

  try {
    await menus.removeMenu(menu.id);
    toast.success('Item excluído.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível excluir o item.');
  } finally {
    busyId.value = null;
  }
}

onMounted(() => menus.fetchMenus());
</script>

<style scoped>
.menu-name{
  display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:700;
}
.menu-name.child{
  font-weight:600;color:var(--text-muted);
}
.menu-name .icon{
  color:var(--accent);
}
.menu-group-row td{
  background:var(--bg-card-alt);
  border-top:1px solid var(--border);
}
.menu-group-row .menu-name{
  font-size:11.5px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);
}
.menu-child-row .menu-name .icon{
  color:var(--text-muted);
}
.type-pill{
  display:inline-flex;align-items:center;padding:2px 9px;border-radius:999px;
  font-size:11px;font-weight:800;letter-spacing:.02em;
}
.type-link{background:rgba(91,141,239,.14);color:#5b8def;}
.type-section{background:rgba(148,163,184,.18);color:#94a3b8;}
.type-dropdown{background:var(--accent-soft);color:var(--accent);}
.menu-preview{
  display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border:1px solid var(--border);
  border-radius:10px;background:var(--bg-card-alt);font-size:13px;font-weight:700;color:var(--text);
}
.menu-preview .icon{
  color:var(--accent);
}
</style>
