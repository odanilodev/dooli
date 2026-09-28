<template>
  <div class="drawer-backdrop" @mousedown="onBackdropDown" @click.self="backdropDown && emit('close')">
    <aside class="drawer">
      <header class="drawer-head">
        <div class="drawer-head-text">
          <span class="drawer-eyebrow">Turma</span>
          <h2 class="drawer-title">{{ form.name || team.name }}</h2>
        </div>
        <span class="status-pill" :class="form.status">{{ statusLabel(form.status) }}</span>
        <button class="drawer-close" @click="emit('close')" aria-label="Fechar">×</button>
      </header>

      <nav class="drawer-tabs">
        <button
          v-for="t in tabs"
          :key="t"
          class="drawer-tab"
          :class="{ active: tab === t }"
          @click="tab = t"
        >{{ tabLabels[t] }}</button>
      </nav>

      <div class="drawer-body">
        <span class="form-error" :class="{ show: error }">{{ error }}</span>
        <div class="form-success" :class="{ show: saved }">Salvo.</div>

        <!-- DETALHES -->
        <template v-if="tab === 'details'">
          <div class="form-group">
            <label for="td-name">Nome</label>
            <input id="td-name" class="form-control" v-model="form.name" @keydown.enter.prevent="saveDetails">
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="td-status">Status</label>
              <select id="td-status" class="form-control" v-model="form.status">
                <option value="active">Ativa</option>
                <option value="inactive">Inativa</option>
              </select>
            </div>
            <div class="form-group">
              <label for="td-sort">Ordenação</label>
              <input id="td-sort" class="form-control" type="number" min="0" v-model.number="form.sort_order">
            </div>
          </div>

          <p class="form-hint">Turma ativa libera os cursos associados para os alunos matriculados.</p>
        </template>

        <!-- ALUNOS -->
        <template v-else-if="tab === 'students'">
          <div class="form-group">
            <label>Alunos na turma ({{ students.length }})</label>
            <div v-if="students.length" class="td-list">
              <div v-for="s in students" :key="s.id" class="td-row">
                <span class="td-avatar">{{ initials(s.name) }}</span>
                <span class="td-info">
                  <span class="td-name">{{ s.name }}</span>
                  <span class="td-sub">{{ s.username || s.email }}</span>
                </span>
                <button class="btn btn-danger btn-sm" :disabled="busyId === `u${s.id}`" @click="removeStudent(s)">Remover</button>
              </div>
            </div>
            <p v-else class="form-hint">Nenhum aluno matriculado.</p>
          </div>

          <div class="drawer-divider"></div>

          <div class="form-group">
            <label for="td-student-search">Adicionar aluno</label>
            <input id="td-student-search" class="form-control" v-model="studentSearch" @input="searchStudents" placeholder="Buscar por nome, usuário ou e-mail...">
          </div>

          <div class="form-group">
            <label for="td-expiry">Expira em (opcional)</label>
            <input id="td-expiry" class="form-control" type="datetime-local" v-model="studentExpiry">
          </div>

          <p v-if="searchingStudents" class="form-hint">Buscando...</p>
          <div v-else-if="studentResults.length" class="td-list">
            <div v-for="s in studentResults" :key="s.id" class="td-row">
              <span class="td-avatar">{{ initials(s.name) }}</span>
              <span class="td-info">
                <span class="td-name">{{ s.name }}</span>
                <span class="td-sub">{{ s.username || s.email }}</span>
              </span>
              <button class="btn btn-outline btn-sm" :disabled="busyId === `a${s.id}`" @click="addStudent(s)">Adicionar</button>
            </div>
          </div>
          <p v-else-if="studentSearch" class="form-hint">Nenhum aluno encontrado.</p>
        </template>

        <!-- CURSOS -->
        <template v-else-if="tab === 'courses'">
          <div class="form-group">
            <label>Cursos da turma ({{ courses.length }})</label>
            <div v-if="courses.length" class="td-list">
              <div v-for="c in courses" :key="c.id" class="td-row">
                <span class="td-info">
                  <span class="td-name">{{ c.name }}</span>
                  <span class="td-sub">#{{ c.id }}</span>
                </span>
                <button class="btn btn-danger btn-sm" :disabled="busyId === `c${c.id}`" @click="removeCourse(c)">Desassociar</button>
              </div>
            </div>
            <p v-else class="form-hint">Nenhum curso associado.</p>
          </div>

          <div class="drawer-divider"></div>

          <div class="form-group">
            <label for="td-course-search">Associar curso</label>
            <input id="td-course-search" class="form-control" v-model="courseSearch" @input="searchCourses" placeholder="Buscar curso...">
          </div>

          <p v-if="searchingCourses" class="form-hint">Buscando...</p>
          <div v-else-if="courseResults.length" class="td-list">
            <div v-for="c in courseResults" :key="c.id" class="td-row">
              <span class="td-info">
                <span class="td-name">{{ c.name }}</span>
                <span class="td-sub">#{{ c.id }}</span>
              </span>
              <button class="btn btn-outline btn-sm" :disabled="busyId === `ac${c.id}`" @click="addCourse(c)">Associar</button>
            </div>
          </div>
          <p v-else-if="courseSearch" class="form-hint">Nenhum curso encontrado.</p>
        </template>
      </div>

      <footer v-if="tab === 'details'" class="drawer-foot">
        <button type="button" class="btn btn-primary" :disabled="saving" @click="saveDetails">
          {{ saving ? 'Salvando...' : 'Salvar' }}
        </button>
      </footer>
    </aside>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { api } from '../../api/http';
