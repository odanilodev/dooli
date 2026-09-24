<template>
  <div class="lesson-content">

    <div v-if="!store.course && store.loading" class="tab-panel simple active" style="flex:1;">Carregando curso...</div>
    <div v-else-if="store.error" class="tab-panel simple active" style="flex:1;">{{ store.error }}</div>
    <div v-else-if="!currentLesson" class="tab-panel simple active" style="flex:1;">Este curso ainda não possui aulas publicadas.</div>

    <template v-else>
      <!-- LEFT COLUMN -->
      <div class="lesson-main">

        <nav class="breadcrumb">
          <svg class="icon" width="13" height="13" viewBox="0 0 24 24" stroke-width="2"><path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v9a1 1 0 0 0 1 1h3v-6h4v6h3a1 1 0 0 0 1-1v-9"/></svg>
          <router-link to="/cursos">Meus cursos</router-link>
          <span class="sep">›</span>
          <router-link :to="{ name: 'curso', params: { courseId } }">{{ store.course.name }}</router-link>
          <span class="sep">›</span>
          <span>{{ currentLesson.moduleName }}</span>
          <span class="sep">›</span>
          <span class="current">{{ currentLesson.name }}</span>
        </nav>

        <div class="lesson-head">
          <span class="lesson-head-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5l12 7-12 7V5z"/></svg>
          </span>
          <div>
            <h1 class="lesson-title">{{ currentLesson.name }}</h1>
            <p class="lesson-desc">{{ currentLesson.description }}</p>
          </div>
        </div>

        <!-- VIDEO -->
        <div class="video-block">
          <div class="video-stage" :style="videoBg">
            <svg v-if="!currentLesson.video" class="video-illustration" width="100%" height="100%" viewBox="0 0 900 420" preserveAspectRatio="xMidYMid slice" fill="none">
              <defs>
                <radialGradient id="vidGlow" cx="35%" cy="55%" r="55%">
                  <stop offset="0%" stop-color="#17c993" stop-opacity="0.25"/>
                  <stop offset="100%" stop-color="#17c993" stop-opacity="0"/>
                </radialGradient>
              </defs>
              <rect width="900" height="420" fill="#0c141e"/>
              <circle cx="330" cy="240" r="260" fill="url(#vidGlow)"/>
              <g fill="#141f2c">
                <circle cx="330" cy="200" r="70"/>
                <path d="M180 420c0-95 65-150 150-150s150 55 150 150z"/>
              </g>
              <g stroke="#1c2a38" stroke-width="1">
                <line x1="0" y1="140" x2="900" y2="140"/>
                <line x1="0" y1="280" x2="900" y2="280"/>
              </g>
            </svg>
            <div class="video-overlay-text">
              <div class="l1">{{ currentLesson.moduleName }}</div>
              <div class="l2">{{ currentLesson.name }}</div>
            </div>
            <button class="play-btn" aria-label="Reproduzir" @click="pulsePlay">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="#ffffff"><path d="M8 5l12 7-12 7V5z"/></svg>
            </button>
          </div>
          <div class="video-controls">
            <button aria-label="Reproduzir"><svg width="15" height="15" viewBox="0 0 24 24" fill="#fff"><path d="M8 5l12 7-12 7V5z"/></svg></button>
            <span class="time">{{ currentLesson.video?.time || '00:00' }}</span>
            <span class="video-seek"><span></span></span>
            <button aria-label="Volume"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" stroke="#fff"><path d="M4 9v6h4l5 4V5L8 9H4z"/></svg></button>
            <button aria-label="Configurações"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" stroke="#fff"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></svg></button>
            <button aria-label="Tela cheia"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" stroke="#fff"><path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5"/></svg></button>
          </div>
        </div>

        <!-- TABS -->
        <div class="tabs">
          <button class="tab" :class="{ active: activeTab === 'descricao' }" @click="activeTab = 'descricao'">Descrição</button>
          <button class="tab" :class="{ active: activeTab === 'materiais' }" @click="activeTab = 'materiais'">Materiais da aula</button>
          <button class="tab" :class="{ active: activeTab === 'comentarios' }" @click="activeTab = 'comentarios'">Comentários</button>
        </div>

        <div v-if="activeTab === 'descricao'" class="tab-panel active">
          <div class="desc-row">
            <p class="desc-text" style="white-space:pre-line;">{{ currentLesson.content || currentLesson.description || 'Sem descrição para esta aula.' }}</p>
            <button class="btn btn-primary" :disabled="isCurrentCompleted || completing" @click="markCompleted">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
              <span>{{ isCurrentCompleted ? 'Aula concluída' : (completing ? 'Salvando...' : 'Marcar como concluída') }}</span>
            </button>
          </div>
          <p v-if="completeError" class="form-error show">{{ completeError }}</p>
        </div>

        <div v-else-if="activeTab === 'materiais'" class="tab-panel simple active">
          <template v-if="currentLesson.document || currentLesson.attachments.length">
            <p v-if="currentLesson.document" style="margin:0 0 12px;">
              <a :href="currentLesson.document.path" target="_blank" rel="noopener" style="color:var(--accent);font-weight:700;">Documento da aula</a>
            </p>
            <ul v-if="currentLesson.attachments.length" style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:6px;">
              <li v-for="attachment in currentLesson.attachments" :key="attachment.id">
                <a :href="attachment.path" target="_blank" rel="noopener" style="color:var(--accent);font-weight:600;">{{ attachment.path }}</a>
              </li>
            </ul>
          </template>
          <template v-else>Nenhum material adicional para esta aula.</template>
        </div>

        <div v-else class="tab-panel simple active">Os comentários dos alunos aparecerão aqui em breve.</div>
      </div>

      <!-- RIGHT COLUMN -->
      <aside class="lesson-side">
        <div class="progress-card">

          <div>
            <div class="progress-head">
              <span class="label">Seu progresso no curso</span>
              <span class="pct">{{ store.percentage }}%</span>
            </div>
            <div class="progress-bar" style="margin-top:8px;"><span :style="{ width: store.percentage + '%' }"></span></div>
            <div class="progress-sub" style="margin-top:6px;">{{ store.completedCount }} de {{ store.totalLessons }} aulas concluídas</div>
          </div>

          <div class="divider"></div>

          <div class="module-list">
            <template v-for="module in modules" :key="module.id">
              <button
                class="module-row"
                :class="moduleClasses(module)"
                :aria-expanded="openModule === module.id"
                @click="toggleModule(module.id)"
              >
                <svg v-if="moduleTotal(module) > 0 && moduleCompleted(module) === moduleTotal(module)" width="18" height="18" viewBox="0 0 24 24" fill="var(--accent)"><circle cx="12" cy="12" r="10"/></svg>
                <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
                <span class="module-title">{{ module.name }}</span>
                <span class="module-count">{{ moduleCompleted(module) }}/{{ moduleTotal(module) }}</span>
                <svg class="icon module-chevron" width="14" height="14" viewBox="0 0 24 24" stroke-width="2.2"><path d="M6 9l6 6 6-6"/></svg>
              </button>

              <div
                class="lesson-sub-list"
                :class="{ open: openModule === module.id }"
                :style="openModule === module.id ? { maxHeight: '1400px' } : null"
              >
                <template v-for="topic in module.topics" :key="topic.id">
                  <div v-if="topic.lessons.length" style="padding:8px 10px 2px;font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);font-weight:700;">{{ topic.name }}</div>
                  <div
                    v-for="lesson in topic.lessons"
                    :key="lesson.id"
                    class="lesson-row"
                    :class="{ current: currentLesson.id === lesson.id }"
                    role="button"
                    tabindex="0"
                    style="cursor:pointer;"
                    @click="goLesson(lesson.id)"
                    @keydown.enter="goLesson(lesson.id)"
                  >
                    <span class="lesson-icon">
                      <svg v-if="store.isCompleted(lesson.id)" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                      <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
                      <svg v-if="currentLesson.id === lesson.id" class="play-mark" width="8" height="8" viewBox="0 0 24 24" fill="currentColor"><path d="M9 6l7 6-7 6V6z"/></svg>
                    </span>
                    <span class="name">{{ lesson.name }}</span>
                    <span class="mins" v-if="store.isCompleted(lesson.id)">✓</span>
                  </div>
                </template>
              </div>
            </template>
          </div>
        </div>

        <div class="lesson-nav">
          <button class="btn btn-outline" :disabled="!store.prevLesson" @click="goLesson(store.prevLesson?.id)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Aula anterior
          </button>
          <button class="btn btn-primary" :disabled="!store.nextLesson" @click="goLesson(store.nextLesson?.id)">
            Próxima aula
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </button>
        </div>
      </aside>
    </template>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCoursePlayerStore } from '../stores/coursePlayer';

