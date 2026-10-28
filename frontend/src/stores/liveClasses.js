import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api } from '../api/http';

function startTime(item) {
  return new Date(item.startsAt ?? item.starts_at).getTime();
}

function endTime(item) {
  const start = startTime(item);

  if (Number.isNaN(start)) {
    return null;
  }

  return start + (Number(item.duration) || 0) * 60000;
}

function mapLiveClass(item) {
  return {
    id: item.id,
    title: item.name,
    description: item.description ?? '',
    link: item.link,
    image: item.image_url ?? '',
    startsAt: item.starts_at,
    duration: Number(item.duration) || 0,
  };
}

export const useLiveClassesStore = defineStore('liveClasses', () => {
  const items = ref([]);
  const loading = ref(false);
  const loaded = ref(false);
  const now = ref(Date.now());
  let clock = null;

  function isLive(item) {
    const start = startTime(item);
    const end = endTime(item);

    return !Number.isNaN(start) && end !== null && now.value >= start && now.value <= end;
  }

  function isPast(item) {
    const end = endTime(item);

    return end !== null && now.value > end;
  }

  const upcoming = computed(() => items.value.filter((item) => !isPast(item)));
  const liveNow = computed(() => items.value.filter(isLive));
  const hasLiveNow = computed(() => liveNow.value.length > 0);

  function startClock() {
    if (clock !== null) {
      return;
    }

    clock = window.setInterval(() => {
      now.value = Date.now();
    }, 30000);
  }

  function stopClock() {
    if (clock === null) {
      return;
    }

    window.clearInterval(clock);
    clock = null;
  }

  async function fetch(force = false) {
    if (loaded.value && !force) {
      return items.value;
    }

    loading.value = true;

    try {
      const { data } = await api.get('/me/live-classes');
      items.value = (data.data ?? []).map(mapLiveClass);
      now.value = Date.now();
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
    stopClock();
  }

  return {
    items,
    loading,
    loaded,
    now,
    upcoming,
    liveNow,
    hasLiveNow,
    isLive,
    startClock,
    stopClock,
    fetch,
    reset,
  };
});
