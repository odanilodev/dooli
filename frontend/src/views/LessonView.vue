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
          </div>
        </div>

        <!-- VIDEO -->
        <div v-if="showMediaBlock" class="video-block">
          <div v-if="isCurrentLocked" class="video-stage video-stage-locked">
            <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
            <div class="video-overlay-text">
              <div class="l1">Aula bloqueada</div>
              <div v-if="currentLockReason === 'schedule'" class="l2">Disponível em {{ formatDate(currentScheduleAt) }}</div>
              <div v-else class="l2">Conclua "{{ lockedPrerequisiteName }}" para liberar</div>
            </div>
            <button v-if="currentLockReason !== 'schedule' && prerequisiteLesson" class="btn btn-primary" @click="goLesson(prerequisiteLesson.id, true)">
              Ir para "{{ prerequisiteLesson.name }}"
            </button>
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
              @error="onVideoError"
            />
          </div>
          <div v-else-if="showDocumentStage" class="video-stage has-player document-stage">
            <div v-if="documentLoading" class="document-loading">
              <span class="video-spinner"></span>
              <span class="video-loading-text">Carregando documento...</span>
            </div>
            <iframe v-else-if="documentPreviewUrl" :src="documentPreviewUrl" class="document-frame" title="Documento da aula"></iframe>
            <div v-else class="document-fallback">
              <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h6"/></svg>
              <div class="document-fallback-name">{{ fileName(currentLesson.document?.path) }}</div>
              <p class="document-fallback-text">{{ documentError || 'Pré-visualização indisponível para este formato. Baixe o arquivo para abrir.' }}</p>
            </div>
            <button
              type="button"
              class="document-download"
              :disabled="downloadingId === currentLesson.document?.id"
              @click="downloadMaterial('document', currentLesson.document)"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 11l5 5 5-5M5 21h14"/></svg>
              {{ currentLesson.document?.url ? 'Abrir documento' : 'Baixar documento' }}
            </button>
          </div>
          <div v-else class="video-stage video-stage-loading">
            <span class="video-spinner"></span>
            <span class="video-loading-text">Carregando vídeo...</span>
          </div>
        </div>
        <!-- TABS -->
        <div class="tabs">
          <button class="tab" :class="{ active: activeTab === 'descricao' }" @click="activeTab = 'descricao'">Descrição</button>
          <button class="tab" :class="{ active: activeTab === 'materiais' }" @click="activeTab = 'materiais'">Materiais da aula</button>
          <button v-if="commentsEnabled" class="tab" :class="{ active: activeTab === 'comentarios' }" @click="activeTab = 'comentarios'">Comentários</button>
          <button class="tab" :class="{ active: activeTab === 'avaliacoes' }" @click="activeTab = 'avaliacoes'">Avaliações</button>
        </div>

        <div v-if="activeTab === 'descricao'" class="tab-panel active">
          <div class="desc-row">
            <p v-if="currentLesson.content" class="desc-text" v-html="currentLesson.content"></p>
            <p v-else-if="currentLesson.description" class="desc-text" style="white-space:pre-line;">{{ currentLesson.description }}</p>
            <p v-else class="desc-text">Sem descrição para esta aula.</p>
            <button class="btn btn-primary" :disabled="isCurrentCompleted || completing || isCurrentLocked || isCurrentAwaitingWatch" @click="markCompleted">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
              <span>{{ isCurrentCompleted ? 'Aula concluída' : (isCurrentLocked ? 'Aula bloqueada' : (isCurrentAwaitingWatch ? 'Assista até o final' : (completing ? 'Salvando...' : 'Marcar como concluída'))) }}</span>
            </button>
          </div>
        </div>

        <div v-else-if="activeTab === 'materiais'" class="tab-panel simple active">
          <template v-if="supplementalDocument || currentLesson.attachments.length">
            <p v-if="supplementalDocument" style="margin:0 0 12px;">
              <a href="#" @click.prevent="downloadMaterial('document', supplementalDocument)" style="color:var(--accent);font-weight:700;">
                Documento da aula · {{ supplementalDocument.path ? fileName(supplementalDocument.path) : supplementalDocument.url }}
              </a>
            </p>
            <ul v-if="currentLesson.attachments.length" style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:6px;">
              <li v-for="attachment in currentLesson.attachments" :key="attachment.id">
                <a href="#" @click.prevent="downloadMaterial('attachment', attachment)" style="color:var(--accent);font-weight:600;">{{ fileName(attachment.path) }}</a>
              </li>
            </ul>
          </template>
          <template v-else>Nenhum material adicional para esta aula.</template>
        </div>

        <div v-else-if="activeTab === 'comentarios' && commentsEnabled" class="tab-panel active">
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
                  <span v-if="comment.is_admin" class="comment-badge">Equipe</span>
                  <span class="comment-date">{{ formatDate(comment.created_at) }}</span>
                  <button
                    v-if="isOwnComment(comment)"
                    type="button"
                    class="comment-delete-btn"
                    :disabled="deletingId === comment.id"
                    @click="removeComment(comment)"
                  >{{ deletingId === comment.id ? 'Excluindo...' : 'Excluir' }}</button>
                </div>
                <p class="comment-text">{{ comment.content }}</p>

                <div v-if="comment.replies?.length" class="comment-replies">
                  <div v-for="reply in comment.replies" :key="reply.id" class="comment-reply">
                    <span class="comment-avatar comment-avatar-sm">{{ initialsOf(reply.user?.name) }}</span>
                    <div class="comment-body">
                      <div class="comment-head">
                        <span class="comment-author">{{ reply.user?.name || 'Aluno' }}</span>
                        <span v-if="reply.is_admin" class="comment-badge">Equipe</span>
                        <span class="comment-date">{{ formatDate(reply.created_at) }}</span>
                        <button
                          v-if="isOwnComment(reply)"
                          type="button"
                          class="comment-delete-btn"
                          :disabled="deletingId === reply.id"
                          @click="removeComment(reply)"
                        >{{ deletingId === reply.id ? 'Excluindo...' : 'Excluir' }}</button>
                      </div>
                      <p class="comment-text">{{ reply.content }}</p>
                    </div>
                  </div>
                </div>

                <div v-if="studentRepliesEnabled" class="comment-reply-area">
                  <button
                    v-if="replyingTo !== comment.id"
                    type="button"
                    class="comment-reply-btn"
                    @click="startReply(comment.id)"
                  >Responder</button>

                  <div v-else class="comment-reply-form">
                    <textarea
                      class="form-control"
                      rows="2"
                      v-model="replyText"
                      :placeholder="`Responder ${comment.user?.name || 'comentário'}...`"
                      style="resize:vertical;"
                      @keydown.ctrl.enter="submitReply(comment.id)"
                    ></textarea>
                    <div class="comment-form-actions">
                      <button type="button" class="comment-reply-cancel" @click="cancelReply">Cancelar</button>
                      <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        :disabled="comments.replying || !replyText.trim()"
                        @click="submitReply(comment.id)"
                      >{{ comments.replying ? 'Enviando...' : 'Responder' }}</button>
                    </div>
                  </div>
                </div>
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
                    :class="{ current: currentLesson.id === lesson.id, locked: store.isLocked(lesson.id), started: store.isStarted(lesson.id), completed: store.isCompleted(lesson.id) }"
                    role="button"
                    tabindex="0"
                    style="cursor:pointer;"
                    @click="goLesson(lesson.id)"
                    @keydown.enter="goLesson(lesson.id)"
                  >
                    <span class="lesson-icon">
                      <svg v-if="store.isLocked(lesson.id)" class="lock-mark" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                      <svg v-else-if="store.isCompleted(lesson.id)" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                      <svg v-else-if="store.isStarted(lesson.id)" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
                      <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
                      <svg v-if="currentLesson.id === lesson.id && !store.isLocked(lesson.id)" class="play-mark" width="8" height="8" viewBox="0 0 24 24" fill="currentColor"><path d="M9 6l7 6-7 6V6z"/></svg>
                    </span>
                    <span class="name">{{ lesson.name }}</span>
                    <span class="mins" v-if="store.isCompleted(lesson.id)">✓</span>
                  </div>
                </template>

                <template v-if="module.lessons?.length">
                  <div
                    v-for="lesson in module.lessons"
                    :key="lesson.id"
                    class="lesson-row"
                    :class="{ current: currentLesson.id === lesson.id, locked: store.isLocked(lesson.id), started: store.isStarted(lesson.id), completed: store.isCompleted(lesson.id) }"
                    role="button"
                    tabindex="0"
                    style="cursor:pointer;"
                    @click="goLesson(lesson.id)"
                    @keydown.enter="goLesson(lesson.id)"
                  >
                    <span class="lesson-icon">
                      <svg v-if="store.isLocked(lesson.id)" class="lock-mark" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                      <svg v-else-if="store.isCompleted(lesson.id)" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                      <svg v-else-if="store.isStarted(lesson.id)" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
                      <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
                      <svg v-if="currentLesson.id === lesson.id && !store.isLocked(lesson.id)" class="play-mark" width="8" height="8" viewBox="0 0 24 24" fill="currentColor"><path d="M9 6l7 6-7 6V6z"/></svg>
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
          <button class="btn btn-primary" :disabled="!store.nextLesson || isNextLocked" @click="goLesson(store.nextLesson?.id)">
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
import { useStudentTeamsStore } from '../stores/studentTeams';
import { api, getToken } from '../api/http';
import { useToast } from '../composables/useToast';
import VideoPlayer from '../components/VideoPlayer.vue';