import { statusLabel } from '../../utils/adminLabels';

const props = defineProps({
  team: { type: Object, required: true },
  initialTab: { type: String, default: 'details' },
});

const emit = defineEmits(['close', 'saved', 'changed']);

const tabs = ['details', 'students', 'courses'];
const tabLabels = { details: 'Detalhes', students: 'Alunos', courses: 'Cursos' };

const tab = ref(tabs.includes(props.initialTab) ? props.initialTab : 'details');
const form = reactive({ name: '', status: 'active', sort_order: 0 });
const error = ref('');
const saved = ref(false);
const saving = ref(false);
const busyId = ref(null);
const backdropDown = ref(false);

const students = ref([]);
const courses = ref([]);

const studentSearch = ref('');
const studentResults = ref([]);
const searchingStudents = ref(false);
const studentExpiry = ref('');
const studentTypeId = ref(null);

const courseSearch = ref('');
const courseResults = ref([]);
const searchingCourses = ref(false);

let studentTimer = null;
let courseTimer = null;

function initials(name) {
  return (name || '?').trim().split(/\s+/).slice(0, 2).map((p) => p[0]).join('').toUpperCase();
}

function sync() {
  form.name = props.team.name ?? '';
  form.status = props.team.status ?? 'active';
  form.sort_order = props.team.sort_order ?? 0;
}

async function loadStudents() {
  try {
    const { data } = await api.get(`/admin/teams/${props.team.id}/students`);
    students.value = data.data ?? [];
    const enrolled = new Set(students.value.map((s) => s.id));
    studentResults.value = studentResults.value.filter((s) => !enrolled.has(s.id));
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar os alunos.';
  }
}

async function loadCourses() {
  try {
    const { data } = await api.get(`/admin/teams/${props.team.id}/courses`);
    courses.value = data.data ?? [];
    const linked = new Set(courses.value.map((c) => c.id));
    courseResults.value = courseResults.value.filter((c) => !linked.has(c.id));
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar os cursos.';
  }
}

async function ensureStudentType() {
  if (studentTypeId.value) {
    return;
  }

  try {
    const { data } = await api.get('/admin/user-types', { params: { per_page: 100 } });
    studentTypeId.value = (data.data ?? []).find((t) => t.code === 'student')?.id ?? null;
  } catch (e) {
    /* silencioso */
  }
}

function searchStudents() {
  clearTimeout(studentTimer);
  const term = studentSearch.value.trim();

  if (!term) {
    studentResults.value = [];
    return;
  }

  studentTimer = setTimeout(async () => {
    searchingStudents.value = true;

    try {
      const { data } = await api.get('/admin/users', {
        params: { search: term, user_type_id: studentTypeId.value || undefined, per_page: 20 },
      });
      const enrolled = new Set(students.value.map((s) => s.id));
      studentResults.value = (data.data ?? []).filter((u) => !enrolled.has(u.id));
    } catch (e) {
      error.value = e?.message || 'Não foi possível buscar alunos.';
    } finally {
      searchingStudents.value = false;
    }
  }, 350);
}

