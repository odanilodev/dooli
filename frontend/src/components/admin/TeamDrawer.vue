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

            <div v-if="students.length" class="td-table-wrap">
              <table class="td-table">
                <thead>
                  <tr><th>Aluno</th><th>Vínculo</th><th>Expira em</th><th></th></tr>
                </thead>
                <tbody>
                  <template v-for="s in students" :key="s.id">
                    <tr>
                      <td>
                        <span class="td-name">{{ s.name }}</span>
                        <span class="td-sub">{{ s.username || s.email }}</span>
                      </td>
                      <td><span class="status-pill" :class="s.status">{{ enrollLabel(s.status) }}</span></td>
                      <td><span class="td-expiry" :class="{ expired: isExpired(s) }">{{ expiryLabel(s) }}</span></td>
                      <td class="td-actions">
                        <button
                          type="button"
                          class="td-kebab"
                          :aria-expanded="rowMenu?.id === s.id"
                          aria-haspopup="menu"
                          title="Ações"
                          @click.stop="toggleRowMenu(s, $event)"
                        >⋯</button>
                      </td>
                    </tr>

                    <tr v-if="editingStudentId === s.id" class="td-edit-row">
                      <td colspan="4">
                        <div class="td-editor">
                          <div class="form-group">
                            <label :for="`te-exp-${s.id}`">Expira em (vazio = sem expiração)</label>
                            <input :id="`te-exp-${s.id}`" class="form-control" type="datetime-local" v-model="editExpiry">
                          </div>
                          <div class="form-group">
                            <label :for="`te-st-${s.id}`">Vínculo na turma</label>
                            <select :id="`te-st-${s.id}`" class="form-control" v-model="editStatus">
                              <option value="active">Ativo</option>
                              <option value="inactive">Inativo</option>
                            </select>
                          </div>
                          <div class="td-editor-actions">
                            <button type="button" class="btn btn-outline btn-sm" @click="cancelEditStudent">Cancelar</button>
                            <button type="button" class="btn btn-primary btn-sm" :disabled="busyId === `s${s.id}`" @click="saveStudent(s)">Salvar</button>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
            <p v-else class="form-hint">Nenhum aluno matriculado.</p>
            <p class="form-hint">Expirar ou inativar aqui afeta só o vínculo nesta turma; a conta do aluno continua ativa.</p>
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

        <!-- AGENDAMENTOS -->
        <template v-else-if="tab === 'schedules'">
          <p class="form-hint">
            Escolha o que liberar e a data a partir da qual fica disponível para a turma. Quando houver mais de uma data
            (curso, módulo e aula), vale a mais tardia.
          </p>

          <p v-if="schedulesLoading" class="form-hint">Carregando...</p>
          <p v-else-if="!schedules.length" class="form-hint">Nenhum curso associado a esta turma.</p>
          <template v-else>
            <div class="td-builder">
              <div class="form-group">
                <label>O que agendar</label>
                <div class="td-seg">
                  <button type="button" class="td-seg-btn" :class="{ active: builder.level === 'course' }" @click="setBuilderLevel('course')">Curso todo</button>
                  <button type="button" class="td-seg-btn" :class="{ active: builder.level === 'module' }" @click="setBuilderLevel('module')">Módulo</button>
                  <button type="button" class="td-seg-btn" :class="{ active: builder.level === 'lesson' }" @click="setBuilderLevel('lesson')">Aula</button>
                </div>
              </div>

              <div class="form-group">
                <label for="td-sched-course">Curso</label>
                <SearchSelect
                  id="td-sched-course"
                  v-model="builder.courseId"
                  :options="courseOptions"
                  placeholder="Buscar curso..."
                  empty-text="Nenhum curso."
                />
              </div>

              <div v-if="builder.level !== 'course'" class="form-group">
                <label for="td-sched-module">Módulo</label>
                <SearchSelect
                  id="td-sched-module"
                  v-model="builder.moduleId"
                  :options="moduleOptions"
                  :disabled="!moduleOptions.length"
                  :placeholder="moduleOptions.length ? 'Buscar módulo...' : 'Nenhum módulo neste curso'"
                  empty-text="Nenhum módulo."
                />
              </div>

              <div v-if="builder.level === 'lesson'" class="form-group">
                <label for="td-sched-lesson">Aula</label>
                <SearchSelect
                  id="td-sched-lesson"
                  v-model="builder.lessonId"
                  :options="lessonOptions"
                  :disabled="!lessonOptions.length"
                  :placeholder="lessonOptions.length ? 'Buscar aula...' : 'Nenhuma aula neste módulo'"
                  empty-text="Nenhuma aula."
                />
              </div>

              <div class="form-group">
                <label for="td-sched-at">Disponível a partir de</label>
                <input id="td-sched-at" class="form-control" type="datetime-local" v-model="builder.availableAt">
              </div>

              <p v-if="builder.editingId" class="form-hint">Este item já tem agendamento. Os campos foram carregados para edição.</p>

              <div class="td-builder-actions">
                <button v-if="builder.editingId" type="button" class="btn btn-outline btn-sm" @click="cancelScheduleEdit">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" :disabled="!canSubmitSchedule || scheduleBusy" @click="submitSchedule">
                  {{ scheduleBusy ? 'Salvando...' : (builder.editingId ? 'Atualizar' : 'Agendar') }}
                </button>
              </div>
            </div>

            <div class="drawer-divider"></div>

            <div class="form-group">
              <label>Agendamentos ativos ({{ scheduledList.length }})</label>
              <div v-if="scheduledList.length" class="td-list">
                <div v-for="s in scheduledList" :key="`${s.type}${s.id}`" class="td-row">
                  <span class="td-sched-badge" :class="s.type">{{ levelLabel(s.type) }}</span>
                  <span class="td-info">
                    <span class="td-name">{{ s.name }}</span>
                    <span class="td-sub">{{ s.path }}</span>
                  </span>
                  <span class="td-sched-when">{{ formatDateTime(s.availableAt) }}</span>
                  <button type="button" class="btn btn-outline btn-sm" @click="editSchedule(s)">Editar</button>
                  <button type="button" class="td-sched-clear" title="Limpar" @click="clearSchedule(s)">×</button>
                </div>
              </div>
              <p v-else class="form-hint">Nenhum agendamento definido.</p>
            </div>
          </template>
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

    <Teleport to="body">
      <div
        v-if="rowMenu"
        class="td-row-menu"
        role="menu"
        :style="{ top: rowMenuPos.top + 'px', left: rowMenuPos.left + 'px', transform: rowMenuPos.up ? 'translateY(-100%)' : 'none' }"
        @click.stop
      >
        <button type="button" role="menuitem" @click="startEditStudent(rowMenu)">Editar vínculo</button>
        <button type="button" role="menuitem" class="danger" @click="removeStudent(rowMenu)">Remover da turma</button>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { api } from '../../api/http';