const route = useRoute();
const router = useRouter();
const store = useCoursePlayerStore();
const comments = useCommentsStore();
const ratings = useRatingsStore();
const auth = useAuthStore();
const studentTeams = useStudentTeamsStore();
const toast = useToast();

const activeTab = ref('descricao');
const openModule = ref(null);
const completing = ref(false);
const commentText = ref('');
const replyingTo = ref(null);
const replyText = ref('');
const deletingId = ref(null);
const selectedStars = ref(0);
const hoverStars = ref(0);
const downloadingId = ref(null);
const videoEmbedUrl = ref(null);
const videoPlatform = ref(null);
const videoDuration = ref(0);
const videoResumeFrom = ref(0);
const videoLoading = ref(false);
const watchedToEnd = ref(false);
const documentPreviewUrl = ref(null);
const documentLoading = ref(false);
const documentError = ref('');

let videoLessonId = null;
let videoRequestId = 0;
let documentPreviewId = null;

const courseId = computed(() => Number(route.params.courseId));
const lessonIdParam = computed(() => (route.params.lessonId ? Number(route.params.lessonId) : null));
const currentLesson = computed(() => store.currentLesson);
const commentsEnabled = computed(() => currentLesson.value?.commentsEnabled !== false);
const studentRepliesEnabled = computed(
  () => commentsEnabled.value && currentLesson.value?.studentRepliesEnabled !== false,
);
const modules = computed(() => store.course?.modules ?? []);
const isCurrentCompleted = computed(() => (currentLesson.value ? store.isCompleted(currentLesson.value.id) : false));

