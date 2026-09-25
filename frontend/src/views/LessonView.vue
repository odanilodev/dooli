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
          <div v-if="currentLesson.webinar_link && !liveSoon" class="video-stage has-player">
            <iframe
              :src="currentLesson.webinar_link"
              class="video-frame"
              title="Aula ao vivo"
              allow="autoplay; fullscreen; encrypted-media; picture-in-picture"
              allowfullscreen
            ></iframe>
            <span class="live-badge">AO VIVO</span>
          </div>
          <div v-else-if="currentLesson.webinar_link && liveSoon" class="video-stage live-soon">
            <span class="live-soon-badge">EM BREVE</span>
            <div class="live-soon-title">Aula ao vivo</div>
            <div class="live-soon-date">{{ formatDate(currentLesson.webinar_starts_at) }}</div>
          </div>
          <div v-else-if="videoEmbedUrl" class="video-stage has-player">
            <VideoPlayer
              :key="currentLesson.id"
              :platform="videoPlatform"
              :embed-url="videoEmbedUrl"
              :link="videoEmbedUrl"
              :duration="videoDuration"
              :resume-from="videoResumeFrom"
              @progress="onVideoProgress"
              @error="videoError = $event"
            />
          </div>
          <div v-else-if="videoLoading" class="video-stage video-stage-loading">
            <span class="video-spinner"></span>
            <span class="video-loading-text">Carregando vídeo...</span>
          </div>
          <div v-else class="video-stage" :style="videoBg">
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
          <div v-if="!currentLesson.webinar_link && !videoEmbedUrl && !videoLoading" class="video-controls">
            <button aria-label="Reproduzir"><svg width="15" height="15" viewBox="0 0 24 24" fill="#fff"><path d="M8 5l12 7-12 7V5z"/></svg></button>
            <span class="time">{{ currentLesson.video?.time || '00:00' }}</span>
            <span class="video-seek"><span></span></span>
            <button aria-label="Volume"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" stroke="#fff"><path d="M4 9v6h4l5 4V5L8 9H4z"/></svg></button>
            <button aria-label="Configurações"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" stroke="#fff"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></svg></button>
            <button aria-label="Tela cheia"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" stroke="#fff"><path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5"/></svg></button>
          </div>
          <p v-if="videoError" class="form-error show" style="padding:10px 14px 0;">{{ videoError }}</p>
        </div>

        <!-- TABS -->
        <div class="tabs">
          <button class="tab" :class="{ active: activeTab === 'descricao' }" @click="activeTab = 'descricao'">Descrição</button>
          <button class="tab" :class="{ active: activeTab === 'materiais' }" @click="activeTab = 'materiais'">Materiais da aula</button>
          <button class="tab" :class="{ active: activeTab === 'comentarios' }" @click="activeTab = 'comentarios'">Comentários</button>
          <button class="tab" :class="{ active: activeTab === 'avaliacoes' }" @click="activeTab = 'avaliacoes'">Avaliações</button>
        </div>

        <div v-if="activeTab === 'descricao'" class="tab-panel active">
          <div class="desc-row">
            <p v-if="currentLesson.content" class="desc-text" v-html="currentLesson.content"></p>
            <p v-else-if="currentLesson.description" class="desc-text" style="white-space:pre-line;">{{ currentLesson.description }}</p>
            <p v-else class="desc-text">Sem descrição para esta aula.</p>
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
              <a href="#" @click.prevent="downloadMaterial('document', currentLesson.document)" style="color:var(--accent);font-weight:700;">
                Documento da aula · {{ fileName(currentLesson.document.path) }}
              </a>
            </p>
            <ul v-if="currentLesson.attachments.length" style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:6px;">
              <li v-for="attachment in currentLesson.attachments" :key="attachment.id">
                <a href="#" @click.prevent="downloadMaterial('attachment', attachment)" style="color:var(--accent);font-weight:600;">{{ fileName(attachment.path) }}</a>
              </li>
            </ul>
          </template>
          <template v-else>Nenhum material adicional para esta aula.</template>
          <p v-if="materialError" class="form-error show" style="margin-top:12px;">{{ materialError }}</p>
        </div>

        <div v-else-if="activeTab === 'comentarios'" class="tab-panel active">
          <div class="comment-form">
            <textarea
              class="form-control"
              rows="3"
              v-model="commentText"
              placeholder="Escreva um comentário ou dúvida sobre esta aula..."
              style="resize:vertical;"
              @keydown.ctrl.enter="submitComment"
            ></textarea>
            <div class="comment-form-actions">
              <span v-if="commentError" class="form-error show" style="margin:0;">{{ commentError }}</span>
              <button
                class="btn btn-primary"
                :disabled="comments.posting || !commentText.trim()"
                @click="submitComment"
              >{{ comments.posting ? 'Enviando...' : 'Comentar' }}</button>
            </div>
          </div>

          <div v-if="comments.loading" class="comment-empty">Carregando comentários...</div>
          <div v-else-if="comments.error" class="comment-empty">{{ comments.error }}</div>
          <div v-else-if="comments.items.length === 0" class="comment-empty">Seja o primeiro a comentar esta aula.</div>
          <template v-else>
            <div v-for="comment in comments.items" :key="comment.id" class="comment-row">
              <span class="comment-avatar">{{ initialsOf(comment.user?.name) }}</span>
              <div class="comment-body">
                <div class="comment-head">
                  <span class="comment-author">{{ comment.user?.name || 'Aluno' }}</span>
                  <span class="comment-date">{{ formatDate(comment.created_at) }}</span>
                </div>
                <p class="comment-text">{{ comment.content }}</p>
              </div>
            </div>
          </template>
        </div>

        <div v-else class="tab-panel active">
          <div class="rating-summary">
            <div class="rating-score">
              <span class="rating-average">{{ formatRating(averageRating) }}</span>
              <span class="rating-average-max">/10</span>
            </div>
            <div class="rating-summary-body">
              <span class="rating-stars">
                <svg v-for="n in 5" :key="n" viewBox="0 0 24 24" :class="{ filled: n <= averageStars }"><path d="M12 2l2.9 6.3 6.6.6-5 4.4 1.5 6.5L12 16.9 5.9 19.8 7.4 13.3 2.4 8.9l6.6-.6z"/></svg>
              </span>
              <span class="rating-count">{{ ratings.items.length }} {{ ratings.items.length === 1 ? 'avaliação' : 'avaliações' }}</span>
            </div>
          </div>

          <div v-if="myRating" class="rating-mine">
            <span class="rating-mine-label">Sua avaliação</span>
            <span class="rating-stars">
              <svg v-for="n in 5" :key="n" viewBox="0 0 24 24" :class="{ filled: n <= myStars }"><path d="M12 2l2.9 6.3 6.6.6-5 4.4 1.5 6.5L12 16.9 5.9 19.8 7.4 13.3 2.4 8.9l6.6-.6z"/></svg>
            </span>
            <span class="rating-mine-value">{{ formatRating(myRating.rating) }}/10</span>
          </div>
          <div v-else class="rating-form">
            <span class="rating-form-label">Avalie esta aula</span>
            <span class="rating-stars rating-stars-input" @mouseleave="hoverStars = 0">
              <button
                v-for="n in 5"
                :key="n"
                type="button"
                class="rating-star-btn"
                :aria-label="`${n} ${n === 1 ? 'estrela' : 'estrelas'}`"
                @click="selectedStars = n"
                @mouseenter="hoverStars = n"
              >
                <svg viewBox="0 0 24 24" :class="{ filled: n <= (hoverStars || selectedStars) }"><path d="M12 2l2.9 6.3 6.6.6-5 4.4 1.5 6.5L12 16.9 5.9 19.8 7.4 13.3 2.4 8.9l6.6-.6z"/></svg>
              </button>
            </span>
            <button class="btn btn-primary" :disabled="ratings.posting || !selectedStars" @click="submitRating">
              {{ ratings.posting ? 'Enviando...' : 'Enviar avaliação' }}
            </button>
          </div>
          <p v-if="ratingError" class="form-error show" style="margin:0;">{{ ratingError }}</p>

          <div v-if="ratings.loading" class="comment-empty">Carregando avaliações...</div>
          <div v-else-if="ratings.error" class="comment-empty">{{ ratings.error }}</div>
          <div v-else-if="ratings.items.length === 0" class="comment-empty">Seja o primeiro a avaliar esta aula.</div>
          <template v-else>
            <div v-for="rating in ratings.items" :key="rating.id" class="rating-row">
              <span class="comment-avatar">{{ initialsOf(rating.user?.name) }}</span>
              <div class="rating-body">
                <div class="rating-head">
                  <span class="rating-author">{{ rating.user?.name || 'Aluno' }}</span>
                  <span class="rating-date">{{ formatDate(rating.created_at) }}</span>
                </div>
                <span class="rating-stars">
                  <svg v-for="n in 5" :key="n" viewBox="0 0 24 24" :class="{ filled: n <= starsOf(rating.rating) }"><path d="M12 2l2.9 6.3 6.6.6-5 4.4 1.5 6.5L12 16.9 5.9 19.8 7.4 13.3 2.4 8.9l6.6-.6z"/></svg>
                  <span class="rating-value">{{ formatRating(rating.rating) }}</span>
                </span>
              </div>
            </div>
          </template>
        </div>
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
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCoursePlayerStore } from '../stores/coursePlayer';
import { useCommentsStore } from '../stores/comments';
import { useRatingsStore } from '../stores/ratings';
import { useAuthStore } from '../stores/auth';
import { api, getToken } from '../api/http';
import VideoPlayer from '../components/VideoPlayer.vue';

