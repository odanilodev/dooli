import { defineStore } from 'pinia';
import { computed, reactive, ref } from 'vue';
import { api } from '../api/http';

export const REPORT_LEVELS = ['team', 'course', 'module', 'lesson', 'student'];

export const useReportsStore = defineStore('reports', () => {
  const filters = reactive({
    team_id: null,
    course_id: null,
    module_id: null,
    lesson_id: null,
    students: [],
    student_status: '',
    search: '',
  });

  const labels = reactive({
    team_id: '',
    course_id: '',
    module_id: '',
    lesson_id: '',
  });

  const groupBy = ref('team');
  const items = ref([]);
  const totals = ref({});
  const meta = ref({ current_page: 1, last_page: 1, total: 0 });
  const loading = ref(false);
  const page = ref(1);

  const crumbs = computed(() => REPORT_LEVELS
    .filter((level) => level !== 'student')
    .filter((level) => filters[`${level}_id`])
    .map((level) => ({ key: `${level}_id`, level, id: filters[`${level}_id`], name: labels[`${level}_id`] })));

  function params() {
    return {
      group_by: groupBy.value,
      team_id: filters.team_id || undefined,
      course_id: filters.course_id || undefined,
      module_id: filters.module_id || undefined,
      lesson_id: filters.lesson_id || undefined,
      user_ids: filters.students.length ? filters.students.map((s) => s.id) : undefined,
      student_status: filters.student_status || undefined,
      search: filters.search || undefined,
      page: page.value,
      per_page: 20,
    };
  }

  async function load() {
    loading.value = true;

    try {
      const { data } = await api.get('/admin/reports/progress', { params: params() });
      items.value = data.data ?? [];
      meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };
      totals.value = data.meta?.totals ?? {};
    } catch (e) {
      items.value = [];
      totals.value = {};
    } finally {
      loading.value = false;
    }
  }

  function setGroupBy(level) {
    groupBy.value = level;
    page.value = 1;
    load();
  }

  /**
   * Drills one level down from a group row.
   */
  function drill(row) {
    const current = groupBy.value;
    const index = REPORT_LEVELS.indexOf(current);

    if (current !== 'student') {
      filters[`${current}_id`] = row.id;
      labels[`${current}_id`] = row.name;
      groupBy.value = REPORT_LEVELS[index + 1];
    }

    page.value = 1;
    load();
  }

  /**
   * Sets an explicit filter and clears every deeper one.
   */
  function setFilter(level, value, label = '') {
    const index = REPORT_LEVELS.indexOf(level);
    const deeper = REPORT_LEVELS.slice(index + 1).filter((l) => l !== 'student');

    filters[`${level}_id`] = value || null;
    labels[`${level}_id`] = label;

    deeper.forEach((l) => {
      filters[`${l}_id`] = null;
      labels[`${l}_id`] = '';
    });

    groupBy.value = REPORT_LEVELS[index + 1] ?? 'student';
    page.value = 1;
    load();
  }

  function jumpTo(crumb) {
    const index = REPORT_LEVELS.indexOf(crumb.level);

    REPORT_LEVELS.slice(index).filter((l) => l !== 'student').forEach((l) => {
      filters[`${l}_id`] = null;
      labels[`${l}_id`] = '';
    });

    groupBy.value = crumb.level;
    page.value = 1;
    load();
  }

  function setStudents(list) {
    filters.students = list ?? [];
    page.value = 1;
    load();
  }

  function setStatus(value) {
    filters.student_status = value;
    page.value = 1;
    load();
  }

  function setSearch(value) {
    filters.search = value;
    page.value = 1;
    load();
  }

  function goPage(value) {
    page.value = value;
    load();
  }

  function reset() {
    Object.assign(filters, {
      team_id: null, course_id: null, module_id: null, lesson_id: null, students: [], student_status: '', search: '',
    });
    Object.assign(labels, { team_id: '', course_id: '', module_id: '', lesson_id: '' });
    groupBy.value = 'team';
    items.value = [];
    totals.value = {};
    meta.value = { current_page: 1, last_page: 1, total: 0 };
    page.value = 1;
  }

  async function exportFile() {
    const { data } = await api.get('/admin/reports/export', {
      params: params(),
      responseType: 'blob',
      headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
    });

    const url = URL.createObjectURL(new Blob([data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `relatorio-progresso-${groupBy.value}.xlsx`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  }

  return {
    filters, labels, groupBy, items, totals, meta, loading, page, crumbs,
    load, setGroupBy, drill, setFilter, jumpTo, setStudents, setStatus, setSearch, goPage, reset, exportFile,
  };
});