import SearchSelect from './SearchSelect.vue';
import { useToast } from '../../composables/useToast';
import { statusLabel } from '../../utils/adminLabels';
import { formatDateTime, toDateTimeInput } from '../../utils/format';

const props = defineProps({
  team: { type: Object, required: true },
  initialTab: { type: String, default: 'details' },
});

const emit = defineEmits(['close', 'saved', 'changed']);
const toast = useToast();

const tabs = ['details', 'students', 'courses', 'schedules'];
const tabLabels = { details: 'Detalhes', students: 'Alunos', courses: 'Cursos', schedules: 'Agendamentos' };

const tab = ref(tabs.includes(props.initialTab) ? props.initialTab : 'details');
const form = reactive({ name: '', status: 'active', sort_order: 0 });
const saving = ref(false);
const busyId = ref(null);
const backdropDown = ref(false);

const students = ref([]);
const courses = ref([]);

const editingStudentId = ref(null);
const editExpiry = ref('');
const editStatus = ref('active');

const rowMenu = ref(null);
const rowMenuPos = reactive({ top: 0, left: 0, up: false });
const ROW_MENU_WIDTH = 170;
const ROW_MENU_HEIGHT = 96;

const studentSearch = ref('');
const studentResults = ref([]);
const searchingStudents = ref(false);
const studentExpiry = ref('');
const studentTypeId = ref(null);

