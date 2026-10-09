import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api } from '../api/http';
import { useStudentTeamsStore } from './studentTeams';

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

    const contentTypeOf = (lesson) => lesson.content_type
      ?? (lesson.video ? 'video' : (lesson.document ? 'document' : (lesson.webinar_link ? 'live' : null)));

    for (const module of modules) {
      for (const topic of module.topics ?? []) {
        for (const lesson of topic.lessons ?? []) {
          flat.push({
            id: lesson.id,
            name: lesson.name,
            description: lesson.description,
            content: lesson.content,
            contentType: contentTypeOf(lesson),
            video: lesson.video ?? null,
            document: lesson.document ?? null,
            attachments: lesson.attachments ?? [],
            webinar_link: lesson.webinar_link ?? null,
            webinar_starts_at: lesson.webinar_starts_at ?? null,
            requiredLessonId: lesson.required_lesson_id ?? null,
            requireFullWatch: lesson.require_full_watch ?? false,
            availableAt: lesson.available_at ?? null,
            scheduleLocked: lesson.is_locked ?? false,
            moduleId: module.id,
            moduleName: module.name,
            topicId: topic.id,
            topicName: topic.name,
          });
        }
      }

      for (const lesson of module.lessons ?? []) {
        flat.push({
          id: lesson.id,
          name: lesson.name,
          description: lesson.description,
          content: lesson.content,
          contentType: contentTypeOf(lesson),
          video: lesson.video ?? null,
          document: lesson.document ?? null,
          attachments: lesson.attachments ?? [],
            webinar_link: lesson.webinar_link ?? null,
            webinar_starts_at: lesson.webinar_starts_at ?? null,
            requiredLessonId: lesson.required_lesson_id ?? null,
            requireFullWatch: lesson.require_full_watch ?? false,
            availableAt: lesson.available_at ?? null,
            scheduleLocked: lesson.is_locked ?? false,
            moduleId: module.id,
            moduleName: module.name,
            topicId: null,
            topicName: null,
          });
        }
      }

    return flat;
  });

  const completedIds = computed(() => new Set(progress.value?.completed_lesson_ids ?? []));
  const startedIds = computed(() => new Set(progress.value?.started_lesson_ids ?? []));
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

  function isStarted(lessonId) {
    const id = Number(lessonId);

    return startedIds.value.has(id) && !completedIds.value.has(id);
  }

  function lessonById(lessonId) {
    return lessons.value.find((lesson) => lesson.id === Number(lessonId)) ?? null;
  }

  function isLocked(lessonId) {
    const lesson = lessonById(lessonId);

    if (!lesson) {
      return false;
    }

    if (lesson.scheduleLocked) {
      return true;
    }

    if (!lesson.requiredLessonId) {
      return false;
    }

    return !completedIds.value.has(Number(lesson.requiredLessonId));
  }

  function lockReason(lessonId) {
    const lesson = lessonById(lessonId);

    if (!lesson) {
      return null;
    }

    if (lesson.scheduleLocked) {
      return 'schedule';
    }

    if (lesson.requiredLessonId && !completedIds.value.has(Number(lesson.requiredLessonId))) {
      return 'prerequisite';
    }

    return null;
  }

  function scheduleAvailableAt(lessonId) {
    return lessonById(lessonId)?.availableAt ?? null;
  }

  function requiredLessonName(lessonId) {
    const lesson = lessonById(lessonId);

    if (!lesson?.requiredLessonId) {
      return null;
    }

    return lessonById(lesson.requiredLessonId)?.name ?? null;
  }

  function selectLesson(lessonId) {
    activeLessonId.value = Number(lessonId);
  }

  async function load(courseId, force = false) {
    const id = Number(courseId);

    if (!force && loadedCourseId.value === id && course.value) {
      return;
    }

    const teamId = useStudentTeamsStore().selectedTeamId || undefined;

    loading.value = true;
    error.value = null;

    try {
      const [courseResponse, progressResponse] = await Promise.all([
        api.get(`/me/courses/${id}`, { params: { team_id: teamId } }),
        api.get(`/me/courses/${id}/progress`, { params: { team_id: teamId } }),
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

  async function startLesson(lessonId) {
    const lesson = lessons.value.find((item) => item.id === Number(lessonId));

    if (!lesson || !course.value || !progress.value) {
      return null;
    }

    try {
      const { data } = await api.post('/me/progress/start', {
        lesson_id: lesson.id,
        course_id: course.value.id,
        team_id: progress.value.team_id,
      });

      return data?.data ?? null;
    } catch (e) {
      return null;
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
    isStarted,
    isLocked,
    lockReason,
    scheduleAvailableAt,
    requiredLessonName,
    load,
    selectLesson,
    startLesson,
    completeLesson,
    reset,
  };
});
