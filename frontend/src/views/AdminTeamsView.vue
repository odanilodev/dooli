<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Turmas</h1>
        <p class="page-subtitle">{{ meta.total ?? teams.length }} turma(s) · acesso de alunos aos cursos</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">{{ showForm ? 'Cancelar' : '+ Nova turma' }}</button>
    </div>

    <form v-if="showForm" class="panel" @submit.prevent="save">
      <h2 class="panel-title">Nova turma</h2>
      <p class="panel-desc">Depois de criar, adicione alunos e associe os cursos liberados.</p>

      <div class="form-group">
        <label for="tm-name">Nome</label>
        <input id="tm-name" class="form-control" v-model="form.name" required placeholder="Turma 2026.1">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="tm-status">Status</label>
          <select id="tm-status" class="form-control" v-model="form.status">
            <option value="active">Ativa</option>
            <option value="inactive">Inativa</option>
          </select>
        </div>
        <div class="form-group">
          <label for="tm-sort">Ordenação</label>
          <input id="tm-sort" class="form-control" type="number" min="0" v-model.number="form.sort_order">
        </div>
      </div>

      <span class="form-error" :class="{ show: formError }">{{ formError }}</span>

      <div>
        <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Criando...' : 'Criar turma' }}</button>
      </div>
    </form>

    <div class="catalog-toolbar">
      <div class="filter-tabs">
        <button
          v-for="tab in filters"
          :key="tab.value"
          class="filter-tab"
          :class="{ active: status === tab.value }"
          @click="setStatus(tab.value)"
        >{{ tab.label }}</button>
      </div>
      <input class="form-control admin-search" type="search" v-model="search" @input="onSearchInput" placeholder="Buscar turma...">
    </div>

    <span class="form-error" :class="{ show: error }">{{ error }}</span>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <div v-else-if="!teams.length" class="empty-state">
      <strong>Nenhuma turma encontrada</strong>
      <span>{{ search || status ? 'Ajuste a busca ou o filtro selecionado.' : 'Crie a primeira turma para liberar acesso aos alunos.' }}</span>
      <button v-if="!search && !status" class="btn btn-primary" @click="openCreate">+ Nova turma</button>
    </div>

    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>Turma</th><th>Alunos</th><th>Cursos</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="team in teams" :key="team.id">
            <td>
              <strong>{{ team.name }}</strong>
              <span class="admin-sub">#{{ team.id }}</span>
            </td>
            <td><span class="status-count">{{ team.users_count ?? 0 }}</span></td>
            <td><span class="status-count">{{ team.courses_count ?? 0 }}</span></td>
            <td><span class="status-pill" :class="team.status">{{ statusLabel(team.status) }}</span></td>
            <td class="team-actions">
              <button
                type="button"
                class="team-kebab"
                :disabled="busyId === team.id"
                :aria-expanded="menuTeam?.id === team.id"
                aria-haspopup="menu"
                title="Ações"
                @click.stop="toggleMenu(team, $event)"
              >⋯</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="admin-pager">
      <button class="btn btn-outline btn-sm" :disabled="meta.current_page <= 1" @click="goPage(meta.current_page - 1)">Anterior</button>
      <span class="admin-muted">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      <button class="btn btn-outline btn-sm" :disabled="meta.current_page >= meta.last_page" @click="goPage(meta.current_page + 1)">Próxima</button>
    </div>

    <Transition name="drawer">
      <TeamDrawer
        v-if="selectedTeam"
        :team="selectedTeam"
        :initial-tab="drawerTab"
        @close="selectedTeam = null"
        @saved="onSaved"
        @changed="load"
      />
    </Transition>

    <Teleport to="body">
      <div
        v-if="menuTeam"
        class="team-dropdown"
        role="menu"
        :style="{ top: menuPos.top + 'px', left: menuPos.left + 'px', transform: menuPos.up ? 'translateY(-100%)' : 'none' }"
        @click.stop
      >
        <button type="button" role="menuitem" @click="run('details')">Detalhes</button>
        <button type="button" role="menuitem" @click="run('students')">Alunos</button>
        <button type="button" role="menuitem" @click="run('courses')">Cursos</button>
        <div class="team-menu-sep"></div>
        <button type="button" role="menuitem" class="danger" @click="run('delete')">Excluir</button>
      </div>
    </Teleport>

  </main>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';
import { statusLabel } from '../utils/adminLabels';
import TeamDrawer from '../components/admin/TeamDrawer.vue';

const filters = [
  { value: '', label: 'Todas' },
  { value: 'active', label: 'Ativas' },
  { value: 'inactive', label: 'Inativas' },
];

const teams = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const status = ref('');
const search = ref('');
const loading = ref(true);
const error = ref('');
const busyId = ref(null);

const showForm = ref(false);
const saving = ref(false);
const formError = ref('');
const form = reactive({ name: '', status: 'active', sort_order: 0 });

const selectedTeam = ref(null);
const drawerTab = ref('details');

const menuTeam = ref(null);
const menuPos = reactive({ top: 0, left: 0, up: false });
const MENU_WIDTH = 176;
const MENU_HEIGHT = 150;

