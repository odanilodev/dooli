<template>
  <div v-if="open" class="admin-modal-backdrop" @click.self="close">
    <div class="panel admin-modal report-drawer">
      <div class="admin-panel-head">
        <div>
          <h2 class="panel-title">{{ user?.name }}</h2>
          <p class="panel-desc">{{ user?.email }}</p>
        </div>
        <button class="admin-modal-close" type="button" @click="close">×</button>
      </div>

      <p v-if="loading" class="admin-muted">Carregando...</p>

      <template v-else>
        <div v-if="!items.length" class="admin-muted">Nenhum curso encontrado para este aluno no escopo atual.</div>

        <div v-for="item in items" :key="`${item.team_id}-${item.course_id}`" class="report-drawer-course">
          <div class="report-drawer-head">
            <strong>{{ item.course_name }}</strong>
            <span class="admin-sub">{{ item.team_name }}</span>
          </div>

          <div class="report-kpis-inline">
            <span v-if="item.is_course_completed" class="status-pill active">Concluído</span>
            <span><b>{{ item.completed }}</b> concluídas</span>
            <span><b>{{ item.in_progress }}</b> em andamento</span>
            <span><b>{{ item.pending }}</b> pendentes</span>
            <span><b>{{ item.blocked }}</b> bloqueadas</span>
            <span><b>{{ item.percentage }}%</b></span>
            <span v-if="item.new_lessons_since_completion > 0"><b>+{{ item.new_lessons_since_completion }}</b> novas</span>
          </div>

          <table class="admin-table report-drawer-table">
            <thead>
              <tr><th>Aula</th><th>Módulo</th><th>Status</th><th>Tempo</th><th>Última atividade</th></tr>
            </thead>
            <tbody>
              <tr v-for="lesson in item.lessons" :key="lesson.id">
                <td>{{ lesson.name }}</td>
                <td class="admin-muted">{{ lesson.module }}</td>
                <td>
                  <span class="status-pill" :class="lessonStatusClass(lesson)">{{ lessonStatusLabel(lesson) }}</span>
                </td>
                <td class="admin-muted">{{ watched(lesson.watched_seconds) }}</td>
                <td class="admin-muted">{{ formatDateTime(lesson.last_activity_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
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
  user: { type: Object, default: null },
  params: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close']);

const items = ref([]);
const loading = ref(false);

watch(() => props.open, async (open) => {
  if (!open || !props.user) {
    return;
  }

  loading.value = true;

  try {
    const { data } = await api.get(`/admin/reports/students/${props.user.id}`, { params: props.params });
    items.value = data.data?.items ?? [];
  } catch (e) {
    items.value = [];
  } finally {
    loading.value = false;
  }
});

function close() {
  emit('close');
}

function lessonStatusClass(lesson) {
  if (lesson.blocked) return 'inactive';
  return lesson.status === 'completed' ? 'active' : 'inactive';
}

function lessonStatusLabel(lesson) {
  if (lesson.blocked) return 'Bloqueada';
  if (lesson.status === 'completed') return 'Concluída';
  if (lesson.status === 'in_progress') return 'Em andamento';
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
.report-drawer{max-width:900px;width:100%;max-height:85vh;overflow:auto;}
.report-drawer-course{margin-top:16px;}
.report-drawer-head{display:flex;flex-direction:column;gap:2px;margin-bottom:8px;}
.report-kpis-inline{display:flex;flex-wrap:wrap;gap:14px;margin-bottom:10px;font-size:12.5px;color:var(--text-secondary);}
.report-drawer-table{font-size:13px;}
</style>
