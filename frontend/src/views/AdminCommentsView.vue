<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Comentários</h1>
        <p class="page-subtitle">Acompanhe as dúvidas das aulas, responda como equipe e modere as mensagens.</p>
      </div>
      <span v-if="stats.awaiting" class="comment-pending-pill">
        <span class="comment-pending-dot"></span>
        {{ stats.awaiting }} aguardando resposta
      </span>
    </div>

    <div class="panel report-filters comment-filters">
      <div class="filter-grid">
        <div class="form-group">
          <label>Curso</label>
          <SearchSelect
            :model-value="filters.course_id || ''"
            remote
            allow-clear
            clear-label="Todos os cursos"
            :options="options.course_id"
            :loading="loadingOption.course_id"
            placeholder="Todos os cursos"
            @search="loadOption('course_id', $event, '/admin/courses')"
            @update:model-value="onCourse"
          />
        </div>
        <div class="form-group">
          <label>Aula</label>
          <SearchSelect
            :model-value="filters.lesson_id || ''"
            remote
            allow-clear
            clear-label="Todas as aulas"
            :options="options.lesson_id"
            :loading="loadingOption.lesson_id"
            placeholder="Todas as aulas"
            @search="loadOption('lesson_id', $event, '/admin/lessons')"
            @update:model-value="onLesson"
          />
        </div>
      </div>

      <div class="comment-filter-bottom">
        <div class="filter-tabs">
          <button
            v-for="tab in statusTabs"
            :key="tab.value || 'all'"
            type="button"
            class="filter-tab"
            :class="{ active: status === tab.value }"
            @click="setStatus(tab.value)"
          >
            {{ tab.label }}
            <span class="filter-tab-count">{{ tab.count }}</span>
          </button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="comment-feed-list">
      <article v-for="n in 3" :key="n" class="comment-card comment-card-skeleton">
        <span class="comment-skeleton-avatar"></span>
        <span class="comment-skeleton-lines">
          <span></span>
          <span></span>
          <span></span>
        </span>
      </article>
    </div>

    <div v-else-if="!comments.length" class="empty-state">
      <strong>Nenhum comentário encontrado</strong>
      <span>Ajuste os filtros ou aguarde novos comentários dos alunos.</span>
    </div>

    <div v-else class="comment-feed">
      <div class="comment-feed-head">
        <span class="admin-muted">{{ meta.total }} conversa(s)</span>
        <button v-if="!selecting" type="button" class="btn btn-outline btn-sm" @click="startSelecting">
          Selecionar
        </button>
        <button v-else type="button" class="btn btn-outline btn-sm" @click="cancelSelecting">
          Cancelar seleção
        </button>
      </div>

      <div v-if="selecting" class="comment-bulk-bar">
        <label class="comment-select-all">
          <input
            class="admin-check"
            type="checkbox"
            :checked="allPageSelected"
            :indeterminate="hasPageSelection && !allPageSelected"
            @change="toggleSelectPage"
          >
          <span>{{ allPageSelected ? 'Desmarcar todos desta página' : 'Selecionar todos desta página' }}</span>
        </label>
        <div class="comment-bulk-actions">
          <strong>{{ selectedIds.length }} selecionado(s)</strong>
          <button type="button" class="btn btn-outline btn-sm" @click="clearSelection">Limpar</button>
          <button
            type="button"
            class="btn btn-danger btn-sm"
            :disabled="!selectedIds.length"
            @click="bulkConfirm = true"
          >Excluir</button>
        </div>
      </div>

      <div class="comment-feed-list">
        <article
          v-for="comment in comments"
          :key="comment.id"
          class="comment-card"
          :class="{
            selected: selectedIds.includes(comment.id),
            'is-selecting': selecting,
          }"
        >
          <header class="comment-card-head">
            <input
              v-if="selecting"
              class="admin-check comment-card-check"
              type="checkbox"
              :checked="selectedIds.includes(comment.id)"
              :aria-label="`Selecionar comentário de ${comment.user?.name || 'Aluno'}`"
              @change="toggleSelection(comment.id)"
            >
            <span class="comment-admin-avatar">
              <img v-if="comment.user?.image_url" :src="comment.user.image_url" :alt="comment.user?.name || 'Aluno'">
              <span v-else>{{ initialsOf(comment.user?.name) }}</span>
            </span>

            <div class="comment-card-meta">
              <div class="comment-author-row">
                <strong>{{ comment.user?.name || 'Aluno' }}</strong>
                <span v-if="comment.is_admin" class="comment-admin-badge">Equipe</span>
                <span class="comment-state" :class="isPending(comment) ? 'is-pending' : 'is-answered'">
                  {{ isPending(comment) ? 'Aguardando' : 'Respondido' }}
                </span>
                <span class="comment-time">{{ formatDateTime(comment.created_at) }}</span>
              </div>

              <nav class="comment-context" aria-label="Local do comentário">
                <span>{{ comment.course?.name || 'Curso' }}</span>
                <span class="comment-context-sep">/</span>
                <span>{{ comment.module?.name || 'Módulo' }}</span>
                <span class="comment-context-sep">/</span>
                <strong>{{ comment.lesson?.name || `Aula #${comment.lesson_id}` }}</strong>
              </nav>
            </div>

          </header>

          <p class="comment-card-text">{{ comment.content }}</p>

          <div v-if="!selecting || replyCount(comment)" class="comment-card-toolbar">
            <button
              v-if="replyCount(comment)"
              type="button"
              class="comment-replies-toggle"
              :aria-expanded="isRepliesOpen(comment.id)"
              @click="toggleReplies(comment.id)"
            >
              <span>{{ replyCount(comment) }} resposta{{ replyCount(comment) === 1 ? '' : 's' }}</span>
              <span class="comment-chevron" :class="{ open: isRepliesOpen(comment.id) }"></span>
            </button>

            <div v-if="!selecting" class="comment-card-actions">
              <button
                v-if="comment.lesson?.comments_enabled"
                type="button"
                class="comment-action"
                :class="{ 'is-active': openReplyId === comment.id }"
                @click="toggleReply(comment)"
              >{{ openReplyId === comment.id ? 'Cancelar' : 'Responder' }}</button>
              <button type="button" class="comment-action is-danger" @click="askRemove(comment)">Excluir</button>
            </div>
          </div>

          <div v-if="comment.replies?.length && isRepliesOpen(comment.id)" class="comment-replies">
            <div
              v-for="reply in comment.replies"
              :key="reply.id"
              class="comment-reply-row"
              :class="{ 'is-team': reply.is_admin }"
            >
              <span class="comment-admin-avatar comment-admin-avatar-sm">
                <img v-if="reply.user?.image_url" :src="reply.user.image_url" :alt="reply.user?.name || 'Aluno'">
                <span v-else>{{ initialsOf(reply.user?.name) }}</span>
              </span>
              <div class="comment-reply-main">
                <div class="comment-author-row">
                  <strong>{{ reply.user?.name || 'Aluno' }}</strong>
                  <span v-if="reply.is_admin" class="comment-admin-badge">Equipe</span>
                  <span class="comment-admin-sub">{{ formatDateTime(reply.created_at) }}</span>
                  <button
                    type="button"
                    class="comment-admin-remove"
                    :disabled="removing && removeTarget?.id === reply.id"
                    @click="askRemove(reply)"
                  >Excluir</button>
                </div>
                <p class="comment-reply-text">{{ reply.content }}</p>
              </div>
            </div>
          </div>

          <div v-if="comment.lesson?.comments_enabled && openReplyId === comment.id" class="comment-reply-form">
            <label :for="`comment-reply-${comment.id}`">Responder como equipe</label>
            <textarea
              :id="`comment-reply-${comment.id}`"
              v-model="replyDrafts[comment.id]"
              class="form-control"
              rows="2"
              placeholder="Escreva uma resposta para o aluno..."
              style="resize:vertical;"
              @keydown.ctrl.enter.prevent="reply(comment)"
            ></textarea>
            <div class="comment-reply-actions">
              <span v-if="comment.lesson?.student_replies_enabled === false" class="comment-admin-hint">
                Respostas de alunos desativadas nesta aula.
              </span>
              <button
                class="btn btn-primary btn-sm"
                :disabled="replyBusyId !== null || !(replyDrafts[comment.id] || '').trim()"
                @click="reply(comment)"
              >{{ replyBusyId === comment.id ? 'Enviando...' : 'Enviar resposta' }}</button>
            </div>
          </div>

          <p v-if="!comment.lesson?.comments_enabled" class="comment-admin-disabled">Os comentários estão desativados para esta aula.</p>
        </article>
      </div>

      <div v-if="meta.last_page > 1" class="admin-pager comment-pager">
        <button class="btn btn-outline btn-sm" :disabled="meta.current_page <= 1" @click="goPage(meta.current_page - 1)">Anterior</button>
        <span class="admin-muted">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <button class="btn btn-outline btn-sm" :disabled="meta.current_page >= meta.last_page" @click="goPage(meta.current_page + 1)">Próxima</button>
      </div>
    </div>

    <ConfirmModal
      v-if="removeTarget"
      :title="removeTarget.parent_id ? 'Excluir resposta' : 'Excluir comentário'"
      :message="removeTarget.parent_id
        ? 'Esta resposta será removida permanentemente.'
        : 'Este comentário e todas as suas respostas serão removidos permanentemente.'"
      confirm-label="Excluir"
      :busy="removing"
      @cancel="removeTarget = null"
      @confirm="confirmRemove"
    />

    <ConfirmModal
      v-if="bulkConfirm"
      title="Excluir comentários selecionados"
      :message="`${selectedIds.length} comentário(s) serão removidos. Comentários principais também excluem todas as respostas.`"
      confirm-label="Excluir selecionados"
      :busy="bulkBusy"
      @cancel="bulkConfirm = false"
      @confirm="confirmBulkRemove"
    />

  </main>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';