const route = useRoute();
const router = useRouter();
const store = useCoursePlayerStore();
const comments = useCommentsStore();
const ratings = useRatingsStore();
const auth = useAuthStore();

const activeTab = ref('descricao');
const openModule = ref(null);
const completing = ref(false);
const completeError = ref('');
const commentText = ref('');
const commentError = ref('');
const ratingError = ref('');
const selectedStars = ref(0);
const hoverStars = ref(0);
const materialError = ref('');
const downloadingId = ref(null);
const videoEmbedUrl = ref(null);
const videoPlatform = ref(null);
const videoDuration = ref(0);
const videoResumeFrom = ref(0);
const videoError = ref('');
const videoLoading = ref(false);
const now = ref(Date.now());

let videoLessonId = null;
let videoRequestId = 0;
let nowTimer = null;

const courseId = computed(() => Number(route.params.courseId));
const lessonIdParam = computed(() => (route.params.lessonId ? Number(route.params.lessonId) : null));
const currentLesson = computed(() => store.currentLesson);
const modules = computed(() => store.course?.modules ?? []);
const isCurrentCompleted = computed(() => (currentLesson.value ? store.isCompleted(currentLesson.value.id) : false));

const myRating = computed(() => {
  const userId = auth.user?.id;

  if (!userId) {
    return null;
  }

  return ratings.items.find((rating) => rating.user?.id === userId) ?? null;
});