const courseSearch = ref('');
const courseResults = ref([]);
const searchingCourses = ref(false);

const schedules = ref([]);
const schedulesLoading = ref(false);
const scheduleBusy = ref(false);
const builder = reactive({ level: 'course', courseId: '', moduleId: '', lessonId: '', availableAt: '', editingId: null });
let schedulesLoaded = false;

const SCHEDULE_LEVELS = { course: 'Curso', module: 'Módulo', lesson: 'Aula' };

function levelLabel(type) {
  return SCHEDULE_LEVELS[type] ?? type;
}

const flatItems = computed(() => {
  const items = [];

  for (const course of schedules.value) {
    items.push({ type: 'course', id: course.id, name: course.name, path: 'Todo o curso', availableAt: course.available_at, courseId: course.id, moduleId: null });

    for (const module of course.modules ?? []) {
      items.push({ type: 'module', id: module.id, name: module.name, path: course.name, availableAt: module.available_at, courseId: course.id, moduleId: module.id });

      for (const lesson of module.lessons ?? []) {
        items.push({ type: 'lesson', id: lesson.id, name: lesson.name, path: `${course.name} › ${module.name}`, availableAt: lesson.available_at, courseId: course.id, moduleId: module.id });
      }
    }
  }

  return items;
});

const scheduledList = computed(() => flatItems.value.filter((item) => item.availableAt));

const builderModules = computed(() => schedules.value.find((c) => c.id === builder.courseId)?.modules ?? []);
const builderLessons = computed(() => builderModules.value.find((m) => m.id === builder.moduleId)?.lessons ?? []);

const courseOptions = computed(() => schedules.value.map((c) => ({ value: c.id, label: c.name })));
const moduleOptions = computed(() => builderModules.value.map((m) => ({ value: m.id, label: m.name })));
const lessonOptions = computed(() => builderLessons.value.map((l) => ({ value: l.id, label: l.name })));

const currentTarget = computed(() => {
  if (builder.level === 'course' && builder.courseId) {
    return { type: 'course', id: builder.courseId };
  }
  if (builder.level === 'module' && builder.moduleId) {
    return { type: 'module', id: builder.moduleId };
  }
  if (builder.level === 'lesson' && builder.lessonId) {
    return { type: 'lesson', id: builder.lessonId };
  }

  return null;
});

const currentTargetKey = computed(() => (currentTarget.value ? `${currentTarget.value.type}${currentTarget.value.id}` : null));
const canSubmitSchedule = computed(() => Boolean(currentTarget.value && builder.availableAt));

let studentTimer = null;
let courseTimer = null;

function initials(name) {
  return (name || '?').trim().split(/\s+/).slice(0, 2).map((p) => p[0]).join('').toUpperCase();
}

const ENROLL_LABELS = { active: 'Ativo', inactive: 'Inativo' };

function enrollLabel(status) {
  return ENROLL_LABELS[status] ?? status ?? '';
}

function isExpired(student) {
  return student.expired_at && new Date(student.expired_at).getTime() < Date.now();
}

function expiryLabel(student) {
  return student.expired_at ? formatDateTime(student.expired_at) : 'Sem expiração';
}

function toggleRowMenu(student, event) {
  if (rowMenu.value?.id === student.id) {
    closeRowMenu();
    return;
  }

  const rect = event.currentTarget.getBoundingClientRect();
  const up = rect.bottom + ROW_MENU_HEIGHT > window.innerHeight;

  rowMenuPos.up = up;
  rowMenuPos.top = up ? rect.top - 6 : rect.bottom + 6;
  rowMenuPos.left = Math.max(8, rect.right - ROW_MENU_WIDTH);
  rowMenu.value = student;
}

