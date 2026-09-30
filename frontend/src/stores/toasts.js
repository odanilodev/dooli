import { defineStore } from 'pinia';
import { ref } from 'vue';

const DEFAULT_TIMEOUTS = {
  success: 3500,
  info: 4000,
  warning: 4500,
  error: 5000,
};

export const useToastStore = defineStore('toasts', () => {
  const items = ref([]);
  const timers = new Map();
  let sequence = 0;

  function push({ type = 'info', message, timeout } = {}) {
    if (!message) {
      return null;
    }

    const duration = timeout ?? DEFAULT_TIMEOUTS[type] ?? 4000;
    const toast = {
      id: ++sequence,
      type,
      message,
      duration,
      remaining: duration,
      startedAt: 0,
    };

    items.value = [...items.value, toast];

    if (duration > 0) {
      schedule(toast);
    }

    return toast.id;
  }

  function schedule(toast) {
    toast.startedAt = Date.now();

    timers.set(toast.id, setTimeout(() => dismiss(toast.id), toast.remaining));
  }

  function dismiss(id) {
    const timer = timers.get(id);

    if (timer) {
      clearTimeout(timer);
      timers.delete(id);
    }

    items.value = items.value.filter((toast) => toast.id !== id);
  }

  function pause(id) {
    const toast = items.value.find((item) => item.id === id);

    if (!toast || toast.duration <= 0) {
      return;
    }

    const timer = timers.get(id);

    if (timer) {
      clearTimeout(timer);
      timers.delete(id);
    }

    toast.remaining = Math.max(0, toast.remaining - (Date.now() - toast.startedAt));
    toast.paused = true;
  }

  function resume(id) {
    const toast = items.value.find((item) => item.id === id);

    if (!toast || toast.duration <= 0 || !toast.paused) {
      return;
    }

    toast.paused = false;
    schedule(toast);
  }

  function success(message, options = {}) {
    return push({ ...options, type: 'success', message });
  }

  function error(message, options = {}) {
    return push({ ...options, type: 'error', message });
  }

  function info(message, options = {}) {
    return push({ ...options, type: 'info', message });
  }

  function warning(message, options = {}) {
    return push({ ...options, type: 'warning', message });
  }

  function clear() {
    timers.forEach((timer) => clearTimeout(timer));
    timers.clear();
    items.value = [];
  }

  return { items, push, dismiss, pause, resume, success, error, info, warning, clear };
});
