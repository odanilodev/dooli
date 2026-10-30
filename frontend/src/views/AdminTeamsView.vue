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

      <div>
        <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Criando...' : 'Criar turma' }}</button>
      </div>
    </form>

    <template v-if="!showForm">

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

    <div v-if="bulkCount" class="bulk-bar">
      <strong>{{ bulkCount }} turma(s) selecionada(s)</strong>
      <div class="bulk-bar-actions">
        <button type="button" class="btn btn-outline btn-sm" :disabled="bulkBusy" @click="openPicker('courses')">Associar curso(s)</button>
        <button type="button" class="btn btn-outline btn-sm" :disabled="bulkBusy" @click="openPicker('users')">Adicionar aluno(s)</button>
        <button type="button" class="btn btn-outline btn-sm" :disabled="bulkBusy" @click="bulkStatus('activate')">Ativar</button>
        <button type="button" class="btn btn-outline btn-sm" :disabled="bulkBusy" @click="bulkStatus('deactivate')">Inativar</button>
        <button type="button" class="btn btn-danger btn-sm" :disabled="bulkBusy" @click="bulkDelete">Excluir</button>
        <button type="button" class="btn btn-outline btn-sm" :disabled="bulkBusy" @click="clearSelection">Limpar</button>
      </div>
    </div>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <div v-else-if="!teams.length" class="empty-state">
      <strong>Nenhuma turma encontrada</strong>
      <span>{{ search || status ? 'Ajuste a busca ou o filtro selecionado.' : 'Crie a primeira turma para liberar acesso aos alunos.' }}</span>
      <button v-if="!search && !status" class="btn btn-primary" @click="openCreate">+ Nova turma</button>
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
                aria-label="Selecionar todas"
                @change="toggleAll(pageIds, $event.target.checked)"
              >
            </th>
            <th>Turma</th><th>Alunos</th><th>Cursos</th><th>Agendamentos</th><th>Status</th><th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="team in teams" :key="team.id">
            <td class="bulk-col">
              <input
                class="admin-check"
                type="checkbox"
                :checked="isSelected(team.id)"
                :aria-label="`Selecionar ${team.name}`"
                @change="toggle(team.id)"
              >
            </td>
            <td>
              <strong>{{ team.name }}</strong>
              <span class="admin-sub">#{{ team.id }}</span>
            </td>
            <td><span class="status-count">{{ team.users_count ?? 0 }}</span></td>
            <td><span class="status-count">{{ team.courses_count ?? 0 }}</span></td>
            <td>
              <span v-if="team.schedules_count" class="status-count sched">{{ team.schedules_count }}</span>
              <span v-else class="admin-muted">—</span>
            </td>
            <td><span class="status-pill" :class="team.status">{{ teamStatusLabel(team.status) }}</span></td>
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

    </template>

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
        <button type="button" role="menuitem" @click="run('tools')">Ferramentas</button>
        <button type="button" role="menuitem" @click="run('schedules')">Agendamentos</button>
        <div class="team-menu-sep"></div>
        <button type="button" role="menuitem" class="danger" @click="run('delete')">Excluir</button>
      </div>
    </Teleport>

    <BulkPickerModal
      :open="picker.open"
      :title="picker.mode === 'users' ? 'Adicionar alunos' : 'Associar cursos'"
      :label="picker.mode === 'users' ? 'Aluno' : 'Curso'"
      :placeholder="picker.mode === 'users' ? 'Buscar por nome ou e-mail...' : 'Buscar curso...'"
      :confirm-label="picker.mode === 'users' ? 'Adicionar' : 'Associar'"
      :search-endpoint="picker.mode === 'users' ? '/admin/users' : '/admin/courses'"
      :map-results="mapPickerResult"
      @close="picker.open = false"
      @confirm="confirmPicker"
    />

  </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';
import { useBulkSelection } from '../composables/useBulkSelection';
import { teamStatusLabel } from '../utils/adminLabels';
import TeamDrawer from '../components/admin/TeamDrawer.vue';
import BulkPickerModal from '../components/admin/BulkPickerModal.vue';