const route = useRoute();
const router = useRouter();
const store = useCoursePlayerStore();

const activeTab = ref('descricao');
const openModule = ref(null);
const completing = ref(false);
const completeError = ref('');

const courseId = computed(() => Number(route.params.courseId));
const lessonIdParam = computed(() => (route.params.lessonId ? Number(route.params.lessonId) : null));
const currentLesson = computed(() => store.currentLesson);
const modules = computed(() => store.course?.modules ?? []);
const isCurrentCompleted = computed(() => (currentLesson.value ? store.isCompleted(currentLesson.value.id) : false));

const videoBg = computed(() => {
  const image = currentLesson.value?.video?.image;
  return image ? { backgroundImage: `url(${image})`, backgroundSize: 'cover', backgroundPosition: 'center' } : {};
});

watch(
  courseId,
  async (id) => {
    if (!id) {
      return;
    }

    await store.load(id);

    if (store.error) {
      return;
    }

    const target = lessonIdParam.value ?? store.lessons[0]?.id ?? null;

    if (target && route.name !== 'aula') {
      router.replace({ name: 'aula', params: { courseId: id, lessonId: target } });
    }
  },
  { immediate: true },
);

watch(lessonIdParam, (id) => {
  if (id) {
    store.selectLesson(id);
  }
});