import { formatDateTime } from '../utils/format';
import { useToast } from '../composables/useToast';
import SearchSelect from '../components/admin/SearchSelect.vue';
import ConfirmModal from '../components/admin/ConfirmModal.vue';

const toast = useToast();

const comments = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const stats = ref({ total: 0, awaiting: 0, answered: 0 });
const loading = ref(true);
const status = ref('');
const replyDrafts = reactive({});
const replyBusyId = ref(null);
const expandedReplies = ref([]);
const openReplyId = ref(null);
const removeTarget = ref(null);
const removing = ref(false);
const selecting = ref(false);
const selectedIds = ref([]);
const bulkConfirm = ref(false);
const bulkBusy = ref(false);

const filters = reactive({ course_id: '', lesson_id: '' });
const options = reactive({ course_id: [], lesson_id: [] });
const loadingOption = reactive({ course_id: false, lesson_id: false });

const statusTabs = computed(() => [
  { value: '', label: 'Todos', count: stats.value.total },
  { value: 'awaiting', label: 'Aguardando', count: stats.value.awaiting },
  { value: 'answered', label: 'Respondidos', count: stats.value.answered },
]);

const allPageSelected = computed(
  () => comments.value.length > 0
    && comments.value.every((comment) => selectedIds.value.includes(comment.id)),
);

