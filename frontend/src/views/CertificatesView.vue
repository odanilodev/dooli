<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Meus certificados</h1>
        <p class="page-subtitle">Visualize a prévia e baixe em PDF quando quiser.</p>
      </div>
    </div>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <div v-else-if="certificates.length === 0" class="empty-state">
      <strong>Nenhum certificado disponível</strong>
      <span>Conclua todas as aulas de um curso para liberar o seu certificado.</span>
    </div>

    <div v-else class="certificates-grid">
      <article v-for="item in certificates" :key="item.id" class="certificate-card" :class="{ 'is-revoked': statusOf(item) !== 'valid' }">
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

  </main>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api/http';
import { formatDate } from '../utils/format';
import { useAuthStore } from '../stores/auth';
import { useToast } from '../composables/useToast';
import CertificateCanvas from '../components/CertificateCanvas.vue';

const auth = useAuthStore();
const toast = useToast();

const certificates = ref([]);
const loading = ref(true);
const active = ref(null);

function statusOf(item) {
  return item.status || (item.invalidated_at ? 'revoked' : 'valid');
}

function statusLabel(item) {
  const status = statusOf(item);

  if (status === 'replaced') return 'Substituído';
  if (status === 'revoked') return 'Revogado';

  return 'Válido';
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