const lockedPrerequisiteName = computed(() => (currentLesson.value ? store.requiredLessonName(currentLesson.value.id) : null));
const prerequisiteLesson = computed(() => {
  const requiredId = currentLesson.value?.requiredLessonId;

  return requiredId ? store.lessons.find((lesson) => lesson.id === Number(requiredId)) ?? null : null;
});
const isCurrentLocked = computed(() => (currentLesson.value ? store.isLocked(currentLesson.value.id) : false));
const currentLockReason = computed(() => (currentLesson.value ? store.lockReason(currentLesson.value.id) : null));
const currentScheduleAt = computed(() => (currentLesson.value ? store.scheduleAvailableAt(currentLesson.value.id) : null));
const isNextLocked = computed(() => (store.nextLesson ? store.isLocked(store.nextLesson.id) : false));
const isCurrentAwaitingWatch = computed(() => {
  const lesson = currentLesson.value;

  if (!lesson || isCurrentCompleted.value) {
    return false;
  }

  return lesson.contentType === 'video' && !!lesson.requireFullWatch && !!lesson.video && !watchedToEnd.value;
});

const showDocumentStage = computed(() => {
  const lesson = currentLesson.value;

  return !!lesson && lesson.contentType === 'document' && !!lesson.document;
});

const isVideoLesson = computed(() => currentLesson.value?.contentType === 'video');