function closeRowMenu() {
  rowMenu.value = null;
}

function startEditStudent(student) {
  closeRowMenu();
  editingStudentId.value = student.id;
  editExpiry.value = toDateTimeInput(student.expired_at);
  editStatus.value = student.status ?? 'active';
}

function cancelEditStudent() {
  editingStudentId.value = null;
  editExpiry.value = '';
}

async function saveStudent(student) {
  busyId.value = `s${student.id}`;

  try {
    await api.put(`/admin/teams/${props.team.id}/students/${student.id}`, {
      status: editStatus.value,
      expired_at: editExpiry.value || null,
    });
    editingStudentId.value = null;
    await loadStudents();
    emit('changed');
    toast.success('Vínculo salvo.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível salvar o vínculo.');
  } finally {
    busyId.value = null;
  }
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
    toast.error(e?.message || 'Não foi possível carregar os alunos.');
  }
}

async function loadCourses() {
  try {
    const { data } = await api.get(`/admin/teams/${props.team.id}/courses`);
    courses.value = data.data ?? [];
    const linked = new Set(courses.value.map((c) => c.id));
    courseResults.value = courseResults.value.filter((c) => !linked.has(c.id));
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar os cursos.');
  }
}

async function loadSchedules() {
  schedulesLoading.value = true;

  try {
    const { data } = await api.get(`/admin/teams/${props.team.id}/schedules`);
    schedules.value = data.data ?? [];
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar os agendamentos.');
  } finally {
    schedulesLoading.value = false;
  }
}

function setBuilderLevel(level) {
  builder.level = level;

  if (level === 'course') {
    builder.moduleId = '';
    builder.lessonId = '';
  } else if (level === 'module') {
    builder.lessonId = '';
  }

  builder.editingId = null;
  builder.availableAt = '';
}

function editSchedule(entry) {
  builder.level = entry.type;
  builder.courseId = entry.courseId;
  builder.moduleId = entry.type === 'course' ? '' : entry.moduleId;
  builder.lessonId = entry.type === 'lesson' ? entry.id : '';
  builder.availableAt = toDateTimeInput(entry.availableAt);
  builder.editingId = `${entry.type}${entry.id}`;
}

function cancelScheduleEdit() {
  builder.editingId = null;
  builder.availableAt = '';
}

async function submitSchedule() {
  const target = currentTarget.value;

  if (!target || !builder.availableAt) {
    return;
  }

  scheduleBusy.value = true;

  try {
    await api.put(`/admin/teams/${props.team.id}/schedules`, {
      schedulable_type: target.type,
      schedulable_id: target.id,
      available_at: builder.availableAt,
    });
    await loadSchedules();
    builder.editingId = currentTargetKey.value;
    toast.success('Agendamento salvo.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível salvar o agendamento.');
  } finally {
    scheduleBusy.value = false;
  }
}

async function clearSchedule(entry) {
  scheduleBusy.value = true;

  try {
    await api.put(`/admin/teams/${props.team.id}/schedules`, {
      schedulable_type: entry.type,
      schedulable_id: entry.id,
      available_at: null,
    });

    if (builder.editingId === `${entry.type}${entry.id}`) {
      cancelScheduleEdit();
    }

    await loadSchedules();
    toast.success('Agendamento removido.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível remover o agendamento.');
  } finally {
    scheduleBusy.value = false;
  }
}

watch(() => builder.courseId, () => {
  builder.moduleId = '';
  builder.lessonId = '';
  builder.availableAt = '';
  builder.editingId = null;
}, { flush: 'sync' });

watch(() => builder.moduleId, () => {
  builder.lessonId = '';
  builder.availableAt = '';
  builder.editingId = null;
}, { flush: 'sync' });