function searchCourses() {
  clearTimeout(courseTimer);
  const term = courseSearch.value.trim();

  if (!term) {
    courseResults.value = [];
    return;
  }

  courseTimer = setTimeout(async () => {
    searchingCourses.value = true;

    try {
      const { data } = await api.get('/admin/courses', { params: { search: term, per_page: 20 } });
      const linked = new Set(courses.value.map((c) => c.id));
      courseResults.value = (data.data ?? []).filter((c) => !linked.has(c.id));
    } catch (e) {
      error.value = e?.message || 'Não foi possível buscar cursos.';
    } finally {
      searchingCourses.value = false;
    }
  }, 350);
}

async function saveDetails() {
  if (!form.name.trim()) {
    error.value = 'Informe o nome.';
    return;
  }

  saving.value = true;
  error.value = '';

  try {
    const { data } = await api.put(`/admin/teams/${props.team.id}`, {
      name: form.name,
      status: form.status,
      sort_order: form.sort_order,
    });
    saved.value = true;
    setTimeout(() => { saved.value = false; }, 1800);
    emit('saved', { id: props.team.id, data: data.data ?? {} });
  } catch (e) {
    error.value = e?.message || 'Não foi possível salvar.';
  } finally {
    saving.value = false;
  }
}

async function addStudent(student) {
  busyId.value = `a${student.id}`;
  error.value = '';

  try {
    await api.post(`/admin/teams/${props.team.id}/students`, {
      user_id: student.id,
      expired_at: studentExpiry.value || null,
    });
    studentSearch.value = '';
    studentResults.value = [];
    await loadStudents();
    emit('changed');
  } catch (e) {
    error.value = e?.message || 'Não foi possível adicionar o aluno.';
  } finally {
    busyId.value = null;
  }
}

async function removeStudent(student) {
  busyId.value = `u${student.id}`;
  error.value = '';

  try {
    await api.delete(`/admin/teams/${props.team.id}/students/${student.id}`);
    await loadStudents();
    emit('changed');
  } catch (e) {
    error.value = e?.message || 'Não foi possível remover o aluno.';
  } finally {
    busyId.value = null;
  }
}

async function addCourse(course) {
  busyId.value = `ac${course.id}`;
  error.value = '';

  try {
    await api.post(`/admin/teams/${props.team.id}/courses`, { course_id: course.id });
    courseSearch.value = '';
    courseResults.value = [];
    await loadCourses();
    emit('changed');
  } catch (e) {
    error.value = e?.message || 'Não foi possível associar o curso.';
  } finally {
    busyId.value = null;
  }
}

async function removeCourse(course) {
  busyId.value = `c${course.id}`;
  error.value = '';

  try {
    await api.delete(`/admin/teams/${props.team.id}/courses/${course.id}`);
    await loadCourses();
    emit('changed');
  } catch (e) {
    error.value = e?.message || 'Não foi possível desassociar o curso.';
  } finally {
    busyId.value = null;
  }
}

function onKeydown(e) {
  if (e.key === 'Escape') {
    emit('close');
  }
}

function onBackdropDown(e) {
  backdropDown.value = e.target === e.currentTarget;
}

watch(() => props.team, sync, { immediate: true });

watch(() => props.team?.id, () => {
  tab.value = tabs.includes(props.initialTab) ? props.initialTab : 'details';
});

onMounted(() => {
  ensureStudentType();
  loadStudents();
  loadCourses();
  document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<style scoped>
.td-list{display:flex;flex-direction:column;gap:8px;}
.td-row{display:flex;align-items:center;gap:10px;padding:9px 11px;background:var(--bg-card-alt);border:1px solid var(--border);border-radius:10px;}
.td-avatar{width:30px;height:30px;border-radius:50%;background:var(--accent-soft);color:var(--accent);font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.td-info{flex:1;min-width:0;display:flex;flex-direction:column;}
.td-name{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.td-sub{font-size:11.5px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
</style>