const hasPageSelection = computed(
  () => comments.value.some((comment) => selectedIds.value.includes(comment.id)),
);

function initialsOf(name) {
  const parts = String(name || 'Aluno').trim().split(/\s+/).filter(Boolean);

  return `${parts[0]?.[0] ?? 'A'}${parts.length > 1 ? parts[parts.length - 1][0] : ''}`.toUpperCase();
}

function isPending(comment) {
  if (typeof comment.awaiting_reply === 'boolean') {
    return comment.awaiting_reply;
  }

  return !(comment.replies ?? []).some((reply) => reply.is_admin);
}

function replyCount(comment) {
  return Number(comment.replies_count ?? comment.replies?.length ?? 0);
}

function isRepliesOpen(id) {
  return expandedReplies.value.includes(id);
}

function toggleReplies(id) {
  expandedReplies.value = isRepliesOpen(id)
    ? expandedReplies.value.filter((commentId) => commentId !== id)
    : [...expandedReplies.value, id];
}

function toggleReply(comment) {
  if (openReplyId.value === comment.id) {
    cancelReply(comment);
    return;
  }

  openReplyId.value = comment.id;

  if (!isRepliesOpen(comment.id)) {
    expandedReplies.value = [...expandedReplies.value, comment.id];
  }

  nextTick(() => document.getElementById(`comment-reply-${comment.id}`)?.focus());
}

