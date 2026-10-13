<template>
  <div v-if="open" class="admin-modal-backdrop" @click.self="close">
    <div class="panel admin-modal report-drawer">
      <div class="admin-panel-head">
        <div>
          <h2 class="panel-title">{{ lesson?.name }}</h2>
          <p class="panel-desc">{{ lesson?.module_name }}</p>
        </div>
        <button class="admin-modal-close" type="button" @click="close">×</button>
      </div>

      <p v-if="loading" class="admin-muted">Carregando...</p>

      <template v-else>
        <div v-if="!students.length" class="admin-muted">Nenhum aluno no escopo atual.</div>

        <table v-else class="admin-table report-drawer-table">
          <thead>
            <tr><th>Aluno</th><th>E-mail</th><th>Status</th><th>Tempo</th><th>Última atividade</th></tr>
          </thead>
          <tbody>
            <tr v-for="student in students" :key="student.user_id">
              <td>{{ student.name }}</td>
              <td class="admin-muted">{{ student.email }}</td>
              <td><span class="status-pill" :class="statusClass(student)">{{ statusLabel(student) }}</span></td>
              <td class="admin-muted">{{ watched(student.watched_seconds) }}</td>
              <td class="admin-muted">{{ formatDateTime(student.last_activity_at) }}</td>
            </tr>
          </tbody>
        </table>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { api } from '../../api/http';
import { formatDateTime } from '../../utils/format';

const props = defineProps({
  open: { type: Boolean, default: false },
  lesson: { type: Object, default: null },
  params: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close']);

const students = ref([]);
const loading = ref(false);

watch(() => props.open, async (open) => {
  if (!open || !props.lesson) {
    return;
  }

  loading.value = true;

  try {
    const { data } = await api.get(`/admin/reports/lessons/${props.lesson.id}`, { params: props.params });
    students.value = data.data?.students ?? [];
  } catch (e) {
    students.value = [];
  } finally {
    loading.value = false;
  }
});

function close() {
  emit('close');
}

function statusClass(student) {
  if (student.blocked) return 'inactive';
  return student.status === 'completed' ? 'active' : 'inactive';
}

function statusLabel(student) {
  if (student.blocked) return 'Bloqueada';
  if (student.status === 'completed') return 'Concluída';
  if (student.status === 'in_progress') return 'Em andamento';
  return 'Pendente';
}

function watched(seconds) {
  if (!seconds) return '—';
  const min = Math.floor(seconds / 60);
  const sec = seconds % 60;
  return `${min}m ${sec}s`;
}
</script>

<style scoped>
.report-drawer{max-width:820px;width:100%;max-height:85vh;overflow:auto;}
.report-drawer-table{font-size:13px;}
</style>
