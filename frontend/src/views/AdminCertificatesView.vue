<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">{{ isScoped ? 'Certificados do curso' : 'Certificados' }}</h1>
        <p class="page-subtitle">
          <template v-if="isScoped">{{ scopedCourseLabel }} · todas as versões.</template>
          <template v-else>Crie modelos de certificado, personalize o visual e vincule a um curso.</template>
        </p>
      </div>
      <div class="certificate-head-actions">
        <router-link v-if="isScoped" class="btn btn-outline btn-sm" :to="{ name: 'admin-certificados' }">Ver todos os certificados</router-link>
        <button v-if="tab === 'templates'" class="btn btn-primary" @click="openCreate">
          {{ showForm ? 'Cancelar' : (isScoped ? 'Nova versão' : 'Novo certificado') }}
        </button>
      </div>
    </div>

    <div class="filter-tabs">
      <button class="filter-tab" :class="{ active: tab === 'templates' }" @click="switchTab('templates')">Modelos</button>
      <button class="filter-tab" :class="{ active: tab === 'issued' }" @click="switchTab('issued')">Emitidos</button>
    </div>

    <!-- Modelos: formulário -->
    <form v-if="tab === 'templates' && showForm" class="panel" @submit.prevent="save">
      <h2 class="panel-title">{{ editingId ? `Editar certificado #${editingId}` : (isScoped ? 'Nova versão do certificado' : 'Novo certificado') }}</h2>
      <p class="panel-desc">
        {{ isScoped ? 'A versão mais recente do curso é usada nas próximas emissões.' : 'O modelo fica vinculado a um curso e é usado ao emitir o certificado do aluno.' }}
      </p>

      <div class="certificate-form-grid">
        <div class="certificate-form-fields">
          <div class="form-row">
            <div class="form-group">
              <label for="c-name">Nome interno</label>
              <input id="c-name" class="form-control" v-model="form.name" required placeholder="Certificado padrão">
            </div>
            <div class="form-group">
              <label for="c-version">Versão</label>
              <input id="c-version" class="form-control" v-model="form.version" placeholder="1.0">
            </div>
          </div>

          <div class="form-group">
            <label for="c-title">Título do certificado</label>
            <input id="c-title" class="form-control" v-model="form.title" required placeholder="Certificado de Conclusão">
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Curso vinculado</label>
              <SearchSelect
                v-model="form.course_id"
                remote
                :options="courseOptions"
                :loading="loadingCourses"
                :disabled="isScoped"
                placeholder="Selecione o curso"
                @search="loadCourses"
              />
            </div>
            <div class="form-group">
              <label for="c-time">Carga horária (h)</label>
              <input id="c-time" type="number" min="0" class="form-control" v-model="form.time">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="c-grade">Nota mínima (opcional)</label>
              <input id="c-grade" type="number" min="0" max="100" class="form-control" v-model="form.grade">
            </div>
            <div class="form-group">
              <label for="c-signatory-name">Nome do assinante</label>
              <input id="c-signatory-name" class="form-control" v-model="form.signatory_name" placeholder="Maria Silva">
            </div>
          </div>

          <div class="form-group">
            <label for="c-signatory-title">Cargo do assinante</label>
            <input id="c-signatory-title" class="form-control" v-model="form.signatory_title" placeholder="Diretora Acadêmica">
          </div>

          <div class="form-group">
            <label for="c-body">Texto do certificado</label>
            <textarea id="c-body" class="form-control" rows="4" v-model="form.body" placeholder="Certificamos que {aluno} concluiu o curso {curso}..."></textarea>
            <span class="certificate-placeholder-hint">
              Variáveis: <code>{aluno}</code> <code>{curso}</code> <code>{data}</code> <code>{carga_horaria}</code> <code>{codigo}</code>
            </span>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Imagem de fundo</label>
              <FilePicker
                accept="image/png,image/jpeg,image/webp"
                title="Escolher fundo"
                hint="PNG/JPG/WebP · até 4 MB"
                :file-name="form.backgroundFile?.name"
                @change="onBackgroundPicked"
              />
            </div>
            <div class="form-group">
              <label>Assinatura</label>
              <FilePicker
                accept="image/png,image/jpeg,image/webp"
                title="Escolher assinatura"
                hint="PNG/JPG/WebP · até 4 MB"
                :file-name="form.signatureFile?.name"
                @change="onSignaturePicked"
              />
            </div>
          </div>

          <div>
            <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar certificado' }}</button>
          </div>
        </div>

        <div class="certificate-form-preview">
          <span class="certificate-preview-label">Pré-visualização</span>
          <CertificateCanvas :certificate="previewCertificate" :values="previewValues" />
        </div>
      </div>
    </form>

    <!-- Modelos: lista -->
    <template v-if="tab === 'templates' && !showForm">
      <div class="catalog-toolbar">
        <input
          class="form-control admin-search"
          type="search"
          v-model="search"
          placeholder="Buscar certificado..."
          @input="onSearch"
        >
        <div class="certificate-course-filter">
          <SearchSelect
            v-if="!isScoped"
            v-model="courseFilter"
            remote
            allow-clear
            clear-label="Todos os cursos"
            :options="filterCourseOptions"
            :loading="loadingFilterCourses"
            placeholder="Todos os cursos"
            @search="loadFilterCourses"
            @update:model-value="reload"
          />
          <span v-else class="certificate-scope-chip">Curso: {{ scopedCourseLabel }}</span>
        </div>
      </div>

      <p v-if="loading" class="page-subtitle">Carregando...</p>

      <div v-else-if="templates.length === 0" class="empty-state">
        <strong>Nenhum certificado encontrado</strong>
        <span>Cadastre o primeiro modelo e vincule a um curso.</span>
      </div>

      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Certificado</th><th>Curso</th><th>Versão</th><th>Carga</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="certificate in templates" :key="certificate.id">
              <td>
                <strong>{{ certificate.title || certificate.name }}</strong>
                <span class="admin-sub">{{ certificate.name }} · #{{ certificate.id }}</span>
              </td>
              <td class="admin-muted">{{ certificate.course?.name || '—' }}</td>
              <td class="admin-muted">
                {{ certificate.version || '—' }}
                <span v-if="certificate.id === currentTemplate?.id" class="certificate-current">Atual</span>
              </td>
              <td class="admin-muted">{{ certificate.time ? `${certificate.time}h` : '—' }}</td>
              <td class="admin-actions">
                <button class="btn btn-outline btn-sm" @click="openEdit(certificate)">Editar</button>
                <button class="btn btn-danger btn-sm" :disabled="busyId === certificate.id" @click="remove(certificate)">Excluir</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="meta.last_page > 1" class="admin-pager">
        <button class="btn btn-outline btn-sm" :disabled="meta.current_page <= 1" @click="goPage(meta.current_page - 1)">Anterior</button>
        <span class="admin-muted">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <button class="btn btn-outline btn-sm" :disabled="meta.current_page >= meta.last_page" @click="goPage(meta.current_page + 1)">Próxima</button>
      </div>
    </template>

    <!-- Emitidos -->
    <template v-if="tab === 'issued'">
      <p v-if="issuedLoading" class="page-subtitle">Carregando...</p>

      <div v-else-if="issued.length === 0" class="empty-state">
        <strong>Nenhum certificado emitido</strong>
        <span>Os certificados aparecem aqui quando um aluno conclui todas as aulas do curso.</span>
      </div>

      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Aluno</th><th>Curso</th><th>Turma</th><th>Emitido em</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="item in issued" :key="item.id">
              <td>
                <strong>{{ item.user?.name || '—' }}</strong>
                <span class="admin-sub">Código: {{ item.code }}</span>
              </td>
              <td class="admin-muted">{{ item.certificate?.course?.name || item.certificate?.title || '—' }}</td>
              <td class="admin-muted">{{ item.team?.name || '—' }}</td>
              <td class="admin-muted">{{ formatDateTime(item.issued_at) }}</td>
              <td>
                <span class="status-pill" :class="item.invalidated_at ? 'inactive' : 'active'">
                  {{ item.invalidated_at ? 'Revogado' : 'Válido' }}
                </span>
              </td>
              <td class="admin-actions">
                <button
                  v-if="!item.invalidated_at"
                  class="btn btn-danger btn-sm"
                  @click="openRevoke(item)"
                >Revogar</button>
                <span v-else class="admin-muted">{{ item.invalidation_reason || '—' }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="issuedMeta.last_page > 1" class="admin-pager">
        <button class="btn btn-outline btn-sm" :disabled="issuedMeta.current_page <= 1" @click="goIssuedPage(issuedMeta.current_page - 1)">Anterior</button>
        <span class="admin-muted">Página {{ issuedMeta.current_page }} de {{ issuedMeta.last_page }}</span>
        <button class="btn btn-outline btn-sm" :disabled="issuedMeta.current_page >= issuedMeta.last_page" @click="goIssuedPage(issuedMeta.current_page + 1)">Próxima</button>
      </div>
    </template>

    <ConfirmModal
      v-if="revokeTarget"
      title="Revogar certificado"
      :message="`Revogar o certificado de ${revokeTarget.user?.name || 'aluno'}? O código deixa de ser validado.`"
      confirm-label="Revogar"
      :busy="revoking"
      @cancel="closeRevoke"
      @confirm="confirmRevoke"
    >
      <div class="form-group">
        <label for="revoke-reason">Motivo (opcional)</label>
        <textarea id="revoke-reason" class="form-control" rows="3" v-model="revokeReason" placeholder="Ex.: dados incorretos"></textarea>
      </div>
    </ConfirmModal>

  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useCertificatesStore } from '../stores/certificates';
