<template>
  <main class="public-cert-main">
    <header class="public-cert-header no-print">
      <div>
        <span class="public-cert-eyebrow">Certificado</span>
        <h1 class="public-cert-title">{{ title }}</h1>
        <p v-if="item" class="public-cert-meta">
          Emitido em {{ formatDate(item.issued_at) }} · Código {{ item.code }}
        </p>
      </div>

      <div class="public-cert-actions">
        <button type="button" class="btn btn-primary" :disabled="!item" @click="print">
          Baixar PDF
        </button>
      </div>
    </header>

    <p v-if="loading" class="public-cert-message">Carregando certificado...</p>

    <div v-else-if="!item" class="public-cert-invalid">
      <strong>Certificado indisponível</strong>
      <span>{{ errorMessage }}</span>
    </div>

    <template v-else>
      <div class="public-cert-status no-print">Certificado válido</div>

      <div class="public-cert-stack">
        <div class="public-cert-sheet">
          <CertificateCanvas
            :certificate="item.certificate || {}"
            :values="values"
            :modules="item.modules || []"
            :layout="item.certificate?.layout"
            :hidden-fields="hiddenFields"
          />
        </div>

        <div v-if="hasModulesPage" class="public-cert-sheet">
          <CertificateCanvas
            :certificate="item.certificate || {}"
            :values="values"
            :modules="item.modules || []"
            :layout="item.certificate?.modules_layout"
            :background-url="modulesBackground"
            mode="modules"
          />
        </div>
      </div>

      <p class="public-cert-hint no-print">
        O botão "Baixar PDF" abre a janela de impressão. Escolha "Salvar como PDF" para gerar o arquivo.
      </p>
    </template>
  </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { api } from '../api/http';
import { formatDate } from '../utils/format';
import CertificateCanvas from '../components/CertificateCanvas.vue';

function readCode() {
  const match = window.location.pathname.match(/\/v\/([^/?#]+)\/?$/);

  if (match) {
    return decodeURIComponent(match[1]);
  }

  return new URLSearchParams(window.location.search).get('code') || '';
}

const code = readCode();
const item = ref(null);
const loading = ref(true);
const errorMessage = ref('O link pode ter expirado ou o certificado foi revogado.');

const title = computed(() => item.value?.certificate?.title || 'Certificado de Conclusão');

const values = computed(() => ({
  aluno: item.value?.holder_name || '',
  curso: item.value?.course || '',
  data: formatDate(item.value?.issued_at),
  carga_horaria: item.value?.certificate?.time ?? 0,
  codigo: item.value?.code || '',
  share_url: item.value?.share_url || '',
}));

const hiddenFields = computed(() => (
  item.value?.certificate?.modules_placement === 'front' ? [] : ['modules']
));

const hasModulesPage = computed(() => (
  ['extra', 'back'].includes(item.value?.certificate?.modules_placement)
  && Array.isArray(item.value?.modules)
  && item.value.modules.length > 0
));

const modulesBackground = computed(() => item.value?.certificate?.modules_background_url || null);

function print() {
  window.print();
}

async function load() {
  if (!code) {
    loading.value = false;
    return;
  }

  let redirecting = false;

  try {
    const { data } = await api.get(`/public/certificates/${encodeURIComponent(code)}`);
    const payload = data.data || null;

    if (payload?.redirect_url) {
      redirecting = true;
      window.location.replace(payload.redirect_url);

      return;
    }

    item.value = payload;
  } catch (e) {
    item.value = null;

    if (e?.status && e.status !== 404) {
      errorMessage.value = 'Não foi possível carregar o certificado. Tente novamente em instantes.';
    }
  } finally {
    if (!redirecting) {
      loading.value = false;
    }
  }
}

onMounted(load);
</script>

<style scoped>
.public-cert-main{
  width:100%;max-width:1100px;margin:0 auto;padding:34px 24px 54px;
  display:flex;flex-direction:column;gap:18px;
}
.public-cert-header{
  display:flex;align-items:flex-start;justify-content:space-between;gap:20px;
  padding-bottom:18px;border-bottom:1px solid var(--border);
}
.public-cert-eyebrow{
  display:block;margin-bottom:6px;color:var(--accent);font-size:11px;font-weight:800;
  letter-spacing:.12em;text-transform:uppercase;
}
.public-cert-title{margin:0;font-size:26px;font-weight:800;color:var(--text-primary);}
.public-cert-meta{margin-top:6px;font-size:12.5px;color:var(--text-muted);overflow-wrap:anywhere;}
.public-cert-actions{display:flex;gap:10px;flex-shrink:0;}
.public-cert-message{padding:40px 0;text-align:center;color:var(--text-muted);}
.public-cert-invalid{
  display:flex;flex-direction:column;align-items:center;gap:8px;padding:54px 20px;
  border:1px solid var(--border);border-radius:18px;background:var(--bg-card);text-align:center;
}
.public-cert-invalid strong{font-size:19px;color:var(--text-primary);}
.public-cert-invalid span{font-size:13px;color:var(--text-muted);}
.public-cert-status{
  align-self:flex-start;display:inline-flex;align-items:center;gap:7px;padding:5px 12px;border-radius:999px;
  background:var(--accent-soft);color:var(--accent);font-size:12px;font-weight:800;
}
.public-cert-status::before{
  content:"";width:7px;height:7px;border-radius:50%;background:var(--accent);
}
.public-cert-stack{display:flex;flex-direction:column;gap:22px;}
.public-cert-sheet{width:100%;}
.public-cert-hint{font-size:12px;color:var(--text-muted);text-align:center;}

@page{size:A4 landscape;margin:0;}

@media (max-width:720px){
  .public-cert-main{padding:22px 14px 40px;}
  .public-cert-header{flex-direction:column;}
  .public-cert-actions{width:100%;}
  .public-cert-actions .btn{flex:1;}
}

@media print {
  :global(body){background:#fff;}
  .no-print{display:none !important;}
  .public-cert-main{max-width:none;margin:0;padding:0;}
  .public-cert-stack{display:block;}
  .public-cert-sheet{max-width:none;margin:0;}
  .public-cert-sheet + .public-cert-sheet{break-before:page;page-break-before:always;}
  :deep(.certificate-canvas){
    border:none !important;border-radius:0 !important;
    break-inside:avoid;page-break-inside:avoid;
    -webkit-print-color-adjust:exact;print-color-adjust:exact;
  }
}
</style>
