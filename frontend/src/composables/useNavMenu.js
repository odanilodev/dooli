import { reactive } from 'vue';
import { useRoute } from 'vue-router';

const openByDepth = reactive({});

export function useNavMenu() {
  const route = useRoute();

  function isOpen(id, depth) {
    return openByDepth[depth] === id;
  }

  function toggle(id, depth) {
    openByDepth[depth] = openByDepth[depth] === id ? null : id;
  }

  function openPath(pairs) {
    pairs.forEach(([depth, id]) => {
      openByDepth[depth] = id;
    });
  }

  function reset() {
    Object.keys(openByDepth).forEach((key) => {
      delete openByDepth[key];
    });
  }

  function isActive(link) {
    if (!link) {
      return false;
    }

    if (link === '/') {
      return route.path === '/';
    }

    return route.path === link || route.path.startsWith(`${link}/`);
  }

  return { isOpen, toggle, openPath, reset, isActive };
}