import { useToast } from '../composables/useToast';
import { api } from '../api/http';
import { formatDateTime } from '../utils/format';
import FilePicker from '../components/admin/FilePicker.vue';
import SearchSelect from '../components/admin/SearchSelect.vue';
import ConfirmModal from '../components/admin/ConfirmModal.vue';
import CertificateCanvas from '../components/CertificateCanvas.vue';

const store = useCertificatesStore();
const toast = useToast();
const route = useRoute();

const scopedCourseId = computed(() => {
  const value = Number(route.query.course_id);

  return Number.isInteger(value) && value > 0 ? value : null;
});
const isScoped = computed(() => scopedCourseId.value !== null);
const scopedCourse = ref(null);
const currentTemplate = ref(null);
const scopedCourseLabel = computed(() => scopedCourse.value?.name || 'Curso selecionado');

const templates = computed(() => store.templates);
const meta = computed(() => store.meta);
const loading = computed(() => store.loading);
const saving = computed(() => store.saving);
const issued = computed(() => store.issued);
const issuedMeta = computed(() => store.issuedMeta);
const issuedLoading = computed(() => store.issuedLoading);

const tab = ref('templates');
const search = ref('');
const courseFilter = ref('');
const busyId = ref(null);

const showForm = ref(false);
const editingId = ref(null);
const copyFromId = ref(null);

