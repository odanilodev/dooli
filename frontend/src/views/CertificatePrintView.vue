<template>
  <main class="content certificate-print-page">

    <div class="page-header no-print">
      <div>
        <h1 class="page-title">Certificado</h1>
        <p class="page-subtitle">Use o botão imprimir para salvar em PDF ou imprimir.</p>
      </div>
      <div class="certificate-print-actions">
        <router-link class="btn btn-outline" :to="{ name: 'certs' }">Voltar</router-link>
        <button class="btn btn-primary" :disabled="!certificate" @click="print">Imprimir</button>
      </div>
    </div>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <div v-else-if="!certificate" class="empty-state">
      <strong>Certificado não encontrado</strong>
      <span>Ele pode ter sido revogado ou não pertence à sua conta.</span>
    </div>

    <div v-else class="certificate-print-stack">
      <div class="certificate-print-sheet">
        <span class="certificate-print-sheet-label no-print">Certificado</span>
        <CertificateCanvas
          :certificate="certificate.certificate"
          :values="values"
          :modules="certificate.modules || []"
          :layout="certificate.certificate?.layout"
          :hidden-fields="hiddenFields"
        />
      </div>

      <div v-if="hasModulesPage" class="certificate-print-sheet certificate-print-sheet--modules">
        <span class="certificate-print-sheet-label no-print">{{ modulesPageLabel }}</span>
        <CertificateCanvas
          :certificate="certificate.certificate"
          :values="values"
          :modules="certificate.modules || []"
          :layout="certificate.certificate?.modules_layout"
          :background-url="modulesBackground"
          mode="modules"
        />
      </div>
    </div>

  </main>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api/http';
import { formatDate } from '../utils/format';
import { useAuthStore } from '../stores/auth';
import CertificateCanvas from '../components/CertificateCanvas.vue';

const route = useRoute();
const auth = useAuthStore();

const certificate = ref(null);
const loading = ref(true);

const values = ref({});

const hiddenFields = computed(() => (
  certificate.value?.certificate?.modules_placement === 'front' ? [] : ['modules']
));

const hasModulesPage = computed(() => (
  ['extra', 'back'].includes(certificate.value?.certificate?.modules_placement)
  && Array.isArray(certificate.value?.modules)
  && certificate.value.modules.length > 0
));

const modulesPageLabel = computed(() => (
  certificate.value?.certificate?.modules_placement === 'back' ? 'Verso' : 'Folha extra'
));

const modulesBackground = computed(() => (
  certificate.value?.certificate?.modules_background_url || null
));

function print() {
  window.print();
}

async function load() {
  loading.value = true;

  try {
    const { data } = await api.get('/me/certificates');
    const item = (data.data ?? []).find((entry) => String(entry.id) === String(route.params.id)) ?? null;

    certificate.value = item;

    if (item) {
      values.value = {
        aluno: auth.user?.name || '',
        curso: item.certificate?.course?.name || '',
        data: formatDate(item.issued_at),
        carga_horaria: item.certificate?.time ?? 0,
        codigo: item.code,
        share_url: item.share_url || '',
      };
    }
  } catch (e) {
    certificate.value = null;
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  await load();

  if (certificate.value && route.query.print) {
    await nextTick();
    window.print();
  }
});
</script>

<style scoped>
.certificate-print-actions{display:flex;gap:10px;}
.certificate-print-stack{display:flex;flex-direction:column;gap:22px;}
.certificate-print-sheet{width:100%;max-width:1000px;margin:0 auto;}
.certificate-print-sheet-label{
  display:block;margin-bottom:8px;font-size:11px;font-weight:800;letter-spacing:.08em;
  text-transform:uppercase;color:var(--text-muted);
}

@page{size:A4 landscape;margin:0;}

@media print {
  :global(.sidebar),
  :global(.sidebar-backdrop),
  :global(.topbar),
  .no-print {
    display:none !important;
  }
  :global(.main-col){margin-left:0 !important;}
  :global(.content){padding:0 !important;}
  .certificate-print-stack{display:block;}
  .certificate-print-sheet{max-width:none;margin:0;}
  .certificate-print-sheet + .certificate-print-sheet{
    break-before:page;page-break-before:always;
  }
  :deep(.certificate-canvas){
    border:none !important;border-radius:0 !important;
    break-inside:avoid;page-break-inside:avoid;
    -webkit-print-color-adjust:exact;print-color-adjust:exact;
  }
}
</style>
