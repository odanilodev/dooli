import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api } from '../api/http';
import { PALETTE } from '../data/palette';
import { formatDuration } from '../utils/format';
import { useStudentTeamsStore } from './studentTeams';

function statusFromPercentage(pct) {
  if (pct >= 100) {
    return 'concluido';
  }

  if (pct <= 0) {
    return 'nao-iniciado';
  }

  return 'andamento';
}

function mapCourse(course, index) {
  const pct = Math.round(Number(course.percentage) || 0);
  const palette = PALETTE[index % PALETTE.length];

  return {
    id: course.id,
    title: course.name,
    count: course.total_lessons ?? 0,
    pct,
    status: statusFromPercentage(pct),
    badge: '',
    icon: palette.icon,
    grad: palette.grad,
    image: course.image_url ?? course.image ?? '',
  };
}

function mapContinue(item, index) {
  const palette = PALETTE[index % PALETTE.length];

  return {
    id: `${item.course_id}-${item.lesson_id}`,
    title: item.course_name,
    sub: item.lesson_name,
    dur: formatDuration(item.duration),
    pct: Math.round(Number(item.percentage) || 0),
    image: item.image ?? '',
    icon: palette.icon,
    grad: palette.grad,
    to: { name: 'aula', params: { courseId: item.course_id, lessonId: item.lesson_id } },
  };
}

export const useCoursesStore = defineStore('courses', () => {
  const items = ref([]);
  const meta = ref({});
  const continueItems = ref([]);
  const homeTeams = ref([]);
  const homeLoaded = ref(false);
  const loading = ref(false);
  const loaded = ref(false);
  const error = ref(null);

  const cards = computed(() => items.value.map(mapCourse));

  const continueCards = computed(() => continueItems.value.map(mapContinue));

  const homeSections = computed(() =>
    homeTeams.value.map((team) => ({
      id: team.id,
      name: team.name,
      sort_order: team.sort_order,
      courses: (team.courses ?? []).map(mapCourse),
      continueCards: (team.continue_watching ?? []).map(mapContinue),
    })),
  );

  const homeCourseIds = computed(
    () => new Set(homeTeams.value.flatMap((team) => (team.courses ?? []).map((course) => course.id))),
  );

  const completedCount = computed(() => cards.value.filter((c) => c.status === 'concluido').length);

  async function fetchMyCourses(force = false) {
    const teams = useStudentTeamsStore();

    await teams.fetchTeams();

    if (loaded.value && !force) {
      return items.value;
    }

    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get('/me/courses', {
        params: { team_id: teams.selectedTeamId || undefined },
      });
      items.value = data.data ?? [];
      meta.value = data.meta ?? {};
      loaded.value = true;

      return items.value;
    } catch (e) {
      error.value = e?.message || 'Não foi possível carregar seus cursos.';
      return [];
    } finally {
      loading.value = false;
    }
  }

  async function fetchContinueWatching() {
    const teams = useStudentTeamsStore();

    await teams.fetchTeams();

    try {
      const { data } = await api.get('/me/continue-watching', {
        params: { team_id: teams.selectedTeamId || undefined },
      });
      continueItems.value = data.data ?? [];

      return continueItems.value;
    } catch (e) {
      continueItems.value = [];
      return [];
    }
  }

  async function fetchHome(force = false) {
    if (homeLoaded.value && !force) {
      return homeTeams.value;
    }

    loading.value = true;

    try {
      const { data } = await api.get('/me/home');
      homeTeams.value = data.data ?? [];
      homeLoaded.value = true;

      return homeTeams.value;
    } catch (e) {
      homeTeams.value = [];
      return [];
    } finally {
      loading.value = false;
    }
  }

  function reset() {
    items.value = [];
    meta.value = {};
    continueItems.value = [];
    homeTeams.value = [];
    homeLoaded.value = false;
    loaded.value = false;
    error.value = null;
  }

  return {
    items,
    meta,
    loading,
    loaded,
    error,
    cards,
    continueItems,
    continueCards,
    homeTeams,
    homeLoaded,
    homeSections,
    homeCourseIds,
    completedCount,
    fetchMyCourses,
    fetchContinueWatching,
    fetchHome,
    reset,
  };
});