const courseOptions = ref([]);
const loadingCourses = ref(false);
const filterCourseOptions = ref([]);
const loadingFilterCourses = ref(false);

const revokeTarget = ref(null);
const revokeReason = ref('');
const revoking = ref(false);

const form = reactive({
  name: '',
  title: '',
  course_id: '',
  grade: '',
  version: '',
  time: 0,
  body: '',
  signatory_name: '',
  signatory_title: '',
  backgroundFile: null,
  signatureFile: null,
  backgroundPreview: '',
  signaturePreview: '',
});

const previewValues = reactive({
  aluno: 'Nome do Aluno',
  curso: 'Nome do Curso',
  data: '00/00/0000',
  carga_horaria: 0,
  codigo: '0000-0000-0000',
});

const previewCertificate = computed(() => ({
  title: form.title,
  body: form.body,
  signatory_name: form.signatory_name,
  signatory_title: form.signatory_title,
  background_url: form.backgroundPreview,
  signature_url: form.signaturePreview,
}));

function revokeBlob(url) {
  if (url && url.startsWith('blob:')) {
    URL.revokeObjectURL(url);
  }
}

function resetForm() {
  form.name = '';
  form.title = '';
  form.course_id = '';
  form.grade = '';
  form.version = '';
  form.time = 0;
  form.body = '';
  form.signatory_name = '';
  form.signatory_title = '';
  form.backgroundFile = null;
  form.signatureFile = null;
  revokeBlob(form.backgroundPreview);
  revokeBlob(form.signaturePreview);
  form.backgroundPreview = '';
  form.signaturePreview = '';
  courseOptions.value = [];
  copyFromId.value = null;
}

async function loadCourses(term = '') {
  loadingCourses.value = true;

  try {
    const { data } = await api.get('/admin/courses', { params: { search: term || undefined, per_page: 20 } });
    courseOptions.value = (data.data ?? []).map((course) => ({ value: course.id, label: course.name }));
  } catch (e) {
    courseOptions.value = [];
  } finally {
    loadingCourses.value = false;
  }
}

async function loadFilterCourses(term = '') {
  loadingFilterCourses.value = true;

  try {
    const { data } = await api.get('/admin/courses', { params: { search: term || undefined, per_page: 20 } });
    filterCourseOptions.value = (data.data ?? []).map((course) => ({ value: course.id, label: course.name }));
  } catch (e) {
    filterCourseOptions.value = [];
  } finally {
    loadingFilterCourses.value = false;
  }
}