let searchTimer = null;

function resetForm() {
  form.name = '';
  form.status = 'active';
  form.sort_order = 0;
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get('/admin/teams', {
      params: {
        search: search.value || undefined,
        status: status.value || undefined,
        page: meta.value.current_page || 1,
        per_page: 15,
      },
    });

    teams.value = data.data ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar as turmas.';
  } finally {
    loading.value = false;
  }
}

function onSearchInput() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    meta.value.current_page = 1;
    load();
  }, 350);
}

function setStatus(value) {
  status.value = value;
  meta.value.current_page = 1;
  load();
}

function goPage(page) {
  meta.value.current_page = page;
  load();
}

function openCreate() {
  if (showForm.value) {
    showForm.value = false;
    return;
  }

  resetForm();
  formError.value = '';
  showForm.value = true;
}

async function save() {
  saving.value = true;
  formError.value = '';

  try {
    const { data } = await api.post('/admin/teams', {
      name: form.name,
      status: form.status,
      sort_order: form.sort_order,
    });

    showForm.value = false;
    resetForm();
    await load();
    drawerTab.value = 'details';
    selectedTeam.value = data.data;
  } catch (e) {
    formError.value = e?.message || 'Não foi possível criar a turma.';
  } finally {
    saving.value = false;
  }
}

async function remove(team) {
  if (!window.confirm(`Excluir a turma "${team.name}"? Os vínculos de alunos e cursos serão removidos.`)) {
    return;
  }

  busyId.value = team.id;
  error.value = '';

  try {
    await api.delete(`/admin/teams/${team.id}`);
    await load();
  } catch (e) {
    error.value = e?.message || 'Não foi possível excluir a turma.';
  } finally {
    busyId.value = null;
  }
}

function openTeam(team, tab = 'details') {
  drawerTab.value = tab;
  selectedTeam.value = team;
}

function toggleMenu(team, event) {
  if (menuTeam.value?.id === team.id) {
    closeMenu();
    return;
  }

  const rect = event.currentTarget.getBoundingClientRect();
  const up = rect.bottom + MENU_HEIGHT > window.innerHeight;

  menuPos.up = up;
  menuPos.top = up ? rect.top - 6 : rect.bottom + 6;
  menuPos.left = Math.max(8, rect.right - MENU_WIDTH);
  menuTeam.value = team;
}

function closeMenu() {
  menuTeam.value = null;
}

function run(action) {
  const team = menuTeam.value;
  closeMenu();

  if (!team) {
    return;
  }

  if (action === 'delete') {
    remove(team);
    return;
  }

  openTeam(team, action);
}

function onKeydown(e) {
  if (e.key === 'Escape') {
    closeMenu();
  }
}

function onSaved({ id, data }) {
  const team = teams.value.find((t) => t.id === id);

  if (team) {
    Object.assign(team, data);
  }

  if (selectedTeam.value?.id === id) {
    Object.assign(selectedTeam.value, data);
  }
}

onMounted(() => {
  load();
  document.addEventListener('click', closeMenu);
  document.addEventListener('keydown', onKeydown);
  window.addEventListener('scroll', closeMenu, true);
  window.addEventListener('resize', closeMenu);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', closeMenu);
  document.removeEventListener('keydown', onKeydown);
  window.removeEventListener('scroll', closeMenu, true);
  window.removeEventListener('resize', closeMenu);
});
</script>

<style scoped>
.status-count{display:inline-flex;align-items:center;justify-content:center;min-width:26px;height:24px;padding:0 8px;border-radius:999px;background:var(--accent-soft);color:var(--accent);font-size:12px;font-weight:800;}
.team-actions{text-align:right;white-space:nowrap;}
.team-kebab{
  display:inline-flex;align-items:center;justify-content:center;width:30px;height:26px;padding:0;border-radius:9px;cursor:pointer;
  background:transparent;border:1.5px solid var(--border);color:var(--text-secondary);
  font-family:inherit;font-size:16px;line-height:1;font-weight:800;transition:border-color .14s ease,color .14s ease;
}
.team-kebab:hover{border-color:var(--accent);color:var(--text-primary);}
.team-kebab:disabled{opacity:.5;cursor:wait;}
</style>

<style scoped>
.team-dropdown{
  position:fixed;z-index:120;min-width:176px;padding:6px;
  background:var(--bg-card);border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);
  display:flex;flex-direction:column;gap:2px;
}
.team-dropdown button{
  text-align:left;background:none;border:none;color:var(--text-secondary);font-family:inherit;font-size:12.5px;font-weight:600;
  padding:8px 10px;border-radius:8px;cursor:pointer;white-space:nowrap;
}
.team-dropdown button:hover{background:var(--bg-card-alt);color:var(--text-primary);}
.team-dropdown button.danger{color:#ef4444;}
.team-dropdown button.danger:hover{background:rgba(239,68,68,.12);}
.team-menu-sep{height:1px;background:var(--border);margin:4px 2px;}
</style>
