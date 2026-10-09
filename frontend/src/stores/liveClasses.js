import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api } from '../api/http';

function endTime(item) {
  const start = new Date(item.starts_at).getTime();

  if (Number.isNaN(start)) {
    return null;
  }

  return start + (Number(item.duration) || 0) * 60000;
}

function mapLiveClass(item) {
  const end = endTime(item);
  const now = Date.now();

  return {
    id: item.id,
    title: item.name,
    description: item.description ?? '',
    link: item.link,
    image: item.image_url ?? '',
    startsAt: item.starts_at,
    duration: Number(item.duration) || 0,
    isLive: end !== null && now >= new Date(item.starts_at).getTime() && now <= end,
    isPast: end !== null && now > end,
  };
}

export const useLiveClassesStore = defineStore('liveClasses', () => {
  const items = ref([]);
  const loading = ref(false);
  const loaded = ref(false);

  const upcoming = computed(() => items.value.filter((item) => !item.isPast));
  const past = computed(() => items.value.filter((item) => item.isPast));

  async function fetch(force = false) {
    if (loaded.value && !force) {
      return items.value;
    }

    loading.value = true;

    try {
      const { data } = await api.get('/me/live-classes');
      items.value = (data.data ?? []).map(mapLiveClass);
      loaded.value = true;

      return items.value;
    } catch (e) {
      items.value = [];

      return [];
    } finally {
      loading.value = false;
    }
  }

  function reset() {
    items.value = [];
    loaded.value = false;
  }

  return { items, loading, loaded, upcoming, past, fetch, reset };
});
