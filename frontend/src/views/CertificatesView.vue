<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Meus certificados</h1>
        <p class="page-subtitle">Visualize a prévia e baixe em PDF quando quiser.</p>
      </div>

      <button
        v-if="!loading && certificates.length"
        type="button"
        class="btn btn-outline btn-sm certificates-filter-trigger"
        aria-label="Abrir filtros"
        aria-controls="certificates-filters-modal"
        :aria-expanded="filtersOpen"
        @click="openFilters"
      >
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M4 5h16l-6.5 7.5V19l-3 1.5v-8z"/>
        </svg>
        Filtros
        <span v-if="activeFilterCount" class="filter-badge">{{ activeFilterCount }}</span>
      </button>
    </div>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <template v-else>
      <div v-if="certificates.length" class="certificates-toolbar">
        <div class="filter-tabs">
          <button
            v-for="tab in statusFilters"
            :key="tab.value"
            type="button"
            class="filter-tab"
            :class="{ active: status === tab.value }"
            @click="status = tab.value"
          >
            {{ tab.label }} <span class="filter-count">({{ statusCounts[tab.value] }})</span>
          </button>
        </div>

        <div class="certificates-course-filter">
          <label for="certificates-course">Curso</label>
          <select id="certificates-course" class="form-control" v-model.number="courseId">
            <option value="">Todos os cursos</option>
            <option v-for="course in courseOptions" :key="course.id" :value="course.id">{{ course.name }}</option>
          </select>
        </div>
      </div>

      <div v-if="certificates.length === 0" class="empty-state">
        <strong>Nenhum certificado disponível</strong>
        <span>Conclua todas as aulas de um curso para liberar o seu certificado.</span>
      </div>

      <div v-else-if="filteredCertificates.length === 0" class="empty-state">
        <strong>Nenhum certificado encontrado</strong>
        <span>{{ emptyFilterMessage }}</span>
        <button class="btn btn-outline btn-sm" @click="showAll">Ver todos os certificados</button>
      </div>

      <div v-else class="certificates-grid">
        <article v-for="item in filteredCertificates" :key="item.id" class="certificate-card" :class="{ 'is-revoked': statusOf(item) !== 'valid' }">
          <div class="certificate-card-body">
            <span class="certificate-card-badge">{{ item.certificate?.course?.name || 'Curso' }}</span>
            <h2 class="certificate-card-title">{{ item.certificate?.title || 'Certificado de Conclusão' }}</h2>
            <span class="certificate-card-meta">Emitido em {{ formatDate(item.issued_at) }}</span>
            <span class="certificate-card-code">Código: {{ item.code }}</span>
          </div>
          <div class="certificate-card-foot">
            <span class="status-pill" :class="statusOf(item) !== 'valid' ? 'inactive' : 'active'">
              {{ statusLabel(item) }}
            </span>
            <button class="btn btn-primary btn-sm" @click="open(item)">Visualizar</button>
          </div>
        </article>
      </div>
    </template>

    <div v-if="active" class="admin-modal-backdrop" @click.self="active = null">
      <div class="panel admin-modal certificate-preview-modal">
        <div class="admin-panel-head no-print">
          <h2 class="panel-title">{{ active.certificate?.title || 'Certificado' }}</h2>
          <button class="admin-modal-close" @click="active = null">×</button>
        </div>

        <div class="certificate-preview-stack">
          <CertificateCanvas
            :certificate="active.certificate || {}"
            :values="valuesFor(active)"
            :modules="active.modules || []"
            :layout="active.certificate?.layout"
            :hidden-fields="hiddenFields(active)"
          />

          <div v-if="hasModulesPage(active)" class="certificate-page-block">
            <span class="certificate-page-label no-print">{{ modulesPageLabel(active) }}</span>
            <CertificateCanvas
              :certificate="active.certificate || {}"
              :values="valuesFor(active)"
              :modules="active.modules || []"
              :layout="active.certificate?.modules_layout"
              :background-url="modulesBackground(active)"
              mode="modules"
            />
          </div>
        </div>

        <div class="admin-modal-actions no-print">
          <button class="btn btn-outline" @click="active = null">Fechar</button>
          <button class="btn btn-primary" @click="downloadPdf">Baixar PDF</button>
        </div>
      </div>
    </div>

    <div v-if="filtersOpen" id="certificates-filters-modal" class="admin-modal-backdrop no-print" @click.self="closeFilters">
      <div class="panel admin-modal certificate-filters-modal">
        <div class="admin-panel-head">
          <h2 class="panel-title">Filtrar certificados</h2>
          <button type="button" class="admin-modal-close" aria-label="Fechar filtros" @click="closeFilters">×</button>
        </div>

        <div class="certificate-filter-section">
          <span class="certificate-filter-label">Status</span>
          <div class="filter-tabs certificate-filter-options">
            <button
              v-for="tab in statusFilters"
              :key="tab.value"
              type="button"
              class="filter-tab"
              :class="{ active: draftStatus === tab.value }"
              @click="draftStatus = tab.value"
            >
              {{ tab.label }} <span class="filter-count">({{ statusCounts[tab.value] }})</span>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label for="certificates-mobile-course">Curso</label>
          <select id="certificates-mobile-course" class="form-control" v-model.number="draftCourseId">
            <option value="">Todos os cursos</option>
            <option v-for="course in courseOptions" :key="course.id" :value="course.id">{{ course.name }}</option>
          </select>
        </div>

        <div class="admin-modal-actions">
          <button type="button" class="btn btn-outline" @click="clearFilters">Limpar</button>
          <button type="button" class="btn btn-primary" @click="applyFilters">Aplicar filtros</button>
        </div>
      </div>
    </div>

  </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { api } from '../api/http';
