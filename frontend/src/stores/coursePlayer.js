import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api } from '../api/http';

export const useCoursePlayerStore = defineStore('coursePlayer', () => {
  const course = ref(null);
  const progress = ref(null);
  const activeLessonId = ref(null);
  const loading = ref(false);
  const loadedCourseId = ref(null);
  const error = ref(null);

  const lessons = computed(() => {
    const modules = course.value?.modules ?? [];
    const flat = [];

    for (const module of modules) {
      for (const topic of module.topics ?? []) {
        for (const lesson of topic.lessons ?? []) {
          flat.push({
            id: lesson.id,
            name: lesson.name,
            description: lesson.description,
            content: lesson.content,
            video: lesson.video ?? null,
            document: lesson.document ?? null,
            attachments: lesson.attachments ?? [],
            webinar_link: lesson.webinar_link ?? null,
            webinar_starts_at: lesson.webinar_starts_at ?? null,
            moduleId: module.id,
            moduleName: module.name,
            topicId: topic.id,
            topicName: topic.name,
          });
        }
      }
    }

    return flat;
  });

  const completedIds = computed(() => new Set(progress.value?.completed_lesson_ids ?? []));
  const currentLesson = computed(
    () => lessons.value.find((lesson) => lesson.id === Number(activeLessonId.value)) ?? lessons.value[0] ?? null,
  );
  const currentIndex = computed(() => lessons.value.findIndex((lesson) => lesson.id === currentLesson.value?.id));
  const percentage = computed(() => Math.round(Number(progress.value?.percentage) || 0));
  const totalLessons = computed(() => progress.value?.total_lessons ?? lessons.value.length);
  const completedCount = computed(() => progress.value?.completed_lessons ?? completedIds.value.size);
  const prevLesson = computed(() => (currentIndex.value > 0 ? lessons.value[currentIndex.value - 1] : null));
  const nextLesson = computed(() =>
    currentIndex.value >= 0 && currentIndex.value < lessons.value.length - 1
      ? lessons.value[currentIndex.value + 1]
      : null,
  );

  function isCompleted(lessonId) {
    return completedIds.value.has(Number(lessonId));
  }

  function selectLesson(lessonId) {
    activeLessonId.value = Number(lessonId);
  }

  async function load(courseId) {
    const id = Number(courseId);

    if (loadedCourseId.value === id && course.value) {
      return;
    }

    loading.value = true;
    error.value = null;

    try {
      const [courseResponse, progressResponse] = await Promise.all([
        api.get(`/me/courses/${id}`),
        api.get(`/me/courses/${id}/progress`),
      ]);

      course.value = courseResponse.data.data;
      progress.value = progressResponse.data.data;
      loadedCourseId.value = id;
      error.value = null;
    } catch (e) {
      course.value = null;
      progress.value = null;
      loadedCourseId.value = null;
      error.value = e?.message || 'Não foi possível carregar o curso.';
    } finally {
      loading.value = false;
    }
  }

  async function completeLesson(lessonId) {
    const lesson = lessons.value.find((item) => item.id === Number(lessonId));

    if (!lesson || !course.value || !progress.value) {
      return null;
    }

    const { data } = await api.post('/me/progress/complete', {
      lesson_id: lesson.id,
      course_id: course.value.id,
      team_id: progress.value.team_id,
      module_id: lesson.moduleId,
    });

    if (data?.data?.progress) {
      progress.value = data.data.progress;
    }

    return data?.data ?? null;
  }

  function reset() {
    course.value = null;
    progress.value = null;
    activeLessonId.value = null;
    loadedCourseId.value = null;
    error.value = null;
  }

  return {
    course,
    progress,
    activeLessonId,
    loading,
    error,
    lessons,
    currentLesson,
    currentIndex,
    percentage,
    totalLessons,
    completedCount,
    prevLesson,
    nextLesson,
    isCompleted,
    load,
    selectLesson,
    completeLesson,
    reset,
  };
});
