<template>
  <form class="panel admin-panel" @submit.prevent="save">
    <div class="admin-panel-head">
      <h2 class="panel-title">Curso</h2>
      <div class="admin-panel-actions">
        <button type="button" class="btn btn-outline btn-sm" :disabled="busy" @click="togglePublish">
          {{ published ? 'Despublicar' : 'Publicar' }}
        </button>
        <button type="submit" class="btn btn-primary btn-sm" :disabled="saving">
          {{ saving ? 'Salvando...' : 'Salvar' }}
        </button>
      </div>
    </div>

    <div class="form-success" :class="{ show: saved }">Curso salvo.</div>
    <span class="form-error" :class="{ show: error }">{{ error }}</span>

    <div class="form-group">
      <label for="cp-name">Nome</label>
      <input id="cp-name" class="form-control" v-model="form.name" required>
    </div>

    <div class="form-group">
      <label for="cp-description">Descrição</label>
      <textarea id="cp-description" class="form-control" rows="3" v-model="form.description"></textarea>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="cp-image">Imagem (url)</label>
        <input id="cp-image" class="form-control" v-model="form.image">
      </div>
      <div class="form-group">
        <label for="cp-status">Status</label>
        <select id="cp-status" class="form-control" v-model="form.status">
          <option value="active">active</option>
          <option value="inactive">inactive</option>
        </select>
      </div>
      <div class="form-group">
        <label for="cp-sort">Ordem</label>
        <input id="cp-sort" class="form-control" type="number" min="0" v-model.number="form.sort_order">
      </div>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { api } from '../../api/http';

const props = defineProps({ course: { type: Object, required: true } });
const emit = defineEmits(['changed']);

const form = reactive({ name: '', description: '', image: '', status: 'active', sort_order: 0 });
const saving = ref(false);
const saved = ref(false);
const error = ref('');
const busy = ref(false);
const published = ref(true);

function sync() {
  form.name = props.course.name ?? '';
  form.description = props.course.description ?? '';
  form.image = props.course.image ?? '';
  form.status = props.course.status ?? 'active';
  form.sort_order = props.course.sort_order ?? 0;
  published.value = props.course.status === 'active';
}

async function save() {
  saving.value = true;
  error.value = '';

  try {
    await api.put(`/admin/courses/${props.course.id}`, { ...form });
    saved.value = true;
    setTimeout(() => { saved.value = false; }, 2000);
    emit('changed');
  } catch (e) {
    error.value = e?.message || 'Não foi possível salvar o curso.';
  } finally {
    saving.value = false;
  }
}

async function togglePublish() {
  busy.value = true;

  try {
    await api.post(`/admin/courses/${props.course.id}/${published.value ? 'unpublish' : 'publish'}`);
    emit('changed');
  } catch (e) {
    error.value = e?.message || 'Não foi possível alterar o status.';
  } finally {
    busy.value = false;
  }
}

watch(() => props.course, sync, { immediate: true });
</script>
