import { computed, ref } from 'vue';

export function useBulkSelection() {
  const selectedIds = ref([]);

  const count = computed(() => selectedIds.value.length);

  function isSelected(id) {
    return selectedIds.value.includes(id);
  }

  function toggle(id) {
    selectedIds.value = isSelected(id)
      ? selectedIds.value.filter((value) => value !== id)
      : [...selectedIds.value, id];
  }

  function toggleAll(ids, checked) {
    const set = new Set(selectedIds.value);

    for (const id of ids) {
      if (checked) {
        set.add(id);
      } else {
        set.delete(id);
      }
    }

    selectedIds.value = [...set];
  }

  function isAllSelected(ids) {
    return ids.length > 0 && ids.every((id) => selectedIds.value.includes(id));
  }

  function isPartial(ids) {
    const selected = ids.filter((id) => selectedIds.value.includes(id)).length;

    return selected > 0 && selected < ids.length;
  }

  function clear() {
    selectedIds.value = [];
  }

  return { selectedIds, count, isSelected, toggle, toggleAll, isAllSelected, isPartial, clear };
}
