<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Comentários</h1>
        <p class="page-subtitle">Acompanhe os comentários das aulas, responda como equipe e modere as mensagens.</p>
      </div>
    </div>

    <div class="panel report-filters">
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
    </div>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <div v-else-if="!comments.length" class="empty-state">
      <strong>Nenhum comentário encontrado</strong>
      <span>Ajuste os filtros ou aguarde novos comentários dos alunos.</span>
    </div>

    <div v-else class="comment-admin-list">
      <article v-for="comment in comments" :key="comment.id" class="comment-admin-card">
        <header class="comment-admin-head">
          <span class="comment-admin-avatar">{{ initialsOf(comment.user?.name) }}</span>
          <div class="comment-admin-meta">
            <div class="comment-admin-author-row">
              <strong>{{ comment.user?.name || 'Aluno' }}</strong>
              <span v-if="comment.is_admin" class="comment-admin-badge">Equipe</span>
            </div>
            <span class="comment-admin-sub">
              {{ comment.lesson?.name || `Aula #${comment.lesson_id}` }} · {{ formatDateTime(comment.created_at) }}
            </span>
          </div>
          <button class="btn btn-danger btn-sm" :disabled="busyId === comment.id" @click="remove(comment)">
            Excluir
          </button>
        </header>

        <p class="comment-admin-text">{{ comment.content }}</p>

        <div v-if="comment.replies?.length" class="comment-admin-replies">
          <div v-for="reply in comment.replies" :key="reply.id" class="comment-admin-reply">
            <div class="comment-admin-reply-body">
              <div class="comment-admin-author-row">
                <strong>{{ reply.user?.name || 'Aluno' }}</strong>
                <span v-if="reply.is_admin" class="comment-admin-badge">Equipe</span>
                <span class="comment-admin-sub">{{ formatDateTime(reply.created_at) }}</span>
              </div>
              <p class="comment-admin-text">{{ reply.content }}</p>
            </div>
            <button class="comment-admin-remove" :disabled="busyId === reply.id" @click="remove(reply)">
              Excluir
            </button>
          </div>
        </div>

        <template v-if="comment.lesson?.comments_enabled">
          <div class="comment-admin-reply-form">
            <textarea
              v-model="replyDrafts[comment.id]"
              class="form-control"
              rows="2"
              placeholder="Responder como equipe..."
              style="resize:vertical;"
            ></textarea>
            <div class="comment-admin-reply-actions">
              <span v-if="comment.lesson?.student_replies_enabled === false" class="comment-admin-hint">
                Respostas de alunos desativadas nesta aula.
              </span>
              <button
                class="btn btn-primary btn-sm"
                :disabled="replyBusyId === comment.id || !(replyDrafts[comment.id] || '').trim()"
                @click="reply(comment)"
              >{{ replyBusyId === comment.id ? 'Enviando...' : 'Responder' }}</button>
            </div>
          </div>
        </template>
        <p v-else class="comment-admin-disabled">Os comentários estão desativados para esta aula.</p>
      </article>
    </div>

    <div v-if="meta.last_page > 1" class="admin-pager">
      <button class="btn btn-outline btn-sm" :disabled="meta.current_page <= 1" @click="goPage(meta.current_page - 1)">Anterior</button>
      <span class="admin-muted">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      <button class="btn btn-outline btn-sm" :disabled="meta.current_page >= meta.last_page" @click="goPage(meta.current_page + 1)">Próxima</button>
    </div>

  </main>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';
import { formatDateTime } from '../utils/format';
import { useToast } from '../composables/useToast';
import SearchSelect from '../components/admin/SearchSelect.vue';

const toast = useToast();

const comments = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(true);
const busyId = ref(null);
const replyBusyId = ref(null);
const replyDrafts = reactive({});
const filters = reactive({ course_id: '', lesson_id: '' });
const options = reactive({ course_id: [], lesson_id: [] });
const loadingOption = reactive({ course_id: false, lesson_id: false });

function initialsOf(name) {
  const parts = String(name || 'Aluno').trim().split(/\s+/).filter(Boolean);

  return `${parts[0]?.[0] ?? 'A'}${parts.length > 1 ? parts[parts.length - 1][0] : ''}`.toUpperCase();
}

async function load() {
  loading.value = true;

  try {
    const { data } = await api.get('/admin/comments', {
      params: {
        roots_only: 1,
        course_id: filters.course_id || undefined,
        lesson_id: filters.lesson_id || undefined,
        page: meta.value.current_page || 1,
        per_page: 15,
      },
    });

    comments.value = data.data ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0 };
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
  meta.value.current_page = 1;
  load();
}

function onLesson(value) {
  filters.lesson_id = value || '';
  meta.value.current_page = 1;
  load();
}

function goPage(page) {
  meta.value.current_page = page;
  load();
}

async function reply(comment) {
  const content = (replyDrafts[comment.id] || '').trim();

  if (!content) {
    return;
  }

  replyBusyId.value = comment.id;

  try {
    await api.post('/admin/comments', { parent_id: comment.id, content });
    replyDrafts[comment.id] = '';
    toast.success('Resposta enviada.');
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível enviar a resposta.');
  } finally {
    replyBusyId.value = null;
  }
}

async function remove(comment) {
  if (!window.confirm('Excluir este comentário?')) {
    return;
  }

  busyId.value = comment.id;

  try {
    await api.delete(`/admin/comments/${comment.id}`);
    toast.success('Comentário excluído.');
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível excluir o comentário.');
  } finally {
    busyId.value = null;
  }
}

onMounted(() => {
  load();
  loadOption('course_id', '', '/admin/courses');
  loadOption('lesson_id', '', '/admin/lessons');
});
</script>

<style scoped>
.comment-admin-list{display:flex;flex-direction:column;gap:14px;}
.comment-admin-card{background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:18px 20px;}
.comment-admin-head{display:flex;align-items:flex-start;gap:12px;}
.comment-admin-avatar{
  width:38px;height:38px;border-radius:50%;background:var(--accent-soft);color:var(--accent);
  display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0;
}
.comment-admin-meta{flex:1;min-width:0;display:flex;flex-direction:column;gap:3px;}
.comment-admin-author-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.comment-admin-author-row strong{font-size:13px;color:var(--text-primary);}
.comment-admin-sub{font-size:11.5px;color:var(--text-muted);}
.comment-admin-badge{
  font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;
  color:var(--accent);background:var(--accent-soft);border-radius:999px;padding:2px 7px;
}
.comment-admin-text{font-size:13.5px;line-height:1.6;color:var(--text-secondary);margin-top:10px;white-space:pre-line;}
.comment-admin-replies{margin-top:12px;display:flex;flex-direction:column;gap:8px;}
.comment-admin-reply{
  display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border-radius:12px;
  border:1px solid var(--border);background:var(--bg-card-alt);
}
.comment-admin-reply-body{flex:1;min-width:0;}
.comment-admin-remove{
  border:0;background:none;color:var(--text-muted);font-size:11.5px;font-weight:700;cursor:pointer;padding:0;
}
.comment-admin-remove:hover{color:#e5484d;}
.comment-admin-reply-form{margin-top:12px;display:flex;flex-direction:column;gap:8px;}
.comment-admin-reply-actions{display:flex;align-items:center;justify-content:flex-end;gap:12px;}
.comment-admin-hint{font-size:11.5px;color:var(--text-muted);margin-right:auto;}
.comment-admin-disabled{margin-top:12px;font-size:12px;color:var(--text-muted);}
</style>