async function refreshCurrentTemplate() {
  if (!scopedCourseId.value) {
    currentTemplate.value = null;

    return;
  }

  try {
    const { data } = await api.get('/admin/certificates', {
      params: { course_id: scopedCourseId.value, per_page: 1 },
    });
    currentTemplate.value = data.data?.[0] ?? null;
  } catch (e) {
    currentTemplate.value = null;
  }
}

async function loadScopedContext() {
  if (!scopedCourseId.value) {
    scopedCourse.value = null;
    currentTemplate.value = null;

    return;
  }

  try {
    const [courseResponse] = await Promise.all([
      api.get(`/admin/courses/${scopedCourseId.value}`),
      refreshCurrentTemplate(),
    ]);
    scopedCourse.value = courseResponse.data.data;
  } catch (e) {
    scopedCourse.value = null;
    toast.error(e?.message || 'Não foi possível carregar o curso.');
  }
}

function nextVersion(version) {
  const text = String(version ?? '').trim();
  const match = text.match(/^(\d+)(\.\d+)*$/);

  if (!match) {
    return '1.0';
  }

  return `${Number(match[1]) + 1}${text.includes('.') ? '.0' : ''}`;
}

function cloneTemplate(source) {
  form.name = source.name ?? '';
  form.title = source.title ?? '';
  form.course_id = source.course_id ?? scopedCourseId.value ?? '';
  form.grade = source.grade ?? '';
  form.time = source.time ?? 0;
  form.body = source.body ?? '';
  form.signatory_name = source.signatory_name ?? '';
  form.signatory_title = source.signatory_title ?? '';
  form.backgroundFile = null;
  form.signatureFile = null;
  revokeBlob(form.backgroundPreview);
  revokeBlob(form.signaturePreview);
  form.backgroundPreview = source.background_url ?? '';
  form.signaturePreview = source.signature_url ?? '';
  form.version = nextVersion(source.version);
  copyFromId.value = source.id;
}

let searchTimer = null;

function onSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(reload, 350);
}

function reload() {
  meta.value.current_page = 1;
  load();
}

function load() {
  return store.fetchTemplates({
    search: search.value || undefined,
    course_id: scopedCourseId.value || courseFilter.value || undefined,
    page: meta.value.current_page || 1,
    per_page: 15,
  });
}

function goPage(page) {
  meta.value.current_page = page;
  load();
}

function loadIssued() {
  return store.fetchIssued({
    course_id: scopedCourseId.value || undefined,
    page: issuedMeta.value.current_page || 1,
    per_page: 15,
  });
}

function goIssuedPage(page) {
  issuedMeta.value.current_page = page;
  loadIssued();
}

function switchTab(value) {
  tab.value = value;
  showForm.value = false;

  if (value === 'issued') {
    loadIssued();
  } else {
    load();
  }
}

async function openCreate() {
  if (showForm.value) {
    showForm.value = false;
    return;
  }

  editingId.value = null;
  resetForm();

  if (isScoped.value) {
    form.course_id = scopedCourseId.value;
    courseOptions.value = scopedCourse.value
      ? [{ value: scopedCourse.value.id, label: scopedCourse.value.name }]
      : [];

    await refreshCurrentTemplate();

    if (currentTemplate.value) {
      cloneTemplate(currentTemplate.value);
    } else {
      form.version = '1.0';
    }

    showForm.value = true;

    return;
  }

  loadCourses();
  showForm.value = true;
}

function openEdit(certificate) {
  editingId.value = certificate.id;
  copyFromId.value = null;
  form.name = certificate.name ?? '';
  form.title = certificate.title ?? '';
  form.course_id = certificate.course_id ?? '';
  form.grade = certificate.grade ?? '';
  form.version = certificate.version ?? '';
  form.time = certificate.time ?? 0;
  form.body = certificate.body ?? '';
  form.signatory_name = certificate.signatory_name ?? '';
  form.signatory_title = certificate.signatory_title ?? '';
  form.backgroundFile = null;
  form.signatureFile = null;
  revokeBlob(form.backgroundPreview);
  revokeBlob(form.signaturePreview);
  form.backgroundPreview = certificate.background_url ?? '';
  form.signaturePreview = certificate.signature_url ?? '';
  courseOptions.value = certificate.course
    ? [{ value: certificate.course_id, label: certificate.course.name }]
    : [];
  showForm.value = true;
}