function cancelReply(comment) {
  openReplyId.value = null;
  replyDrafts[comment.id] = '';
}

async function load({ silent = false } = {}) {
  if (!silent) {
    loading.value = true;
  }

  try {
    const { data } = await api.get('/admin/comments', {
      params: {
        roots_only: 1,
        status: status.value || undefined,
        course_id: filters.course_id || undefined,
        lesson_id: filters.lesson_id || undefined,
        page: meta.value.current_page || 1,
        per_page: 20,
      },
    });

    comments.value = data.data ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };

    if (data.meta?.stats) {
      stats.value = data.meta.stats;
    }

    if (selecting.value) {
      const pageIds = new Set(comments.value.map((comment) => comment.id));
      selectedIds.value = selectedIds.value.filter((id) => pageIds.has(id));
    }
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar os comentários.');
  } finally {
    loading.value = false;
  }
}

async function loadOption(key, search, endpoint) {
  loadingOption[key] = true;

  try {
    const { data } = await api.get(endpoint, {
      params: { search: search || undefined, per_page: 20 },
    });

    options[key] = (data.data ?? []).map((item) => ({ value: item.id, label: item.name }));
  } catch (e) {
    options[key] = [];
  } finally {
    loadingOption[key] = false;
  }
}

function onCourse(value) {
  filters.course_id = value || '';
  filters.lesson_id = '';
  resetPage();
  load();
}

function onLesson(value) {
  filters.lesson_id = value || '';
  resetPage();
  load();
}

function setStatus(value) {
  status.value = value;
  resetPage();
  load();
}

function resetPage() {
  meta.value.current_page = 1;
  openReplyId.value = null;
  expandedReplies.value = [];
  cancelSelecting();
}

function goPage(page) {
  meta.value.current_page = page;
  openReplyId.value = null;
  expandedReplies.value = [];
  cancelSelecting();
  load();
}

function startSelecting() {
  selecting.value = true;
  selectedIds.value = [];
  openReplyId.value = null;
}

function cancelSelecting() {
  selecting.value = false;
  selectedIds.value = [];
}

function clearSelection() {
  selectedIds.value = [];
}

function toggleSelection(id) {
  selectedIds.value = selectedIds.value.includes(id)
    ? selectedIds.value.filter((selectedId) => selectedId !== id)
    : [...selectedIds.value, id];
}

function toggleSelectPage() {
  const pageIds = comments.value.map((comment) => comment.id);

  if (allPageSelected.value) {
    selectedIds.value = selectedIds.value.filter((id) => !pageIds.includes(id));
    return;
  }

  selectedIds.value = [...new Set([...selectedIds.value, ...pageIds])];
}

async function reply(comment) {
  const content = String(replyDrafts[comment.id] || '').trim();

  if (!content || replyBusyId.value) {
    return;
  }

  replyBusyId.value = comment.id;

  try {
    await api.post('/admin/comments', { parent_id: comment.id, content });
    replyDrafts[comment.id] = '';
    openReplyId.value = null;
    toast.success('Resposta enviada.');
    await load({ silent: true });
  } catch (e) {
    toast.error(e?.message || 'Não foi possível enviar a resposta.');
  } finally {
    replyBusyId.value = null;
  }
}

function askRemove(comment) {
  removeTarget.value = comment;
}

async function confirmRemove() {
  const target = removeTarget.value;

  if (!target) {
    return;
  }

  removing.value = true;

  try {
    await api.delete(`/admin/comments/${target.id}`);
    toast.success(target.parent_id ? 'Resposta excluída.' : 'Comentário excluído.');
    removeTarget.value = null;
    await load({ silent: true });

    if (!comments.value.length && meta.value.current_page > 1) {
      meta.value.current_page -= 1;
      await load();
    }
  } catch (e) {
    toast.error(e?.message || 'Não foi possível excluir o comentário.');
  } finally {
    removing.value = false;
  }
}

