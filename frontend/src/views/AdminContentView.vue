<template>
  <main class="content">

    <div class="page-header">
      <router-link class="admin-back" :to="{ name: 'admin-conteudo' }">‹ Conteúdo</router-link>

      <div class="admin-head-actions" v-if="course">
        <router-link class="btn btn-outline" :to="{ name: 'curso', params: { courseId: course.id } }">Ver como aluno</router-link>
        <button class="btn btn-outline" @click="openDrawer('course', course.id)">Editar curso</button>
      </div>
    </div>

    <span class="form-error" :class="{ show: error }">{{ error }}</span>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <template v-else-if="course">
      <CurriculumBuilder
        :course="course"
        :selected="selected"
        @select="onSelect"
        @rename="rename"
        @add-module="createModule"
        @attach-module="openAttach"
        @add-topic="createTopic"
        @add-lesson="createLesson"
        @remove="removeNode"
        @reorder="reorder"
      />
    </template>

    <div v-else class="empty-state">
      <strong>Curso não encontrado</strong>
      <span>Ele pode ter sido removido. Volte para a lista de conteúdos.</span>
      <router-link class="btn btn-primary" :to="{ name: 'admin-conteudo' }">‹ Voltar para conteúdos</router-link>
    </div>

    <Transition name="drawer">
      <ItemDrawer
        v-if="selectedItem"
        :type="selected.type"
        :item="selectedItem"
        :course="course"
        @close="selected = null"
        @saved="onSaved"
      />
    </Transition>

    <div v-if="showAttach" class="admin-modal-backdrop" @click.self="showAttach = false">
      <div class="panel admin-modal">
        <div class="admin-panel-head">
          <h2 class="panel-title">Vincular módulo existente</h2>
          <button class="admin-modal-close" @click="showAttach = false">×</button>
        </div>

        <p v-if="attaching" class="page-subtitle">Carregando módulos...</p>
        <p v-else-if="!availableModules.length" class="form-hint">Todos os módulos já estão vinculados a este curso.</p>

        <div v-else class="admin-attach-list">
          <button v-for="m in availableModules" :key="m.id" class="admin-attach-item" @click="attach(m)">
            <span>{{ m.name }}</span>
            <span class="status-pill" :class="m.status">{{ statusLabel(m.status) }}</span>
          </button>
        </div>
      </div>
    </div>

  </main>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../api/http';
import { statusLabel } from '../utils/adminLabels';
import CurriculumBuilder from '../components/admin/CurriculumBuilder.vue';
import ItemDrawer from '../components/admin/ItemDrawer.vue';

const route = useRoute();
const router = useRouter();

const courseId = computed(() => Number(route.params.courseId));
const course = ref(null);
const selected = ref(null);
const loading = ref(true);
const error = ref('');

const showAttach = ref(false);
const attaching = ref(false);
const availableModules = ref([]);

const selectedItem = computed(() => (selected.value ? findNode(selected.value.type, selected.value.id) : null));

function findNode(type, id) {
  if (!course.value) {
    return null;
  }

  if (type === 'course') {
    return course.value;
  }

  for (const module of course.value.modules ?? []) {
    if (type === 'module' && module.id === id) {
      return module;
    }

    for (const topic of module.topics ?? []) {
      if (type === 'topic' && topic.id === id) {
        return topic;
      }

      for (const lesson of topic.lessons ?? []) {
        if (type === 'lesson' && lesson.id === id) {
          return lesson;
        }
      }
    }
  }

  return null;
}

async function load() {
  if (!courseId.value) {
    course.value = null;
    loading.value = false;
    return;
  }

  loading.value = true;
  error.value = '';
  course.value = null;

  try {
    const { data } = await api.get(`/admin/courses/${courseId.value}`);
    course.value = data.data;

    if (route.query.edit === 'course') {
      openDrawer('course', course.value.id);
      router.replace({ name: 'admin-conteudo-curso', params: { courseId: courseId.value } });
    }
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar o curso.';
  } finally {
    loading.value = false;
  }
}

function onSelect(target) {
  selected.value = target;
}

function openDrawer(type, id) {
  selected.value = { type, id };
}

async function onSaved({ type, id, data }) {
  const node = findNode(type, id);

  if (node) {
    Object.assign(node, data);
  }
}

