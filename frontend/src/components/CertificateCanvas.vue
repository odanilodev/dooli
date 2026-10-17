<template>
  <div class="certificate-canvas" :class="{ 'is-filled': hasBackground }" :style="backgroundStyle">
    <div class="certificate-content">
      <p class="certificate-eyebrow">Certificado</p>
      <h2 class="certificate-title">{{ certificate?.title || 'Certificado de Conclusão' }}</h2>
      <p class="certificate-body">{{ renderedBody }}</p>

      <div v-if="certificate?.signature_url || certificate?.signatory_name || certificate?.signatory_title" class="certificate-signature">
        <img v-if="certificate?.signature_url" :src="certificate.signature_url" alt="Assinatura" class="certificate-signature-img">
        <span class="certificate-signature-line"></span>
        <span v-if="certificate?.signatory_name" class="certificate-signatory-name">{{ certificate.signatory_name }}</span>
        <span v-if="certificate?.signatory_title" class="certificate-signatory-title">{{ certificate.signatory_title }}</span>
      </div>

      <span v-if="values?.codigo" class="certificate-code">Código: {{ values.codigo }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const DEFAULT_BODY = 'Certificamos que {aluno} concluiu com êxito o curso {curso}, '
  + 'com carga horária de {carga_horaria} horas, em {data}.';

const props = defineProps({
  certificate: { type: Object, default: () => ({}) },
  values: { type: Object, default: () => ({}) },
});

const hasBackground = computed(() => Boolean(props.certificate?.background_url));

const backgroundStyle = computed(() => (
  hasBackground.value ? { backgroundImage: `url('${props.certificate.background_url}')` } : {}
));

const renderedBody = computed(() => {
  const template = props.certificate?.body?.trim() || DEFAULT_BODY;

  const map = {
    '{aluno}': props.values?.aluno || 'Nome do Aluno',
    '{curso}': props.values?.curso || 'Nome do Curso',
    '{data}': props.values?.data || '00/00/0000',
    '{carga_horaria}': props.values?.carga_horaria !== undefined && props.values?.carga_horaria !== null
      ? String(props.values.carga_horaria)
      : '00',
    '{codigo}': props.values?.codigo || 'CÓDIGO',
  };

  return template.replace(/\{(aluno|curso|data|carga_horaria|codigo)\}/g, (token) => map[token] ?? token);
});
</script>

<style scoped>
.certificate-canvas{
  position:relative;width:100%;aspect-ratio:297 / 210;border-radius:14px;overflow:hidden;
  background:var(--bg-card-alt);background-size:cover;background-position:center;background-repeat:no-repeat;
  border:1px solid var(--border);color:var(--text-primary);
}
.certificate-canvas.is-filled{color:#0a1e2a;}
.certificate-content{
  position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:10px;padding:8%;text-align:center;
}
.certificate-eyebrow{
  margin:0;font-size:11px;font-weight:800;letter-spacing:.28em;text-transform:uppercase;color:var(--accent);
}
.certificate-canvas.is-filled .certificate-eyebrow{color:inherit;opacity:.75;}
.certificate-title{
  margin:0;font-size:clamp(18px,3.2vw,30px);font-weight:800;line-height:1.15;
}
.certificate-body{
  margin:0;max-width:80%;font-size:clamp(11px,1.4vw,15px);line-height:1.6;white-space:pre-line;
}
.certificate-signature{
  display:flex;flex-direction:column;align-items:center;gap:2px;margin-top:6px;min-width:180px;
}
.certificate-signature-img{max-height:46px;max-width:180px;object-fit:contain;}
.certificate-signature-line{width:180px;border-top:1px solid currentColor;opacity:.5;}
.certificate-signatory-name{font-size:12.5px;font-weight:700;}
.certificate-signatory-title{font-size:11px;opacity:.8;}
.certificate-code{
  position:absolute;right:16px;bottom:12px;font-size:10px;letter-spacing:.06em;opacity:.65;
}
</style>
