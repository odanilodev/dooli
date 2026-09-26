<template>
  <form class="panel admin-panel" @submit.prevent="save">
    <div class="admin-panel-head">
      <h2 class="panel-title">Módulo</h2>
      <button type="submit" class="btn btn-primary btn-sm" :disabled="saving">
        {{ saving ? 'Salvando...' : 'Salvar' }}
      </button>
    </div>

    <div class="form-success" :class="{ show: saved }">Módulo salvo.</div>
    <span class="form-error" :class="{ show: error }">{{ error }}</span>

    <div class="form-group">
      <label for="mp-name">Nome</label>
      <input id="mp-name" class="form-control" v-model="form.name" required>
    </div>

    <div class="form-group">
      <label for="mp-description">Descrição</label>
      <textarea id="mp-description" class="form-control" rows="2" v-model="form.description"></textarea>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="mp-image">Imagem (url)</label>
        <input id="mp-image" class="form-control" v-model="form.image">
      </div>
      <div class="form-group">
        <label for="mp-status">Status</label>
        <select id="mp-status" class="form-control" v-model="form.status">
          <option value="active">active</option>
          <option value="inactive">inactive</option>
        </select>
      </div>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { api } from '../../api/http';

const props = defineProps({ module: { type: Object, required: true } });
const emit = defineEmits(['changed']);

const form = reactive({ name: '', description: '', image: '', status: 'active' });
const saving = ref(false);
const saved = ref(false);
const error = ref('');

function sync() {
  form.name = props.module.name ?? '';
  form.description = props.module.description ?? '';
  form.image = props.module.image ?? '';
  form.status = props.module.status ?? 'active';
}

async function save() {
  saving.value = true;
  error.value = '';

  try {
    await api.put(`/admin/modules/${props.module.id}`, { ...form });
    saved.value = true;
    setTimeout(() => { saved.value = false; }, 2000);
    emit('changed');
  } catch (e) {
    error.value = e?.message || 'Não foi possível salvar o módulo.';
  } finally {
    saving.value = false;
  }
}

watch(() => props.module, sync, { immediate: true });
</script>
