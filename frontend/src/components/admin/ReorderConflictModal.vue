<template>
  <div class="admin-modal-backdrop" @mousedown="onBackdropDown" @click.self="backdropDown && emit('cancel')">
    <div class="panel admin-modal">
      <div class="admin-panel-head">
        <h2 class="panel-title">Travas de pré-requisito vão ser removidas</h2>
        <button class="admin-modal-close" @click="emit('cancel')">×</button>
      </div>

      <p class="page-subtitle">Com esta nova ordem, as aulas abaixo ficariam antes da aula que exigem concluir. Para não travar o aluno, a trava será removida:</p>

      <ul class="admin-conflict-list">
        <li v-for="conflict in conflicts" :key="conflict.lesson_id">
          <strong>{{ conflict.lesson_name }}</strong> — exigia <em>{{ conflict.required_lesson_name }}</em>
        </li>
      </ul>

      <div class="admin-modal-actions">
        <button class="btn btn-outline" @click="emit('cancel')">Cancelar</button>
        <button class="btn btn-danger" @click="emit('confirm')">Reordenar e remover travas</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
  conflicts: { type: Array, default: () => [] },
});

const emit = defineEmits(['cancel', 'confirm']);

const backdropDown = ref(false);

function onBackdropDown(e) {
  backdropDown.value = e.target === e.currentTarget;
}
</script>