import { formatDate } from '../utils/format';
import { useAuthStore } from '../stores/auth';
import { useToast } from '../composables/useToast';
import CertificateCanvas from '../components/CertificateCanvas.vue';

const auth = useAuthStore();
const toast = useToast();

const statusFilters = [
  { value: 'valid', label: 'Válidos' },
  { value: 'replaced', label: 'Substituídos' },
  { value: 'revoked', label: 'Revogados' },
  { value: 'all', label: 'Todos' },
];

const certificates = ref([]);
const loading = ref(true);
const active = ref(null);
const status = ref('valid');
const courseId = ref('');
const filtersOpen = ref(false);
const draftStatus = ref('valid');
const draftCourseId = ref('');

function statusOf(item) {
  return item.status || (item.invalidated_at ? 'revoked' : 'valid');
}

function statusLabel(item) {
  const status = statusOf(item);

  if (status === 'replaced') return 'Substituído';
  if (status === 'revoked') return 'Revogado';

  return 'Válido';
}

const statusCounts = computed(() => {
  const counts = {
    valid: 0,
    replaced: 0,
    revoked: 0,
    all: certificates.value.length,
  };

  certificates.value.forEach((item) => {
    const itemStatus = statusOf(item);

    if (counts[itemStatus] !== undefined) {
      counts[itemStatus]++;
    }
  });

  return counts;
});

const activeFilterCount = computed(() => {
  let count = 0;

  if (status.value !== 'valid') {
    count++;
  }

  if (courseId.value !== '') {
    count++;
  }

  return count;
});

const courseOptions = computed(() => {
  const courses = new Map();

  certificates.value.forEach((item) => {
    const course = item.certificate?.course;
    const id = item.certificate?.course_id ?? course?.id;

    if (id && !courses.has(id)) {
      courses.set(id, { id, name: course?.name || 'Curso' });
    }
  });

  return [...courses.values()].sort((a, b) => a.name.localeCompare(b.name));
});

const filteredCertificates = computed(() => {
  const selectedCourse = courseId.value === '' ? null : Number(courseId.value);

  return certificates.value.filter((item) => {
    if (status.value !== 'all' && statusOf(item) !== status.value) {
      return false;
    }

    if (selectedCourse === null) {
      return true;
    }

    const itemCourseId = item.certificate?.course_id ?? item.certificate?.course?.id;

    return Number(itemCourseId) === selectedCourse;
  });
});

const emptyFilterMessage = computed(() => {
  if (status.value === 'valid' && courseId.value === '') {
    return 'Seus certificados substituídos ou revogados continuam disponíveis nas abas acima.';
  }

  if (courseId.value !== '') {
    return 'Nenhum certificado com esse status para o curso selecionado.';
  }

  return 'Ajuste o status para ver outros certificados.';
});

function showAll() {
  status.value = 'all';
  courseId.value = '';
}

function openFilters() {
  draftStatus.value = status.value;
  draftCourseId.value = courseId.value;
  filtersOpen.value = true;
}

function closeFilters() {
  filtersOpen.value = false;
}

function applyFilters() {
  status.value = draftStatus.value;
  courseId.value = draftCourseId.value;
  closeFilters();
}