watch(currentTargetKey, (key) => {
  if (!key) {
    return;
  }

  const existing = flatItems.value.find((item) => `${item.type}${item.id}` === key);

  if (existing?.availableAt) {
    builder.availableAt = toDateTimeInput(existing.availableAt);
    builder.editingId = key;
  } else {
    builder.availableAt = '';
    builder.editingId = null;
  }
});

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
      toast.error(e?.message || 'Não foi possível buscar alunos.');
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
      toast.error(e?.message || 'Não foi possível buscar cursos.');
    } finally {
      searchingCourses.value = false;
    }
  }, 350);
}

async function saveDetails() {
  if (!form.name.trim()) {
    toast.error('Informe o nome.');
    return;
  }

  saving.value = true;

  try {
    const { data } = await api.put(`/admin/teams/${props.team.id}`, {
      name: form.name,
      status: form.status,
      sort_order: form.sort_order,
    });
    toast.success('Detalhes salvos.');
    emit('saved', { id: props.team.id, data: data.data ?? {} });
  } catch (e) {
    toast.error(e?.message || 'Não foi possível salvar.');
  } finally {
    saving.value = false;
  }
}

async function addStudent(student) {
  busyId.value = `a${student.id}`;

  try {
    await api.post(`/admin/teams/${props.team.id}/students`, {
      user_id: student.id,
      expired_at: studentExpiry.value || null,
    });
    studentSearch.value = '';
    studentResults.value = [];
    await loadStudents();
    emit('changed');
    toast.success('Aluno adicionado.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível adicionar o aluno.');
  } finally {
    busyId.value = null;
  }
}

async function removeStudent(student) {
  closeRowMenu();
  busyId.value = `u${student.id}`;

  try {
    await api.delete(`/admin/teams/${props.team.id}/students/${student.id}`);
    await loadStudents();
    emit('changed');
    toast.success('Aluno removido.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível remover o aluno.');
  } finally {
    busyId.value = null;
  }
}

async function addCourse(course) {
  busyId.value = `ac${course.id}`;

  try {
    await api.post(`/admin/teams/${props.team.id}/courses`, { course_id: course.id });
    courseSearch.value = '';
    courseResults.value = [];
    schedulesLoaded = false;
    await loadCourses();
    emit('changed');
    toast.success('Curso associado.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível associar o curso.');
  } finally {
    busyId.value = null;
  }
}

async function removeCourse(course) {
  busyId.value = `c${course.id}`;

  try {
    await api.delete(`/admin/teams/${props.team.id}/courses/${course.id}`);
    schedulesLoaded = false;
    await loadCourses();
    emit('changed');
    toast.success('Curso desassociado.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível desassociar o curso.');
  } finally {
    busyId.value = null;
  }
}

function onKeydown(e) {
  if (e.key === 'Escape') {
    if (rowMenu.value) {
      closeRowMenu();
      return;
    }

    emit('close');
  }
}

function onBackdropDown(e) {
  backdropDown.value = e.target === e.currentTarget;
}

watch(() => props.team, sync, { immediate: true });

watch(() => props.team?.id, () => {
  tab.value = tabs.includes(props.initialTab) ? props.initialTab : 'details';
  schedulesLoaded = false;
  closeRowMenu();
  cancelEditStudent();
});

watch(tab, (value) => {
  if (value === 'schedules' && !schedulesLoaded) {
    schedulesLoaded = true;
    loadSchedules();
  }
}, { immediate: true });

onMounted(() => {
  ensureStudentType();
  loadStudents();
  loadCourses();
  document.addEventListener('keydown', onKeydown);
  document.addEventListener('click', closeRowMenu);
  window.addEventListener('scroll', closeRowMenu, true);
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
  document.removeEventListener('click', closeRowMenu);
  window.removeEventListener('scroll', closeRowMenu, true);
});
</script>