const toast = useToast();

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
const busyId = ref(null);

const showForm = ref(false);
const saving = ref(false);
const form = reactive({ name: '', status: 'active', sort_order: 0 });

const selectedTeam = ref(null);
const drawerTab = ref('details');

const menuTeam = ref(null);
const menuPos = reactive({ top: 0, left: 0, up: false });
const MENU_WIDTH = 176;
const MENU_HEIGHT = 186;

const { selectedIds, count: bulkCount, isSelected, toggle, toggleAll, isAllSelected, isPartial, clear: clearSelection } = useBulkSelection();
const bulkBusy = ref(false);
const picker = reactive({ open: false, mode: 'courses' });

const pageIds = computed(() => teams.value.map((team) => team.id));

let searchTimer = null;

function resetForm() {
  form.name = '';
  form.status = 'active';
  form.sort_order = 0;
}

async function load() {
  loading.value = true;

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
    toast.error(e?.message || 'Não foi possível carregar as turmas.');
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
  showForm.value = true;
}

async function save() {
  saving.value = true;

  try {
    const { data } = await api.post('/admin/teams', {
      name: form.name,
      status: form.status,
      sort_order: form.sort_order,
    });

    showForm.value = false;
    resetForm();
    toast.success('Turma criada.');
    await load();
    drawerTab.value = 'details';
    selectedTeam.value = data.data;
  } catch (e) {
    toast.error(e?.message || 'Não foi possível criar a turma.');
  } finally {
    saving.value = false;
  }
}

async function remove(team) {
  if (!window.confirm(`Excluir a turma "${team.name}"? Os vínculos de alunos e cursos serão removidos.`)) {
    return;
  }

  busyId.value = team.id;

  try {
    await api.delete(`/admin/teams/${team.id}`);
    toast.success('Turma excluída.');
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível excluir a turma.');
  } finally {
    busyId.value = null;
  }
}

function openPicker(mode) {
  picker.mode = mode;
  picker.open = true;
}

function mapPickerResult(item) {
  if (picker.mode === 'users') {
    return { value: item.id, label: item.name, sub: item.email };
  }

  return { value: item.id, label: item.name, sub: item.status === 'active' ? 'Ativo' : 'Inativo' };
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
    const { data } = await api.post('/admin/teams/bulk', payload);
    reportSummary(data.data);
    await load();
    clearSelection();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível concluir a ação.');
  } finally {
    bulkBusy.value = false;
  }
}

function confirmPicker(items) {
  const ids = items.map((item) => item.value);
  const mode = picker.mode;
  picker.open = false;

  return runBulk(
    mode === 'users'
      ? { action: 'add-students', team_ids: selectedIds.value, user_ids: ids }
      : { action: 'attach-courses', team_ids: selectedIds.value, course_ids: ids },
  );
}

function bulkStatus(action) {
  if (action === 'deactivate') {
    const studentsCount = teams.value
      .filter((team) => selectedIds.value.includes(team.id))
      .reduce((sum, team) => sum + (team.users_count ?? 0), 0);

    const message = studentsCount > 0
      ? `Inativar as turmas selecionadas bloqueará o acesso de ${studentsCount} aluno(s) aos cursos até serem reativadas. Continuar?`
      : 'Inativar as turmas selecionadas?';

    if (!window.confirm(message)) {
      return;
    }
  }

  return runBulk({ action, team_ids: selectedIds.value });
}

function bulkDelete() {
  if (!window.confirm(`Excluir ${bulkCount.value} turma(s) selecionada(s)? Os vínculos de alunos e cursos serão removidos.`)) {
    return;
  }

  return runBulk({ action: 'delete', team_ids: selectedIds.value });
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
.status-count.sched{background:rgba(59,130,246,.15);color:#3b82f6;}
.bulk-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:12px 0;padding:10px 14px;border:1px solid var(--border);border-radius:12px;background:var(--bg-card-alt);}
.bulk-bar strong{font-size:13px;}
.bulk-bar-actions{display:flex;gap:8px;flex-wrap:wrap;}
.bulk-col{width:44px;text-align:center;vertical-align:middle;}
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