const myStars = computed(() => (myRating.value ? starsOf(myRating.value.rating) : 0));

const averageRating = computed(() => {
  if (!ratings.items.length) {
    return 0;
  }

  const sum = ratings.items.reduce((acc, rating) => acc + Number(rating.rating), 0);

  return sum / ratings.items.length;
});

const averageStars = computed(() => Math.round(averageRating.value / 2));

const videoBg = computed(() => {
  const image = currentLesson.value?.video?.image;
  return image ? { backgroundImage: `url(${image})`, backgroundSize: 'cover', backgroundPosition: 'center' } : {};
});

const liveSoon = computed(() => {
  const lesson = currentLesson.value;

  if (!lesson?.webinar_link || !lesson.webinar_starts_at) {
    return false;
  }

  return new Date(lesson.webinar_starts_at).getTime() > now.value;
});

function resetVideoState() {
  videoLoading.value = false;
  videoEmbedUrl.value = null;
  videoPlatform.value = null;
  videoDuration.value = 0;
  videoResumeFrom.value = 0;
  videoError.value = '';
  videoLessonId = null;
}

async function loadVideoPlayback(lessonId) {
  const cid = courseId.value;

  if (!lessonId || !cid) {
    resetVideoState();

    return;
  }

  // Live lessons stream via the webinar embed, no on-demand playback to load.
  if (store.lessons.find((item) => item.id === Number(lessonId))?.webinar_link) {
    resetVideoState();

    return;
  }

  if (videoLessonId === lessonId && (videoLoading.value || videoEmbedUrl.value)) {
    return;
  }

  videoLessonId = lessonId;
  videoLoading.value = true;
  videoEmbedUrl.value = null;
  videoPlatform.value = null;
  videoDuration.value = 0;
  videoResumeFrom.value = 0;
  videoError.value = '';

  const requestId = ++videoRequestId;

  try {
    const { data } = await api.get(`/me/courses/${cid}/lessons/${lessonId}/video`);

    if (requestId !== videoRequestId) {
      return;
    }

    const playback = data.data || {};

    videoEmbedUrl.value = playback.embed_url || null;
    videoPlatform.value = playback.platform || null;
    videoDuration.value = playback.duration || 0;
    videoResumeFrom.value = playback.resume_from || 0;
  } catch (e) {
    if (requestId !== videoRequestId) {
      return;
    }

    // A lesson without a video is a valid state, not an error.
    if (e?.status !== 404) {
      videoError.value = e?.message || 'Não foi possível carregar o vídeo.';
    }
  } finally {
    if (requestId === videoRequestId) {
      videoLoading.value = false;
    }
  }
}

let lastVideoSentAt = 0;
const videoTracking = { lessonId: null, courseId: null, teamId: null, position: 0, duration: null };

function videoPositionPayload() {
  return {
    lesson_id: videoTracking.lessonId,
    course_id: videoTracking.courseId,
    team_id: videoTracking.teamId,
    position: Math.max(0, Math.floor(videoTracking.position)),
    duration: videoTracking.duration ? Math.floor(videoTracking.duration) : null,
  };
}