async function createModule() {
  error.value = '';

  try {
    const { data } = await api.post('/admin/modules', { name: 'Novo módulo', status: 'active' });
    await api.post(`/admin/courses/${courseId.value}/modules`, { module_id: data.data.id });
    const module = { ...data.data, topics: [] };
    course.value.modules = [...(course.value.modules ?? []), module];
    openDrawer('module', module.id);
  } catch (e) {
    error.value = e?.message || 'Não foi possível criar o módulo.';
  }
}

async function createTopic(module) {
  error.value = '';

  try {
    const { data } = await api.post('/admin/topics', {
      name: 'Novo tópico',
      module_id: module.id,
      status: 'active',
    });
    const topic = { ...data.data, lessons: [] };
    module.topics = [...(module.topics ?? []), topic];
    openDrawer('topic', topic.id);
  } catch (e) {
    error.value = e?.message || 'Não foi possível criar o tópico.';
  }
}

async function createLesson(topic) {
  error.value = '';

  try {
    const { data } = await api.post('/admin/lessons', {
      name: 'Nova aula',
      topic_id: topic.id,
      status: 'active',
    });
    const lesson = { ...data.data, attachments: data.data.attachments ?? [] };
    topic.lessons = [...(topic.lessons ?? []), lesson];
    openDrawer('lesson', lesson.id);
  } catch (e) {
    error.value = e?.message || 'Não foi possível criar a aula.';
  }
}

async function rename({ type, id, name }) {
  error.value = '';

  const paths = { module: 'modules', topic: 'topics', lesson: 'lessons' };

  try {
    await api.put(`/admin/${paths[type]}/${id}`, { name });
    const node = findNode(type, id);

    if (node) {
      node.name = name;
    }
  } catch (e) {
    error.value = e?.message || 'Não foi possível renomear.';
    await load();
  }
}

async function removeNode({ type, node }) {
  const labels = { module: 'o módulo', topic: 'o tópico', lesson: 'a aula' };

  if (!window.confirm(`Remover ${labels[type]} "${node.name}"?`)) {
    return;
  }

  error.value = '';

  try {
    if (type === 'module') {
      await api.delete(`/admin/courses/${courseId.value}/modules/${node.id}`);
    } else {
      const paths = { topic: 'topics', lesson: 'lessons' };
      await api.delete(`/admin/${paths[type]}/${node.id}`);
    }

    if (type === 'module') {
      course.value.modules = (course.value.modules ?? []).filter((m) => m.id !== node.id);
    } else if (type === 'topic') {
      for (const module of course.value.modules ?? []) {
        module.topics = (module.topics ?? []).filter((t) => t.id !== node.id);
      }
    } else {
      for (const module of course.value.modules ?? []) {
        for (const topic of module.topics ?? []) {
          topic.lessons = (topic.lessons ?? []).filter((l) => l.id !== node.id);
        }
      }
    }

    if (selected.value && selected.value.type === type && selected.value.id === node.id) {
      selected.value = null;
    }
  } catch (e) {
    error.value = e?.message || 'Não foi possível remover o item.';
    await load();
  }
}

async function reorder({ level, parentId, ids }) {
  error.value = '';

  const endpoints = {
    module: `/admin/courses/${parentId}/modules/reorder`,
    topic: `/admin/modules/${parentId}/topics/reorder`,
    lesson: `/admin/topics/${parentId}/lessons/reorder`,
  };

  try {
    await api.put(endpoints[level], { ids });
  } catch (e) {
    error.value = e?.message || 'Não foi possível reordenar.';
    await load();
  }
}

async function openAttach() {
  showAttach.value = true;
  attaching.value = true;

  try {
    const { data } = await api.get('/admin/modules', { params: { per_page: 100 } });
    const linked = new Set((course.value.modules ?? []).map((m) => m.id));
    availableModules.value = (data.data ?? []).filter((m) => !linked.has(m.id));
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar os módulos.';
  } finally {
    attaching.value = false;
  }
}

async function attach(module) {
  error.value = '';

  try {
    await api.post(`/admin/courses/${courseId.value}/modules`, { module_id: module.id });
    showAttach.value = false;
    await load();
    openDrawer('module', module.id);
  } catch (e) {
    error.value = e?.message || 'Não foi possível vincular o módulo.';
  }
}

onMounted(load);

watch(courseId, () => {
  selected.value = null;
  load();
});
</script>

<style scoped>
</style>