const showMediaBlock = computed(() => {
  const lesson = currentLesson.value;

  if (!lesson) {
    return false;
  }

  return isCurrentLocked.value || isVideoLesson.value || showDocumentStage.value;
});

const supplementalDocument = computed(() => {
  const lesson = currentLesson.value;

  if (!lesson) {
    return null;
  }

  return lesson.contentType === 'document' ? null : lesson.document;
});

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

function resetVideoState() {
  videoLoading.value = false;
  videoEmbedUrl.value = null;
  videoPlatform.value = null;
  videoDuration.value = 0;
  videoResumeFrom.value = 0;
  videoLessonId = null;
  watchedToEnd.value = false;
}

async function loadVideoPlayback(lessonId) {
  const cid = courseId.value;

  if (!lessonId || !cid) {
    resetVideoState();

    return;
  }

  // Skip on-demand playback when the lesson's content is not a video. While the
  // course is still loading the lesson is unknown, so let the request proceed.
  const lesson = store.lessons.find((item) => item.id === Number(lessonId));

  if (lesson && lesson.contentType !== 'video') {
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

    if (videoDuration.value > 0 && videoResumeFrom.value >= videoDuration.value - 2) {
      watchedToEnd.value = true;
    }
  } catch (e) {
    if (requestId !== videoRequestId) {
      return;
    }

    // A lesson without a video is a valid state, not an error.
    if (e?.status !== 404) {
      toast.error(e?.message || 'Não foi possível carregar o vídeo.');
    }
  } finally {
    if (requestId === videoRequestId) {
      videoLoading.value = false;
    }
  }
}

function onVideoError(message) {
  toast.error(message || 'Não foi possível carregar o vídeo.');
}

function isPdf(path) {
  return /\.pdf$/i.test(path || '');
}

function revokeDocumentPreview() {
  if (documentPreviewUrl.value) {
    if (documentPreviewUrl.value.startsWith('blob:')) {
      URL.revokeObjectURL(documentPreviewUrl.value);
    }

    documentPreviewUrl.value = null;
  }
}

async function loadDocumentPreview(lesson) {
  revokeDocumentPreview();
  documentError.value = '';
  documentLoading.value = false;
  documentPreviewId = null;

  if (!lesson || lesson.contentType !== 'document' || !lesson.document) {
    return;
  }

  if (lesson.document.url) {
    documentPreviewUrl.value = lesson.document.url;

    return;
  }

  if (!isPdf(lesson.document.path)) {
    return;
  }

  const id = lesson.document.id;
  documentPreviewId = id;
  documentLoading.value = true;

  try {
    const { data } = await api.get(`/me/courses/${courseId.value}/documents/${id}`, {
      responseType: 'blob',
    });

    if (documentPreviewId !== id) {
      return;
    }

    documentPreviewUrl.value = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }));
  } catch (e) {
    if (documentPreviewId === id) {
      documentError.value = e?.message || 'Não foi possível carregar o documento.';
    }
  } finally {
    if (documentPreviewId === id) {
      documentLoading.value = false;
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
    return Promise.resolve();
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

    return Promise.resolve();
  }

  return api.post('/me/progress/video-position', payload).catch(() => {});
}

function onVideoProgress({ position, duration }) {
  videoTracking.lessonId = currentLesson.value?.id ?? null;
  videoTracking.courseId = courseId.value;
  videoTracking.teamId = store.progress?.team_id ?? null;
  videoTracking.position = position;
  videoTracking.duration = duration || videoTracking.duration;

  if (videoTracking.duration > 0 && position >= videoTracking.duration - 2) {
    watchedToEnd.value = true;
  }

  const now = Date.now();

  if (watchedToEnd.value || now - lastVideoSentAt >= 5000) {
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
});

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', handleBeforeUnload);
  flushVideoPosition();
  revokeDocumentPreview();
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

watch(() => studentTeams.selectedTeamId, () => {
  if (courseId.value) {
    store.load(courseId.value, true);
  }
});

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
  cancelReply();
  selectedStars.value = 0;
  hoverStars.value = 0;

  loadDocumentPreview(lesson);

  if (lesson) {
    loadVideoPlayback(lesson.id);
    openModule.value = lesson.moduleId;

    if (activeTab.value === 'comentarios' && !commentsEnabled.value) {
      activeTab.value = 'descricao';
    }

    if (activeTab.value === 'comentarios') {
      comments.fetch(lesson.id);
    }

    if (activeTab.value === 'avaliacoes') {
      ratings.fetch(lesson.id);
    }
  }
});

