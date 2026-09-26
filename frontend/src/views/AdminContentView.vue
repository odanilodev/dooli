<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Conteúdo</h1>
        <p class="page-subtitle">Cursos, módulos, tópicos e aulas em uma tela só.</p>
      </div>
      <div class="admin-head-actions">
        <select class="form-control admin-course-select" :value="courseId" @change="selectCourse(Number($event.target.value))">
          <option v-if="!courses.length" :value="null">Nenhum curso</option>
          <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <button class="btn btn-primary" @click="createCourse">+ Novo curso</button>
      </div>
    </div>

    <span class="form-error" :class="{ show: error }">{{ error }}</span>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <div v-else-if="course" class="admin-layout">
      <section class="panel admin-tree-panel">
        <ContentTree
          :course="course"
          :selected="selected"
          @select="selected = $event"
          @create-module="createModule"
          @attach-module="openAttach"
          @create-topic="createTopic"
          @create-lesson="createLesson"
          @remove-node="removeNode"
          @reorder="reorder"
        />
      </section>

      <section class="admin-detail">
        <CoursePanel v-if="selected?.type === 'course'" :course="course" @changed="reload" />
        <ModulePanel v-else-if="selected?.type === 'module' && selectedNode" :module="selectedNode" @changed="reload" />
        <TopicPanel v-else-if="selected?.type === 'topic' && selectedNode" :topic="selectedNode" @changed="reload" />
        <LessonPanel v-else-if="selected?.type === 'lesson' && selectedNode" :lesson="selectedNode" @changed="reload" />
        <div v-else class="panel">
          <div class="empty-state">
            <strong>Selecione um item</strong>
            <span>Clique em um curso, módulo, tópico ou aula na árvore para editar.</span>
          </div>
        </div>
      </section>
    </div>

    <div v-else class="empty-state">
      <strong>Nenhum curso</strong>
      <span>Crie o primeiro curso para começar.</span>
    </div>

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
            <span>#{{ m.id }} · {{ m.name }}</span>
            <span class="status-pill" :class="m.status">{{ m.status }}</span>
          </button>
        </div>
      </div>
    </div>

  </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { api } from '../api/http';
import ContentTree from '../components/admin/ContentTree.vue';
import CoursePanel from '../components/admin/CoursePanel.vue';
import ModulePanel from '../components/admin/ModulePanel.vue';
import TopicPanel from '../components/admin/TopicPanel.vue';
import LessonPanel from '../components/admin/LessonPanel.vue';

const courses = ref([]);
const courseId = ref(null);
const course = ref(null);
const selected = ref(null);
const loading = ref(true);
const error = ref('');

const showAttach = ref(false);
const attaching = ref(false);
const availableModules = ref([]);

const selectedNode = computed(() => {
  if (!course.value || !selected.value) {
    return null;
  }

  const { type, id } = selected.value;

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
});

async function loadCourses() {
  try {
    const { data } = await api.get('/admin/courses', { params: { per_page: 100 } });
    courses.value = data.data ?? [];
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar os cursos.';
  }
}

async function loadCourse() {
  if (!courseId.value) {
    course.value = null;
    return;
  }

  try {
    const { data } = await api.get(`/admin/courses/${courseId.value}`);
    course.value = data.data;

    if (!selected.value || selected.value.type === 'course') {
      selected.value = { type: 'course', id: course.value.id };
    } else if (!selectedNode.value) {
      selected.value = { type: 'course', id: course.value.id };
    }
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar o curso.';
    course.value = null;
  }
}

async function reload() {
  await Promise.all([loadCourse(), loadCourses()]);
}

async function selectCourse(id) {
  courseId.value = id;
  selected.value = id ? { type: 'course', id } : null;
  loading.value = true;
  await loadCourse();
  loading.value = false;
}

async function createCourse() {
  error.value = '';

  try {
    const { data } = await api.post('/admin/courses', { name: 'Novo curso', status: 'active' });
    await loadCourses();
    await selectCourse(data.data.id);
  } catch (e) {
    error.value = e?.message || 'Não foi possível criar o curso.';
  }
}

async function createModule() {
  error.value = '';

  try {
    const { data } = await api.post('/admin/modules', { name: 'Novo módulo', status: 'active' });
    await api.post(`/admin/courses/${courseId.value}/modules`, { module_id: data.data.id });
    await loadCourse();
    selected.value = { type: 'module', id: data.data.id };
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
    await loadCourse();
    selected.value = { type: 'topic', id: data.data.id };
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
    await loadCourse();
    selected.value = { type: 'lesson', id: data.data.id };
  } catch (e) {
    error.value = e?.message || 'Não foi possível criar a aula.';
  }
}

async function removeNode({ type, node }) {
  if (!window.confirm(`Remover ${label(type)} "${node.name}"?`)) {
    return;
  }

  error.value = '';

  try {
    if (type === 'module') {
      await api.delete(`/admin/courses/${courseId.value}/modules/${node.id}`);
    } else if (type === 'topic') {
      await api.delete(`/admin/topics/${node.id}`);
    } else if (type === 'lesson') {
      await api.delete(`/admin/lessons/${node.id}`);
    }

    if (selected.value && selected.value.id === node.id && selected.value.type === type) {
      selected.value = { type: 'course', id: course.value.id };
    }

    await loadCourse();
  } catch (e) {
    error.value = e?.message || 'Não foi possível remover o item.';
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
    await loadCourse();
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
    await loadCourse();
    selected.value = { type: 'module', id: module.id };
  } catch (e) {
    error.value = e?.message || 'Não foi possível vincular o módulo.';
  }
}

function label(type) {
  return { module: 'o módulo', topic: 'o tópico', lesson: 'a aula' }[type] || 'o item';
}

onMounted(async () => {
  loading.value = true;
  await loadCourses();

  if (courses.value.length) {
    await selectCourse(courses.value[0].id);
  } else {
    loading.value = false;
  }
});
</script>

<style scoped>
.admin-course-select{max-width:260px;}
</style>
