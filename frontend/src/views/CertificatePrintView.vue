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

    <div v-else class="certificate-print-sheet">
      <CertificateCanvas :certificate="certificate.certificate" :values="values" :modules="certificate.modules || []" :layout="certificate.certificate?.layout" />
    </div>

  </main>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue';
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
.certificate-print-sheet{width:100%;max-width:1000px;margin:0 auto;}

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
  .certificate-print-sheet{max-width:none;margin:0;}
}
</style>
