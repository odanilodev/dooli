import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api } from '../api/http';
import { ICON } from '../data/icons';

const PALETTE = [
  { icon: ICON.trendUp, grad: 'linear-gradient(150deg,#0f3d2e,#22c55e)' },
  { icon: ICON.bolt, grad: 'linear-gradient(150deg,#101826,#1e293b)' },
  { icon: ICON.wave, grad: 'linear-gradient(150deg,#122236,#134e4a)' },
  { icon: ICON.shield, grad: 'linear-gradient(150deg,#3a2f12,#7c5a15)' },
  { icon: ICON.bulb, grad: 'linear-gradient(150deg,#0d1420,#1b2536)' },
  { icon: ICON.options, grad: 'linear-gradient(150deg,#101826,#1e293b)' },
  { icon: ICON.magnifier, grad: 'linear-gradient(150deg,#0f2a3a,#134e4a)' },
  { icon: ICON.candles, grad: 'linear-gradient(150deg,#1e1b3a,#3b1f4a)' },
  { icon: ICON.target, grad: 'linear-gradient(150deg,#1a2e1a,#0f3d2e)' },
];

function statusFromPercentage(pct) {
  if (pct >= 100) {
    return 'concluido';
  }

  if (pct <= 0) {
    return 'nao-iniciado';
  }

  return 'andamento';
}

export const useCoursesStore = defineStore('courses', () => {
  const items = ref([]);
  const meta = ref({});
  const loading = ref(false);
  const loaded = ref(false);
  const error = ref(null);

  const cards = computed(() =>
    items.value.map((course, index) => {
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
      };
    }),
  );

  const completedCount = computed(() => cards.value.filter((c) => c.status === 'concluido').length);

  async function fetchMyCourses(force = false) {
    if (loaded.value && !force) {
      return items.value;
    }

    loading.value = true;
    error.value = null;

    try {
      const { data } = await api.get('/me/courses');
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

  return { items, meta, loading, loaded, error, cards, completedCount, fetchMyCourses };
});