watch(currentLesson, (lesson) => {
  if (lesson) {
    openModule.value = lesson.moduleId;
  }
});

function goLesson(lessonId) {
  if (!lessonId) {
    return;
  }

  router.push({ name: 'aula', params: { courseId: courseId.value, lessonId } });
}

function toggleModule(moduleId) {
  openModule.value = openModule.value === moduleId ? null : moduleId;
}

function pulsePlay(event) {
  const button = event.currentTarget;
  button.style.transform = 'scale(0.9)';
  setTimeout(() => {
    button.style.transform = '';
  }, 120);
}

function moduleLessons(module) {
  return (module.topics ?? []).flatMap((topic) => topic.lessons ?? []);
}

function moduleTotal(module) {
  return moduleLessons(module).length;
}

function moduleCompleted(module) {
  return moduleLessons(module).filter((lesson) => store.isCompleted(lesson.id)).length;
}

function moduleClasses(module) {
  const total = moduleTotal(module);
  const done = moduleCompleted(module);
  return { done: total > 0 && done === total, todo: done === 0 };
}

async function markCompleted() {
  if (!currentLesson.value || isCurrentCompleted.value || completing.value) {
    return;
  }

  completing.value = true;
  completeError.value = '';

  try {
    await store.completeLesson(currentLesson.value.id);
  } catch (e) {
    completeError.value = e?.message || 'Não foi possível concluir a aula.';
  } finally {
    completing.value = false;
  }
}
</script>