watch(
  [currentLesson, () => store.progress],
  ([lesson]) => {
    if (lesson && store.progress && !isCurrentLocked.value) {
      store.startLesson(lesson.id);
    }
  },
  { immediate: true },
);

watch(activeTab, (tab) => {
  if (!currentLesson.value) {
    return;
  }

  if (tab === 'comentarios' && commentsEnabled.value) {
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
    toast.success('Avaliação enviada.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível enviar sua avaliação.');
  }
}

function fileName(path) {
  return (path || '').split('/').pop() || 'arquivo';
}

async function downloadMaterial(type, material) {
  if (material.url) {
    window.open(material.url, '_blank', 'noopener');

    return;
  }

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
    toast.error(e?.message || 'Não foi possível baixar o arquivo.');
  } finally {
    downloadingId.value = null;
  }
}

async function submitComment() {
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
    toast.success('Comentário enviado.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível enviar seu comentário.');
  }
}

function startReply(commentId) {
  replyingTo.value = commentId;
  replyText.value = '';
}

function cancelReply() {
  replyingTo.value = null;
  replyText.value = '';
}

async function submitReply(commentId) {
  const content = replyText.value.trim();

  if (!content) {
    return;
  }

  try {
    await comments.reply({ parentId: commentId, content });

    cancelReply();
    toast.success('Resposta enviada.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível enviar sua resposta.');
  }
}

function isOwnComment(comment) {
  const userId = Number(auth.user?.id);

  return !!userId && Number(comment.user?.id) === userId;
}

async function removeComment(comment) {
  if (!isOwnComment(comment) || deletingId.value) {
    return;
  }

  const isRoot = !comment.parent_id;
  const hasReplies = isRoot && (comment.replies?.length ?? 0) > 0;
  const message = isRoot
    ? (hasReplies
      ? 'Excluir seu comentário? Todas as respostas também serão excluídas.'
      : 'Excluir seu comentário?')
    : 'Excluir sua resposta?';

  if (!window.confirm(message)) {
    return;
  }

  deletingId.value = comment.id;

  try {
    await comments.remove(comment.id, comment.parent_id);

    if (replyingTo.value === comment.id) {
      cancelReply();
    }

    toast.success(isRoot ? 'Comentário excluído.' : 'Resposta excluída.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível excluir.');
  } finally {
    deletingId.value = null;
  }
}

function goLesson(lessonId, force = false) {
  if (!lessonId) {
    return;
  }

  if (!force && store.isLocked(lessonId)) {
    if (store.lockReason(lessonId) === 'schedule') {
      toast.warning(`Esta aula estará disponível a partir de ${formatDate(store.scheduleAvailableAt(lessonId))}.`);

      return;
    }

    const name = store.requiredLessonName(lessonId);
    toast.warning(name
      ? `Conclua a aula "${name}" para liberar esta aula.`
      : 'Conclua a aula anterior para liberar esta aula.');

    return;
  }

  router.push({ name: 'aula', params: { courseId: courseId.value, lessonId } });
}

function toggleModule(moduleId) {
  openModule.value = openModule.value === moduleId ? null : moduleId;
}

function moduleLessons(module) {
  return [
    ...(module.topics ?? []).flatMap((topic) => topic.lessons ?? []),
    ...(module.lessons ?? []),
  ];
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

  if (isCurrentAwaitingWatch.value) {
    toast.error('Assista a aula até o final para concluí-la.');

    return;
  }

  completing.value = true;

  try {
    if (currentLesson.value.requireFullWatch && currentLesson.value.video) {
      videoTracking.lessonId = currentLesson.value.id;
      videoTracking.courseId = courseId.value;
      videoTracking.teamId = store.progress?.team_id ?? null;
      await sendVideoPosition();
    }

    await store.completeLesson(currentLesson.value.id);
    toast.success('Aula concluída.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível concluir a aula.');
  } finally {
    completing.value = false;
  }
}
</script>
