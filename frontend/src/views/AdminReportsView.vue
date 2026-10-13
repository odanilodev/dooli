<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Relatório de progresso</h1>
        <p class="page-subtitle">Acompanhe o que os alunos concluíram, estão fazendo e ainda não iniciaram.</p>
      </div>
      <button class="btn btn-outline" :disabled="exporting" @click="exportFile">
        {{ exporting ? 'Exportando...' : 'Exportar CSV' }}
      </button>
    </div>

    <div class="panel report-filters">
      <div class="filter-grid">
        <div class="form-group">
          <label>Turma</label>
          <SearchSelect
            :model-value="store.filters.team_id || ''"
            remote
            allow-clear
            clear-label="Todas as turmas"
            :options="options.team_id"
            :loading="loadingOption.team_id"
            placeholder="Todas as turmas"
            @search="loadOption('team_id', $event, '/admin/teams')"
            @update:model-value="onSelect('team_id', $event)"
          />
        </div>
        <div class="form-group">
          <label>Curso</label>
          <SearchSelect
            :model-value="store.filters.course_id || ''"
            remote
            allow-clear
            clear-label="Todos os cursos"
            :options="options.course_id"
            :loading="loadingOption.course_id"
            placeholder="Todos os cursos"
            @search="loadOption('course_id', $event, '/admin/courses')"
            @update:model-value="onSelect('course_id', $event)"
          />
        </div>
        <div class="form-group">
          <label>Módulo</label>
          <SearchSelect
            :model-value="store.filters.module_id || ''"
            remote
            allow-clear
            clear-label="Todos os módulos"
            :options="options.module_id"
            :loading="loadingOption.module_id"
            placeholder="Todos os módulos"
            @search="loadOption('module_id', $event, '/admin/modules')"
            @update:model-value="onSelect('module_id', $event)"
          />
        </div>
        <div class="form-group">
          <label>Aula</label>
          <SearchSelect
            :model-value="store.filters.lesson_id || ''"
            remote
            allow-clear
            clear-label="Todas as aulas"
            :options="options.lesson_id"
            :loading="loadingOption.lesson_id"
            placeholder="Todas as aulas"
            @search="loadOption('lesson_id', $event, '/admin/lessons')"
            @update:model-value="onSelect('lesson_id', $event)"
          />
        </div>
      </div>

      <div class="form-group report-students">
        <label>Alunos</label>
        <StudentPicker
          :model-value="store.filters.students"
          @update:model-value="store.setStudents($event)"
        />
      </div>

      <div class="report-filters-bottom">
        <div class="filter-tabs">
          <button
            v-for="tab in statusTabs"
            :key="tab.value"
            class="filter-tab"
            :class="{ active: store.filters.student_status === tab.value }"
            @click="store.setStatus(tab.value)"
          >{{ tab.label }}</button>
        </div>
        <input
          class="form-control admin-search"
          type="search"
          :value="store.filters.search"
          placeholder="Buscar aluno..."
          @input="onSearch"
        >
      </div>
    </div>

    <div class="report-toolbar">
      <div class="filter-tabs report-levels">
        <button
          v-for="level in levels"
          :key="level.value"
          class="filter-tab"
          :class="{ active: store.groupBy === level.value }"
          @click="store.setGroupBy(level.value)"
        >{{ level.label }}</button>
      </div>

      <nav v-if="store.crumbs.length" class="report-crumbs">
        <button class="report-crumb" @click="store.jumpTo({ level: 'team' })">Início</button>
        <template v-for="crumb in store.crumbs" :key="crumb.key">
          <span class="report-crumb-sep">/</span>
          <button class="report-crumb" @click="store.jumpTo(crumb)">{{ crumb.name }}</button>
        </template>
      </nav>
    </div>

    <div class="report-kpis">
      <div class="stat-card"><span class="stat-value">{{ totals.students ?? 0 }}</span><span class="stat-label">Alunos no escopo</span></div>
      <div class="stat-card"><span class="stat-value">{{ totals.lessons ?? 0 }}</span><span class="stat-label">Aulas elegíveis</span></div>
      <div class="stat-card"><span class="stat-value">{{ totals.completed ?? 0 }}</span><span class="stat-label">Concluídas</span></div>
      <div class="stat-card"><span class="stat-value">{{ totals.in_progress ?? 0 }}</span><span class="stat-label">Em andamento</span></div>
      <div class="stat-card"><span class="stat-value">{{ totals.pending ?? 0 }}</span><span class="stat-label">Pendentes</span></div>
      <div class="stat-card"><span class="stat-value">{{ totals.blocked ?? 0 }}</span><span class="stat-label">Bloqueadas</span></div>
      <div class="stat-card"><span class="stat-value">{{ totals.percentage ?? 0 }}%</span><span class="stat-label">Progresso médio</span></div>
    </div>

    <p v-if="store.loading" class="page-subtitle">Carregando...</p>

    <div v-else-if="!store.items.length" class="empty-state">
      <strong>Nenhum resultado</strong>
      <span>Ajuste os filtros ou selecione outro nível de agrupamento.</span>
    </div>

    <div v-else class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>{{ nameColumn }}</th>
            <th v-if="showStudents">Alunos</th>
            <th v-if="showLessons">Aulas</th>
            <th>Concluídas</th>
            <th>Em andamento</th>
            <th>Pendentes</th>
            <th>Bloqueadas</th>
            <th>Progresso</th>
            <th>Última atividade</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in store.items" :key="row.id" class="report-row" @click="onRow(row)">
            <td>
              <strong>{{ row.name }}</strong>
              <span v-if="row.subtitle" class="admin-sub">{{ row.subtitle }}</span>
            </td>
            <td v-if="showStudents" class="admin-muted">{{ row.students }}</td>
            <td v-if="showLessons" class="admin-muted">{{ row.lessons }}</td>
            <td>{{ row.completed }}</td>
            <td>{{ row.in_progress }}</td>
            <td>{{ row.pending }}</td>
            <td>{{ row.blocked }}</td>
            <td>
              <span class="report-progress-cell">
                <span class="report-progress">
                  <span class="report-progress-bar" :style="{ width: `${Math.min(100, row.percentage)}%` }"></span>
                </span>
                <span class="report-progress-value">{{ row.percentage }}%</span>
              </span>
            </td>
            <td class="admin-muted">{{ formatDateTime(row.last_activity_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="store.meta.last_page > 1" class="admin-pager">
      <button class="btn btn-outline btn-sm" :disabled="store.meta.current_page <= 1" @click="store.goPage(store.meta.current_page - 1)">Anterior</button>
      <span class="admin-muted">Página {{ store.meta.current_page }} de {{ store.meta.last_page }}</span>
      <button class="btn btn-outline btn-sm" :disabled="store.meta.current_page >= store.meta.last_page" @click="store.goPage(store.meta.current_page + 1)">Próxima</button>
    </div>

    <StudentProgressDrawer
      :open="studentDrawer.open"
      :user="studentDrawer.user"
      :params="detailParams"
      @close="studentDrawer.open = false"
    />

    <LessonProgressDrawer
      :open="lessonDrawer.open"
      :lesson="lessonDrawer.lesson"
      :params="detailParams"
      @close="lessonDrawer.open = false"
    />

  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';
import { formatDateTime } from '../utils/format';
import { useReportsStore } from '../stores/reports';
import SearchSelect from '../components/admin/SearchSelect.vue';
import StudentPicker from '../components/admin/StudentPicker.vue';
import StudentProgressDrawer from '../components/admin/StudentProgressDrawer.vue';
import LessonProgressDrawer from '../components/admin/LessonProgressDrawer.vue';

const store = useReportsStore();

const levels = [
  { value: 'team', label: 'Turmas' },
  { value: 'course', label: 'Cursos' },
  { value: 'module', label: 'Módulos' },
  { value: 'lesson', label: 'Aulas' },
  { value: 'student', label: 'Alunos' },
];

const statusTabs = [
  { value: '', label: 'Todos' },
  { value: 'active', label: 'Ativos' },
  { value: 'inactive', label: 'Inativos' },
];

const options = reactive({ team_id: [], course_id: [], module_id: [], lesson_id: [] });
const loadingOption = reactive({ team_id: false, course_id: false, module_id: false, lesson_id: false });

const exporting = ref(false);
const studentDrawer = reactive({ open: false, user: null });
const lessonDrawer = reactive({ open: false, lesson: null });

const totals = computed(() => store.totals ?? {});
const showStudents = computed(() => ['team', 'course', 'lesson'].includes(store.groupBy));
const showLessons = computed(() => store.groupBy !== 'student');

const nameColumn = computed(() => ({
  team: 'Turma', course: 'Curso', module: 'Módulo', lesson: 'Aula', student: 'Aluno',
}[store.groupBy] ?? 'Item'));

const detailParams = computed(() => ({
  team_id: store.filters.team_id || undefined,
  course_id: store.filters.course_id || undefined,
  user_ids: store.filters.students.length ? store.filters.students.map((s) => s.id) : undefined,
}));

let searchTimer = null;

async function loadOption(key, term, endpoint) {
  loadingOption[key] = true;

  try {
    const { data } = await api.get(endpoint, { params: { search: term || undefined, per_page: 20 } });
    options[key] = (data.data ?? []).map((item) => ({ value: item.id, label: item.name }));
  } catch (e) {
    options[key] = [];
  } finally {
    loadingOption[key] = false;
  }
}

function labelFor(key, value) {
  const option = options[key].find((o) => String(o.value) === String(value));

  return option?.label ?? '';
}

function onSelect(key, value) {
  store.setFilter(key.replace('_id', ''), value, labelFor(key, value));
}

function onSearch(event) {
  const value = event.target.value;
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => store.setSearch(value), 350);
}

function onRow(row) {
  if (store.groupBy === 'lesson') {
    lessonDrawer.lesson = row;
    lessonDrawer.open = true;
    return;
  }

  if (store.groupBy === 'student') {
    studentDrawer.user = row;
    studentDrawer.open = true;
    return;
  }

  store.drill(row);
}

async function exportFile() {
  exporting.value = true;

  try {
    await store.exportFile();
  } finally {
    exporting.value = false;
  }
}

onMounted(() => {
  ['team_id', 'course_id', 'module_id', 'lesson_id'].forEach((key, index) => {
    const endpoint = ['/admin/teams', '/admin/courses', '/admin/modules', '/admin/lessons'][index];
    loadOption(key, '', endpoint);
  });

  store.load();
});
</script>

<style scoped>
.report-filters{gap:22px;}
.report-filters .filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:18px;}
.report-filters-bottom{display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between;padding-top:18px;border-top:1px solid var(--border);}
.report-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.report-levels{gap:10px;}

.report-crumbs{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:13px;background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:10px 16px;box-shadow:var(--shadow-sm);}
.report-crumb{border:none;background:none;color:var(--accent);font-weight:700;cursor:pointer;font-family:inherit;font-size:13px;padding:2px 4px;}
.report-crumb:hover{text-decoration:underline;}
.report-crumb-sep{color:var(--text-muted);}

.report-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;}
.report-kpis .stat-card{padding:16px 18px;}

.report-row{cursor:pointer;}
.report-progress-cell{display:flex;align-items:center;gap:10px;}
.report-progress{flex:1;min-width:56px;max-width:110px;height:7px;border-radius:999px;background:var(--border);overflow:hidden;}
.report-progress-bar{display:block;height:100%;background:var(--accent-grad);}
.report-progress-value{font-size:12.5px;color:var(--text-muted);font-variant-numeric:tabular-nums;min-width:38px;}

@media (max-width:900px){
  .report-kpis{grid-template-columns:repeat(auto-fit,minmax(130px,1fr));}
}
</style>