function sendVideoPosition(keepalive = false) {
  if (!videoTracking.lessonId || !videoTracking.teamId) {
    return;
  }

  const payload = videoPositionPayload();

  if (keepalive) {
    try {
      const token = getToken();
      fetch(`${api.defaults.baseURL}/me/progress/video-position`, {
        method: 'POST',
        keepalive: true,
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: JSON.stringify(payload),
      }).catch(() => {});
    } catch (e) {
      /* ignore */
    }

    return;
  }

  api.post('/me/progress/video-position', payload).catch(() => {});
}

function onVideoProgress({ position, duration }) {
  videoTracking.lessonId = currentLesson.value?.id ?? null;
  videoTracking.courseId = courseId.value;
  videoTracking.teamId = store.progress?.team_id ?? null;
  videoTracking.position = position;
  videoTracking.duration = duration || videoTracking.duration;

  const now = Date.now();

  if (now - lastVideoSentAt >= 5000) {
    lastVideoSentAt = now;
    sendVideoPosition();
  }
}

function flushVideoPosition() {
  if (videoTracking.lessonId && videoTracking.position > 0) {
    sendVideoPosition();
  }
}

function handleBeforeUnload() {
  sendVideoPosition(true);
}

onMounted(() => {
  window.addEventListener('beforeunload', handleBeforeUnload);
  nowTimer = setInterval(() => {
    now.value = Date.now();
  }, 30000);
});

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', handleBeforeUnload);
  clearInterval(nowTimer);
  flushVideoPosition();
});

watch(
  courseId,
  async (id) => {
    if (!id) {
      return;
    }

    videoLessonId = null;

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
    loadVideoPlayback(id);
  }
}, { immediate: true });

watch(currentLesson, (lesson) => {
  flushVideoPosition();

  comments.reset();
  ratings.reset();
  commentText.value = '';
  commentError.value = '';
  selectedStars.value = 0;
  hoverStars.value = 0;
  ratingError.value = '';

  if (lesson) {
    openModule.value = lesson.moduleId;

    if (activeTab.value === 'comentarios') {
      comments.fetch(lesson.id);
    }

    if (activeTab.value === 'avaliacoes') {
      ratings.fetch(lesson.id);
    }
  }
});

watch(activeTab, (tab) => {
  if (!currentLesson.value) {
    return;
  }

  if (tab === 'comentarios') {
    comments.fetch(currentLesson.value.id);
  }

  if (tab === 'avaliacoes') {
    ratings.fetch(currentLesson.value.id);
  }
});

function formatDate(value) {
  if (!value) {
    return '';
  }

  try {
    return new Date(value).toLocaleString('pt-BR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch (e) {
    return '';
  }
}

function initialsOf(name) {
  const trimmed = (name || '').trim();

  if (!trimmed) {
    return '?';
  }

  const parts = trimmed.split(/\s+/);

  return ((parts[0]?.[0] || '') + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
}

function starsOf(value) {
  const numeric = Number(value) || 0;

  return Math.max(0, Math.min(5, Math.round(numeric / 2)));
}

function formatRating(value) {
  return (Number(value) || 0).toLocaleString('pt-BR', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  });
}

async function submitRating() {
  ratingError.value = '';

  if (!selectedStars.value || !currentLesson.value || !store.course || !store.progress) {
    return;
  }

  try {
    await ratings.store({
      rating: selectedStars.value * 2,
      lessonId: currentLesson.value.id,
      moduleId: currentLesson.value.moduleId,
      courseId: store.course.id,
      teamId: store.progress.team_id,
    });

    selectedStars.value = 0;
    hoverStars.value = 0;
  } catch (e) {
    ratingError.value = e?.message || 'Não foi possível enviar sua avaliação.';
  }
}

function fileName(path) {
  return (path || '').split('/').pop() || 'arquivo';
}

async function downloadMaterial(type, material) {
  materialError.value = '';
  downloadingId.value = material.id;

  const segment = type === 'document' ? 'documents' : 'attachments';

  try {
    const { data } = await api.get(`/me/courses/${courseId.value}/${segment}/${material.id}`, {
      responseType: 'blob',
    });

    const url = URL.createObjectURL(data);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName(material.path);
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  } catch (e) {
    materialError.value = e?.message || 'Não foi possível baixar o arquivo.';
  } finally {
    downloadingId.value = null;
  }
}

async function submitComment() {
  commentError.value = '';

  const content = commentText.value.trim();

  if (!content || !currentLesson.value || !store.course || !store.progress) {
    return;
  }

  try {
    await comments.store({
      content,
      lessonId: currentLesson.value.id,
      moduleId: currentLesson.value.moduleId,
      courseId: store.course.id,
      teamId: store.progress.team_id,
    });

    commentText.value = '';
  } catch (e) {
    commentError.value = e?.message || 'Não foi possível enviar seu comentário.';
  }
}

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
