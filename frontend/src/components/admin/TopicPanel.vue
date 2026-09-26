<template>
  <form class="panel admin-panel" @submit.prevent="save">
    <div class="admin-panel-head">
      <h2 class="panel-title">Tópico</h2>
      <button type="submit" class="btn btn-primary btn-sm" :disabled="saving">
        {{ saving ? 'Salvando...' : 'Salvar' }}
      </button>
    </div>

    <div class="form-success" :class="{ show: saved }">Tópico salvo.</div>
    <span class="form-error" :class="{ show: error }">{{ error }}</span>

    <div class="form-group">
      <label for="tp-name">Nome</label>
      <input id="tp-name" class="form-control" v-model="form.name" required>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="tp-status">Status</label>
        <select id="tp-status" class="form-control" v-model="form.status">
          <option value="active">active</option>
          <option value="inactive">inactive</option>
        </select>
      </div>
      <div class="form-group">
        <label for="tp-sort">Ordem</label>
        <input id="tp-sort" class="form-control" type="number" min="0" v-model.number="form.sort_order">
      </div>
    </div>

    <p class="form-hint">Módulo: {{ moduleName }} — use o arrastar na árvore para mover entre módulos.</p>
  </form>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { api } from '../../api/http';

const props = defineProps({ topic: { type: Object, required: true } });
const emit = defineEmits(['changed']);

const form = reactive({ name: '', status: 'active', sort_order: 0 });
const saving = ref(false);
const saved = ref(false);
const error = ref('');

const moduleName = computed(() => props.topic.module?.name || `Módulo #${props.topic.module_id}`);

function sync() {
  form.name = props.topic.name ?? '';
  form.status = props.topic.status ?? 'active';
  form.sort_order = props.topic.sort_order ?? 0;
}

async function save() {
  saving.value = true;
  error.value = '';

  try {
    await api.put(`/admin/topics/${props.topic.id}`, { ...form });
    saved.value = true;
    setTimeout(() => { saved.value = false; }, 2000);
    emit('changed');
  } catch (e) {
    error.value = e?.message || 'Não foi possível salvar o tópico.';
  } finally {
    saving.value = false;
  }
}

watch(() => props.topic, sync, { immediate: true });
</script>
