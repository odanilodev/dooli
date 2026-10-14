import { ref } from 'vue';
import { api } from '../api/http';
import { useToast } from './useToast';

const PATHS = { module: 'modules', topic: 'topics', lesson: 'lessons' };

export function useCurriculumModules({
  modules,
  root = null,
  courseId = null,
  attachModuleToCourse = false,
  openDrawer = null,
  reload = null,
}) {
  const toast = useToast();
  const reorderConflicts = ref([]);
  const pendingReorder = ref(null);
  const removeConfirm = ref(null);

  function getModules() {
    return modules?.value ?? [];
  }

  function setModules(value) {
    if (modules) {
      modules.value = value;
    }
  }

  function getRoot() {
    return root?.value ?? null;
  }

  function getCourseId() {
    return courseId?.value ?? null;
  }

  function findNode(type, id) {
    if (type === 'course') {
      return getRoot();
    }

    for (const module of getModules()) {
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

      for (const lesson of module.lessons ?? []) {
        if (type === 'lesson' && lesson.id === id) {
          return lesson;
        }
      }
    }

    return null;
  }

  async function createModule() {
    try {
      const { data } = await api.post('/admin/modules', { name: 'Novo módulo', status: 'active' });

      if (attachModuleToCourse && getCourseId()) {
        await api.post(`/admin/courses/${getCourseId()}/modules`, { module_id: data.data.id });
      }

      const module = { ...data.data, topics: [], lessons: [] };
      setModules([...getModules(), module]);
      openDrawer?.('module', module.id);
    } catch (e) {
      toast.error(e?.message || 'Não foi possível criar o módulo.');
    }
  }

  async function createTopic(module) {
    try {
      const { data } = await api.post('/admin/topics', {
        name: 'Novo tópico',
        module_id: module.id,
        status: 'active',
      });

      const topic = { ...data.data, lessons: [] };
      module.topics = [...(module.topics ?? []), topic];
      openDrawer?.('topic', topic.id);
    } catch (e) {
      toast.error(e?.message || 'Não foi possível criar o tópico.');
    }
  }

  async function createLesson(topic) {
    try {
      const { data } = await api.post('/admin/lessons', {
        name: 'Nova aula',
        module_id: topic.module_id,
        topic_id: topic.id,
        status: 'active',
      });

      const lesson = { ...data.data, attachments: data.data.attachments ?? [] };
      topic.lessons = [...(topic.lessons ?? []), lesson];
      openDrawer?.('lesson', lesson.id);
    } catch (e) {
      toast.error(e?.message || 'Não foi possível criar a aula.');
    }
  }

  async function createModuleLesson(module) {
    try {
      const { data } = await api.post('/admin/lessons', {
        name: 'Nova aula',
        module_id: module.id,
        status: 'active',
      });

      const lesson = { ...data.data, attachments: data.data.attachments ?? [] };
      module.lessons = [...(module.lessons ?? []), lesson];
      openDrawer?.('lesson', lesson.id);
    } catch (e) {
      toast.error(e?.message || 'Não foi possível criar a aula.');
    }
  }

  async function rename({ type, id, name }) {
    try {
      await api.put(`/admin/${PATHS[type]}/${id}`, { name });
      const node = findNode(type, id);

      if (node) {
        node.name = name;
      }
    } catch (e) {
      toast.error(e?.message || 'Não foi possível renomear.');
      await reload?.();
    }
  }

  function removeNode({ type, node }) {
    removeConfirm.value = buildRemoveConfirm(type, node);
  }

  function buildRemoveConfirm(type, node) {
    if (type === 'module' && getCourseId()) {
      return {
        type,
        node,
        title: 'Remover módulo do curso',
        message: `O módulo "${node.name}" será desvinculado deste curso. O módulo e suas aulas não serão apagados.`,
        confirmLabel: 'Desvincular',
        variant: 'primary',
        courses: [],
      };
    }

    if (type === 'module') {
      const courses = node.courses ?? [];

      return {
        type,
        node,
        title: 'Excluir módulo',
        message: courses.length
          ? `Tem certeza que deseja excluir o módulo "${node.name}"? As aulas dele (com progresso, comentários e avaliações dos alunos) também serão apagadas. Ele está vinculado aos cursos abaixo, que ficarão sem este módulo.`
          : `Tem certeza que deseja excluir o módulo "${node.name}"? As aulas dele (com progresso, comentários e avaliações dos alunos) também serão apagadas.`,
        confirmLabel: 'Excluir',
        variant: 'danger',
        courses,
      };
    }

    if (type === 'topic') {
      return {
        type,
        node,
        title: 'Excluir tópico',
        message: `Tem certeza que deseja excluir o tópico "${node.name}"? As aulas dele (com progresso, comentários e avaliações dos alunos) também serão apagadas.`,
        confirmLabel: 'Excluir',
        variant: 'danger',
        courses: [],
      };
    }

    return {
      type,
      node,
      title: 'Excluir aula',
      message: `Tem certeza que deseja excluir a aula "${node.name}"? Isso também apaga progresso, comentários e avaliações dos alunos nesta aula.`,
      confirmLabel: 'Excluir',
      variant: 'danger',
      courses: [],
    };
  }

  async function confirmRemove() {
    const pending = removeConfirm.value;
    removeConfirm.value = null;

    if (pending) {
      await performRemove(pending);
    }
  }

  function cancelRemove() {
    removeConfirm.value = null;
  }

  async function performRemove({ type, node }) {
    try {
      if (type === 'module' && getCourseId()) {
        await api.delete(`/admin/courses/${getCourseId()}/modules/${node.id}`);
      } else {
        await api.delete(`/admin/${PATHS[type]}/${node.id}`);
      }

      if (type === 'module') {
        setModules(getModules().filter((m) => m.id !== node.id));
      } else if (type === 'topic') {
        for (const module of getModules()) {
          module.topics = (module.topics ?? []).filter((t) => t.id !== node.id);
        }
      } else {
        for (const module of getModules()) {
          module.lessons = (module.lessons ?? []).filter((l) => l.id !== node.id);

          for (const topic of module.topics ?? []) {
            topic.lessons = (topic.lessons ?? []).filter((l) => l.id !== node.id);
          }
        }
      }
    } catch (e) {
      toast.error(e?.message || 'Não foi possível remover o item.');
      await reload?.();
    }
  }

  async function reorder(payload) {
    pendingReorder.value = payload;
    await sendReorder(payload, false);
  }

  async function sendReorder(payload, force) {
    const { level, parentId, ids } = payload;

    const endpoints = {
      module: `/admin/courses/${parentId}/modules/reorder`,
      topic: `/admin/modules/${parentId}/topics/reorder`,
      lesson: `/admin/topics/${parentId}/lessons/reorder`,
      'module-lesson': `/admin/modules/${parentId}/lessons/reorder`,
    };

    try {
      const { data } = await api.put(endpoints[level], { ids, force });
      const cleared = data?.data?.cleared ?? [];

      for (const lessonId of cleared) {
        const node = findNode('lesson', lessonId);

        if (node) {
          node.required_lesson_id = null;
        }
      }

      if (pendingReorder.value === payload) {
        reorderConflicts.value = [];
        pendingReorder.value = null;
      }
    } catch (e) {
      if (e?.status === 409 && e?.errors?.conflicts?.length) {
        reorderConflicts.value = e.errors.conflicts;
        pendingReorder.value = payload;

        return;
      }

      toast.error(e?.message || 'Não foi possível reordenar.');

      if (pendingReorder.value === payload) {
        reorderConflicts.value = [];
        pendingReorder.value = null;
      }

      await reload?.();
    }
  }

  async function confirmReorder() {
    if (pendingReorder.value) {
      await sendReorder(pendingReorder.value, true);
    }
  }

  function cancelReorder() {
    reorderConflicts.value = [];
    pendingReorder.value = null;
    reload?.();
  }

  return {
    findNode,
    createModule,
    createTopic,
    createLesson,
    createModuleLesson,
    rename,
    removeNode,
    confirmRemove,
    cancelRemove,
    removeConfirm,
    reorder,
    confirmReorder,
    cancelReorder,
    reorderConflicts,
  };
}