function clearFilters() {
  draftStatus.value = 'valid';
  draftCourseId.value = '';
  status.value = 'valid';
  courseId.value = '';
  closeFilters();
}

function valuesFor(item) {
  return {
    aluno: auth.user?.name || '',
    curso: item.certificate?.course?.name || '',
    data: formatDate(item.issued_at),
    carga_horaria: item.certificate?.time ?? 0,
    codigo: item.code,
  };
}

function hiddenFields(item) {
  return item.certificate?.modules_placement === 'front' ? [] : ['modules'];
}

function hasModulesPage(item) {
  return ['extra', 'back'].includes(item.certificate?.modules_placement)
    && Array.isArray(item.modules)
    && item.modules.length > 0;
}

function modulesPageLabel(item) {
  return item.certificate?.modules_placement === 'back' ? 'Verso' : 'Folha extra';
}

function modulesBackground(item) {
  return item.certificate?.modules_background_url || null;
}

function open(item) {
  active.value = item;
}

function downloadPdf() {
  window.print();
}

async function load() {
  loading.value = true;

  try {
    const { data } = await api.get('/me/certificates');
    certificates.value = data.data ?? [];
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar seus certificados.');
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.certificates-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:14px;margin-bottom:18px;}
.filter-count{opacity:.7;font-variant-numeric:tabular-nums;}
.certificates-course-filter{display:flex;align-items:center;gap:8px;min-width:240px;}
.certificates-course-filter label{font-size:12px;font-weight:700;color:var(--text-muted);white-space:nowrap;}
.certificates-course-filter select{min-width:200px;}
.certificates-filter-trigger{display:none;align-items:center;gap:7px;position:relative;}
.certificates-filter-trigger svg{flex-shrink:0;}
.filter-badge{
  min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:var(--accent-grad);color:var(--accent-ink);
  font-size:10px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;
}
.certificate-filters-modal{max-width:420px;}
.certificate-filter-section{display:flex;flex-direction:column;gap:10px;margin:18px 0;}
.certificate-filter-label{font-size:12px;font-weight:700;color:var(--text-muted);}
.certificate-filter-options{flex-wrap:wrap;}
.certificate-filters-modal .admin-modal-actions{margin-top:20px;}
.certificates-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px;}
.certificate-card{
  display:flex;flex-direction:column;justify-content:space-between;gap:18px;
  background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:22px;box-shadow:var(--shadow-sm);
}
.certificate-card.is-revoked{opacity:.7;}
.certificate-card-body{display:flex;flex-direction:column;gap:6px;}
.certificate-card-badge{
  align-self:flex-start;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;
  color:var(--accent);background:var(--accent-soft);border-radius:999px;padding:3px 10px;
}
.certificate-card-title{margin:2px 0 0;font-size:17px;font-weight:800;color:var(--text-primary);}
.certificate-card-meta{font-size:12.5px;color:var(--text-muted);}
.certificate-card-code{font-size:11.5px;color:var(--text-muted);font-variant-numeric:tabular-nums;}
.certificate-card-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;}

.certificate-preview-modal{max-width:1000px;}
.certificate-preview-modal .admin-modal-actions{margin-top:16px;}
.certificate-preview-stack{display:flex;flex-direction:column;gap:18px;}
.certificate-page-block{display:flex;flex-direction:column;gap:8px;}
.certificate-page-label{
  font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);
}

@media (max-width:720px){
  .certificates-toolbar{display:none;}
  .certificates-filter-trigger{display:inline-flex;}
}

@page{size:A4 landscape;margin:0;}

@media print {
  :global(.sidebar),
  :global(.sidebar-backdrop),
  :global(.topbar),
  .page-header,
  .certificates-grid,
  .no-print {
    display:none !important;
  }
  :global(.main-col){margin-left:0 !important;}
  :global(.content){padding:0 !important;display:block !important;gap:0 !important;}
  .admin-modal-backdrop{position:static !important;background:none !important;padding:0 !important;display:block !important;}
  .admin-modal{
    max-width:none !important;max-height:none !important;overflow:visible !important;
    box-shadow:none !important;border:none !important;width:100% !important;padding:0 !important;background:none !important;
  }
  .certificate-preview-stack{display:block;gap:0;}
  .certificate-page-block{display:block;break-before:page;page-break-before:always;}
  :deep(.certificate-canvas){
    border:none !important;border-radius:0 !important;
    break-inside:avoid;page-break-inside:avoid;
    -webkit-print-color-adjust:exact;print-color-adjust:exact;
  }
}
</style>