function onBackgroundPicked(file) {
  revokeBlob(form.backgroundPreview);
  form.backgroundFile = file;
  form.backgroundPreview = file ? URL.createObjectURL(file) : '';
}

function onSignaturePicked(file) {
  revokeBlob(form.signaturePreview);
  form.signatureFile = file;
  form.signaturePreview = file ? URL.createObjectURL(file) : '';
}

async function save() {
  if (!form.course_id) {
    toast.error('Selecione o curso vinculado.');
    return;
  }

  const payload = {
    name: form.name,
    title: form.title,
    course_id: isScoped.value ? scopedCourseId.value : Number(form.course_id),
    grade: form.grade === '' || form.grade === null ? null : Number(form.grade),
    version: form.version || null,
    time: Number(form.time) || 0,
    body: form.body || null,
    signatory_name: form.signatory_name || null,
    signatory_title: form.signatory_title || null,
  };

  if (!editingId.value && copyFromId.value) {
    payload.copy_from = copyFromId.value;
  }

  const files = {
    background: form.backgroundFile,
    signature: form.signatureFile,
  };

  try {
    if (editingId.value) {
      await store.updateTemplate(editingId.value, payload, files);
    } else {
      await store.createTemplate(payload, files);
    }

    showForm.value = false;
    editingId.value = null;
    resetForm();
    toast.success('Certificado salvo.');
    await load();

    if (isScoped.value) {
      await loadScopedContext();
    }
  } catch (e) {
    toast.error(e?.message || 'Não foi possível salvar o certificado.');
  }
}

async function remove(certificate) {
  if (!window.confirm(`Excluir o certificado "${certificate.title || certificate.name}"?`)) {
    return;
  }

  busyId.value = certificate.id;

  try {
    await store.deleteTemplate(certificate.id);
    toast.success('Certificado excluído.');
    await load();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível excluir o certificado.');
  } finally {
    busyId.value = null;
  }
}

function openRevoke(item) {
  revokeTarget.value = item;
  revokeReason.value = '';
}

function closeRevoke() {
  revokeTarget.value = null;
  revokeReason.value = '';
}

async function confirmRevoke() {
  revoking.value = true;

  try {
    await store.revoke(revokeTarget.value.id, revokeReason.value || null);
    toast.success('Certificado revogado.');
    closeRevoke();
    await loadIssued();
  } catch (e) {
    toast.error(e?.message || 'Não foi possível revogar o certificado.');
  } finally {
    revoking.value = false;
  }
}

onMounted(async () => {
  if (isScoped.value) {
    courseFilter.value = scopedCourseId.value;
    await loadScopedContext();
  }

  load();
  loadFilterCourses();
});

watch(scopedCourseId, async (id) => {
  showForm.value = false;
  editingId.value = null;
  resetForm();

  if (id) {
    courseFilter.value = id;
    await loadScopedContext();
  } else {
    courseFilter.value = '';
    scopedCourse.value = null;
    currentTemplate.value = null;
    loadFilterCourses();
  }

  if (tab.value === 'issued') {
    loadIssued();
  } else {
    load();
  }
});
</script>

<style scoped>
.catalog-toolbar{flex-wrap:wrap;gap:14px;}
.admin-search{max-width:320px;}
.certificate-course-filter{min-width:240px;}
.certificate-head-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.certificate-scope-chip{
  display:inline-flex;align-items:center;height:38px;padding:0 12px;border-radius:10px;
  background:var(--accent-soft);color:var(--accent);font-size:12.5px;font-weight:700;
}
.certificate-current{
  margin-left:6px;font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;
  padding:2px 7px;border-radius:999px;background:var(--accent-soft);color:var(--accent);
}

.certificate-form-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,480px);gap:28px;align-items:start;}
.certificate-form-fields{display:flex;flex-direction:column;gap:16px;}
.certificate-form-preview{position:sticky;top:18px;display:flex;flex-direction:column;gap:8px;}
.certificate-preview-label{font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);}
.certificate-placeholder-hint{font-size:12px;color:var(--text-muted);margin-top:6px;}
.certificate-placeholder-hint code{
  background:var(--accent-soft);color:var(--accent);border-radius:6px;padding:1px 5px;font-size:11.5px;
}

@media (max-width:1024px){
  .certificate-form-grid{grid-template-columns:1fr;}
  .certificate-form-preview{position:static;}
}
</style>
