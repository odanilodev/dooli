<template>
  <div class="student-picker">
    <div v-if="modelValue.length" class="student-picker-chips">
      <span v-for="student in modelValue" :key="student.id" class="student-chip">
        {{ student.name }}
        <button type="button" :aria-label="`Remover ${student.name}`" @click="remove(student.id)">×</button>
      </span>
    </div>
    <p v-else class="form-hint">Nenhum aluno selecionado.</p>

    <button type="button" class="btn btn-outline btn-sm" @click="open = true">
      Adicionar aluno
    </button>

    <BulkPickerModal
      :open="open"
      title="Alunos"
      label="Aluno"
      placeholder="Buscar aluno..."
      confirm-label="Adicionar"
      search-endpoint="/admin/users"
      :params="studentParams"
      :map-results="mapResult"
      @close="open = false"
      @confirm="onConfirm"
    />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../../api/http';
import BulkPickerModal from './BulkPickerModal.vue';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const studentTypeId = ref(null);

const studentParams = ref({});

function mapResult(item) {
  return { value: item.id, label: item.name, sub: item.email };
}

function onConfirm(items) {
  const merged = [...props.modelValue];

  items.forEach((item) => {
    if (!merged.some((student) => student.id === item.value)) {
      merged.push({ id: item.value, name: item.label });
    }
  });

  emit('update:modelValue', merged);
}

function remove(id) {
  emit('update:modelValue', props.modelValue.filter((student) => student.id !== id));
}

onMounted(async () => {
  try {
    const { data } = await api.get('/admin/user-types', { params: { code: 'student', per_page: 1 } });
    studentTypeId.value = data.data?.[0]?.id ?? null;
  } catch {
    studentTypeId.value = null;
  }

  studentParams.value = studentTypeId.value ? { user_type_id: studentTypeId.value } : {};
});
</script>

<style scoped>
.student-picker{display:flex;flex-direction:column;gap:8px;align-items:flex-start;}
.student-picker-chips{display:flex;flex-wrap:wrap;gap:6px;}
.student-chip{
  display:inline-flex;align-items:center;gap:6px;padding:4px 6px 4px 10px;border-radius:999px;
  background:var(--accent-soft);color:var(--accent);font-size:12px;font-weight:700;
}
.student-chip button{
  border:none;background:none;color:inherit;cursor:pointer;font-size:14px;line-height:1;
  padding:0 2px;font-family:inherit;
}
</style>