<style scoped>
.td-list{display:flex;flex-direction:column;gap:8px;}
.td-row{display:flex;align-items:center;gap:10px;padding:9px 11px;background:var(--bg-card-alt);border:1px solid var(--border);border-radius:10px;}
.td-avatar{width:30px;height:30px;border-radius:50%;background:var(--accent-soft);color:var(--accent);font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.td-info{flex:1;min-width:0;display:flex;flex-direction:column;}
.td-name{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.td-sub{font-size:11.5px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

.td-table-wrap{overflow-x:auto;}
.td-table{width:100%;border-collapse:collapse;font-size:12.5px;}
.td-table th{text-align:left;font-size:10.5px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;color:var(--text-muted);padding:0 8px 8px;}
.td-table td{padding:9px 8px;border-top:1px solid var(--border);vertical-align:middle;}
.td-table .td-name{display:block;font-size:12.5px;}
.td-table .td-sub{display:block;}
.td-expiry{font-size:11.5px;color:var(--text-secondary);white-space:nowrap;}
.td-expiry.expired{color:#ef4444;font-weight:700;}
.td-actions{text-align:right;}
.td-kebab{
  display:inline-flex;align-items:center;justify-content:center;width:28px;height:24px;padding:0;border-radius:9px;cursor:pointer;
  background:transparent;border:1.5px solid var(--border);color:var(--text-secondary);
  font-family:inherit;font-size:15px;line-height:1;font-weight:800;transition:border-color .14s ease,color .14s ease;
}
.td-kebab:hover{border-color:var(--accent);color:var(--text-primary);}
.td-edit-row td{background:var(--bg-card-alt);}
.td-editor{display:flex;flex-direction:column;gap:10px;padding:2px 0;}
.td-editor-actions{display:flex;justify-content:flex-end;gap:8px;}
.td-row-menu{
  position:fixed;z-index:130;min-width:170px;padding:6px;background:var(--bg-card);border:1px solid var(--border);
  border-radius:12px;box-shadow:var(--shadow);display:flex;flex-direction:column;gap:2px;
}
.td-row-menu button{
  text-align:left;background:none;border:none;color:var(--text-secondary);font-family:inherit;font-size:12.5px;font-weight:600;
  padding:8px 10px;border-radius:8px;cursor:pointer;white-space:nowrap;
}
.td-row-menu button:hover{background:var(--bg-card-alt);color:var(--text-primary);}
.td-row-menu button.danger{color:#ef4444;}
.td-row-menu button.danger:hover{background:rgba(239,68,68,.12);}

.td-builder{display:flex;flex-direction:column;gap:12px;padding:12px;background:var(--bg-card-alt);border:1px solid var(--border);border-radius:12px;}
.td-seg{display:flex;flex-wrap:wrap;gap:6px;background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:4px;}
.td-seg-btn{
  flex:1 1 auto;border:none;background:none;color:var(--text-muted);font-family:inherit;font-weight:700;font-size:12.5px;
  padding:8px 10px;border-radius:9px;cursor:pointer;transition:background .15s ease,color .15s ease;white-space:nowrap;
}
.td-seg-btn:hover{color:var(--text-primary);}
.td-seg-btn.active{background:var(--accent);color:var(--accent-ink);}
.td-builder-actions{display:flex;justify-content:flex-end;gap:8px;}

.td-sched-badge{flex-shrink:0;font-size:10px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;padding:3px 7px;border-radius:6px;background:var(--accent-soft);color:var(--accent);}
.td-sched-badge.module{background:rgba(59,130,246,.15);color:#3b82f6;}
.td-sched-badge.lesson{background:rgba(148,163,184,.18);color:var(--text-secondary);}
.td-sched-when{font-size:11.5px;color:var(--text-secondary);white-space:nowrap;}
.td-sched-clear{
  display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;flex-shrink:0;padding:0;
  border:1.5px solid var(--border);border-radius:9px;background:transparent;color:var(--text-muted);cursor:pointer;
  font-size:15px;line-height:1;font-family:inherit;transition:border-color .14s ease,color .14s ease;
}
.td-sched-clear:hover{border-color:#ef4444;color:#ef4444;}
</style>