async function confirmBulkRemove() {
  if (!selectedIds.value.length) {
    return;
  }

  bulkBusy.value = true;

  try {
    const ids = [...selectedIds.value];
    const { data } = await api.delete('/admin/comments/bulk', { data: { ids } });

    toast.success(`${data.data?.deleted ?? ids.length} comentário(s) excluído(s).`);
    bulkConfirm.value = false;
    cancelSelecting();
    await load({ silent: true });

    if (!comments.value.length && meta.value.current_page > 1) {
      meta.value.current_page -= 1;
      await load();
    }
  } catch (e) {
    toast.error(e?.message || 'Não foi possível excluir os comentários.');
  } finally {
    bulkBusy.value = false;
  }
}

onMounted(() => {
  load();
  loadOption('course_id', '', '/admin/courses');
  loadOption('lesson_id', '', '/admin/lessons');
});
</script>

<style scoped>
.comment-pending-pill{
  display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;
  background:rgba(245,158,11,.12);color:#d97706;font-size:12px;font-weight:800;
}
.comment-pending-dot{width:7px;height:7px;border-radius:50%;background:#f59e0b;flex-shrink:0;}
.comment-filters{gap:14px;}
.comment-filters .filter-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;}
.comment-filter-bottom{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.filter-tab-count{
  display:inline-flex;align-items:center;justify-content:center;min-width:19px;height:19px;padding:0 5px;
  margin-left:5px;border-radius:999px;background:color-mix(in srgb,currentColor 12%,transparent);
  font-size:10.5px;line-height:1;
}
.comment-feed{display:flex;flex-direction:column;gap:10px;}
.comment-feed-head{display:flex;align-items:center;justify-content:space-between;gap:12px;}
.comment-feed-list{display:flex;flex-direction:column;gap:10px;}
.comment-card{
  background:var(--bg-card);border:1px solid var(--border);border-radius:14px;
  padding:15px 16px;box-shadow:var(--shadow-sm);transition:background .15s ease,box-shadow .15s ease;
}
.comment-card.selected{background:var(--accent-soft);}
.comment-card-head{display:flex;align-items:flex-start;gap:10px;}
.comment-card-check{margin-top:9px;flex-shrink:0;}
.comment-card-meta{display:flex;flex-direction:column;gap:3px;flex:1;min-width:0;}
.comment-author-row{display:flex;align-items:center;gap:6px;flex-wrap:wrap;min-width:0;}
.comment-author-row strong{font-size:12.5px;color:var(--text-primary);}
.comment-time{font-size:10.5px;color:var(--text-muted);margin-left:auto;white-space:nowrap;}
.comment-context{
  display:flex;align-items:center;gap:5px;flex-wrap:wrap;
  font-size:11px;color:var(--text-muted);overflow-wrap:anywhere;
}
.comment-context strong{font-size:11px;color:var(--text-primary);}
.comment-context-sep{opacity:.45;}
.comment-card-text{
  margin-top:10px;font-size:13px;line-height:1.55;color:var(--text-secondary);
  white-space:pre-line;overflow-wrap:anywhere;
}
.comment-card-toolbar{
  display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;
  margin-top:10px;padding-top:8px;border-top:1px solid var(--border);
}
.comment-card-actions{display:flex;align-items:center;gap:2px;margin-left:auto;}
.comment-action{
  border:0;background:none;color:var(--text-muted);font:inherit;font-size:11.5px;font-weight:800;
  cursor:pointer;padding:4px 7px;border-radius:7px;
}
.comment-action:hover{background:var(--bg-card-alt);color:var(--text-primary);}
.comment-action.is-active{color:var(--accent);background:var(--accent-soft);}
.comment-action.is-danger:hover{color:#e5484d;background:rgba(229,72,77,.08);}
.comment-replies-toggle{
  display:inline-flex;align-items:center;gap:6px;border:0;background:none;padding:4px 0;
  color:var(--text-muted);font:inherit;font-size:11.5px;font-weight:700;cursor:pointer;
}
.comment-replies-toggle:hover{color:var(--text-primary);}
.comment-chevron{
  width:6px;height:6px;border-right:1.5px solid currentColor;border-bottom:1.5px solid currentColor;
  transform:rotate(45deg);transition:transform .15s ease;margin-top:-2px;
}
.comment-chevron.open{transform:rotate(225deg);margin-top:2px;}
.comment-state{
  display:inline-flex;align-items:center;padding:2px 7px;border-radius:999px;
  font-size:10px;font-weight:800;letter-spacing:.03em;text-transform:uppercase;
}
.comment-state.is-pending{background:rgba(245,158,11,.14);color:#d97706;}
.comment-state.is-answered{background:rgba(34,197,94,.13);color:#16a34a;}
.comment-admin-avatar{
  width:34px;height:34px;border-radius:50%;background:var(--accent-soft);color:var(--accent);
  display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0;overflow:hidden;
}
.comment-admin-avatar img{width:100%;height:100%;object-fit:cover;}
.comment-admin-avatar-sm{width:26px;height:26px;font-size:9.5px;}
.comment-admin-badge{
  font-size:9px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;
  color:var(--accent);background:var(--accent-soft);border-radius:999px;padding:2px 6px;
}
.comment-admin-sub{font-size:10.5px;color:var(--text-muted);}
.comment-replies{display:flex;flex-direction:column;gap:6px;margin-top:8px;}
.comment-reply-row{
  display:flex;align-items:flex-start;gap:8px;padding:8px 10px;border-radius:10px;
  border:1px solid var(--border);background:var(--bg-card-alt);
}
.comment-reply-row.is-team{
  border-color:color-mix(in srgb,var(--accent) 28%,var(--border));
  background:color-mix(in srgb,var(--accent-soft) 42%,var(--bg-card));
}
.comment-reply-main{flex:1;min-width:0;}
.comment-reply-text{
  margin-top:3px;font-size:12px;line-height:1.55;color:var(--text-secondary);
  white-space:pre-line;overflow-wrap:anywhere;
}
.comment-admin-remove{
  margin-left:auto;border:0;background:none;color:var(--text-muted);font-size:10.5px;font-weight:700;
  cursor:pointer;padding:0;
}
.comment-admin-remove:hover{color:#e5484d;}
.comment-reply-form{
  display:flex;flex-direction:column;gap:7px;margin-top:8px;padding:10px;
  border:1px solid var(--border);border-radius:12px;background:var(--bg-card-alt);
}
.comment-reply-form label{font-size:11.5px;font-weight:800;color:var(--text-primary);}
.comment-reply-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;}
.comment-admin-hint{font-size:10.5px;color:var(--text-muted);margin-right:auto;}
.comment-admin-disabled{margin-top:8px;border-top:1px solid var(--border);padding-top:8px;font-size:11px;color:var(--text-muted);}
.comment-bulk-bar{
  display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 14px;
  background:var(--accent-soft);border:1px solid var(--border);border-radius:12px;font-size:12px;
}
.comment-select-all{
  display:inline-flex;align-items:center;gap:8px;cursor:pointer;
  font-size:12px;font-weight:700;color:var(--text-secondary);
}
.comment-select-all input{flex-shrink:0;}
.comment-bulk-actions{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.comment-bulk-actions strong{font-size:11.5px;color:var(--text-secondary);white-space:nowrap;}
.comment-pager{padding-top:4px;}
.comment-card-skeleton{display:flex;gap:10px;align-items:flex-start;}
.comment-skeleton-avatar{width:34px;height:34px;border-radius:50%;background:var(--bg-card-alt);flex-shrink:0;}
.comment-skeleton-lines{display:flex;flex-direction:column;gap:7px;flex:1;}
.comment-skeleton-lines span{height:9px;border-radius:999px;background:var(--bg-card-alt);}
.comment-skeleton-lines span:nth-child(1){width:32%;}
.comment-skeleton-lines span:nth-child(2){width:70%;}
.comment-skeleton-lines span:nth-child(3){width:52%;}

@media (max-width:900px){
  .comment-card{padding:14px;}
}
@media (max-width:560px){
  .comment-pending-pill{font-size:11px;}
  .comment-card{padding:13px 14px;}
  .comment-card-head{gap:9px;}
  .comment-card-text{font-size:12.8px;}
  .comment-card-toolbar{gap:6px;}
  .comment-card-actions{gap:0;}
  .comment-action{padding:4px 6px;}
  .comment-reply-row{padding:8px 9px;}
  .comment-reply-form{padding:8px;}
  .comment-filters .filter-grid{grid-template-columns:1fr;}
  .comment-filter-bottom{align-items:flex-start;}
  .comment-feed-head{align-items:flex-start;}
  .comment-bulk-bar{align-items:flex-start;flex-wrap:wrap;}
}
</style>
