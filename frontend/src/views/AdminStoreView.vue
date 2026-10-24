<template>
  <main class="content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Loja</h1>
        <p class="page-subtitle">Personalize os banners, os textos e a vitrine das lojas pública e interna.</p>
      </div>
      <a
        v-if="publicStoreUrl"
        :href="publicStoreUrl"
        target="_blank"
        rel="noopener"
        class="btn btn-outline btn-sm"
      >Abrir loja</a>
    </div>

    <p v-if="loading" class="page-subtitle">Carregando configurações...</p>

    <div v-else>
      <div class="store-admin-main">
        <section class="panel store-admin-section">
          <div class="store-admin-section-head">
            <div>
              <h2 class="panel-title">Banners do topo</h2>
              <p class="panel-desc">Eles formam o carrossel exibido no topo da loja pública e o destaque da loja interna.</p>
            </div>
          </div>

          <BannerManager
            placement="store"
            :show-filters="false"
            :show-empty="false"
            form-description="A imagem ocupa o topo da loja. Os textos abaixo compõem o carrossel."
          />
        </section>

        <form class="panel store-admin-section" @submit.prevent="save">
          <div>
            <h2 class="panel-title">Catálogo</h2>
            <p class="panel-desc">Textos exibidos acima dos produtos nas lojas pública e interna. Campos em branco ocultam o bloco.</p>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="s-catalog-eyebrow">Pré-título</label>
              <input id="s-catalog-eyebrow" class="form-control" v-model="settings.catalog.eyebrow" maxlength="80">
            </div>
            <div class="form-group">
              <label for="s-catalog-title">Título</label>
              <input id="s-catalog-title" class="form-control" v-model="settings.catalog.title" maxlength="120">
            </div>
          </div>

          <div class="form-group">
            <label for="s-catalog-description">Descrição</label>
            <input id="s-catalog-description" class="form-control" v-model="settings.catalog.description" maxlength="500">
          </div>

          <div class="store-admin-divider"></div>

          <div>
            <h2 class="panel-title">Benefícios</h2>
            <p class="panel-desc">Até três destaques exibidos abaixo do hero da loja pública.</p>
          </div>

          <div v-for="(benefit, index) in settings.benefits" :key="index" class="benefit-editor">
            <span class="benefit-editor-index">0{{ index + 1 }}</span>
            <div class="form-row">
              <div class="form-group">
                <label :for="`s-benefit-title-${index}`">Título</label>
                <input :id="`s-benefit-title-${index}`" class="form-control" v-model="benefit.title" maxlength="80">
              </div>
              <div class="form-group">
                <label :for="`s-benefit-description-${index}`">Descrição</label>
                <input :id="`s-benefit-description-${index}`" class="form-control" v-model="benefit.description" maxlength="160">
              </div>
            </div>
          </div>

          <div class="store-admin-divider"></div>

          <div>
            <h2 class="panel-title">Rodapé</h2>
            <p class="panel-desc">Mensagem exibida no rodapé da loja pública.</p>
          </div>

          <div class="form-group">
            <label for="s-footer-note">Mensagem</label>
            <input id="s-footer-note" class="form-control" v-model="settings.footer.note" maxlength="255">
          </div>

          <div>
            <button class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Salvando...' : 'Salvar textos da loja' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </main>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';
import BannerManager from '../components/admin/BannerManager.vue';
import { useStorefrontStore } from '../stores/storefront';

const toast = useToast();
const storefront = useStorefrontStore();

const loading = ref(true);
const saving = ref(false);
const publicStoreUrl = ref('');

const settings = reactive(defaultSettings());

function defaultSettings() {
  return {
    catalog: {
      eyebrow: 'Catálogo',
      title: 'Todos os cursos',
      description: 'Encontre o conteúdo ideal para o seu próximo passo.',
    },
    benefits: [
      { title: 'Conteúdo atualizado', description: 'Aulas e materiais sempre relevantes.' },
      { title: 'Compra segura', description: 'Pagamento processado com proteção.' },
      { title: 'Acesso em todos os dispositivos', description: 'Estude onde e quando quiser.' },
    ],
    footer: {
      note: 'Pagamento processado com segurança.',
    },
  };
}

function applySettings(data = {}) {
  settings.catalog = { ...defaultSettings().catalog, ...(data.catalog ?? {}) };
  settings.footer = { ...defaultSettings().footer, ...(data.footer ?? {}) };

  const benefits = Array.isArray(data.benefits) ? data.benefits : [];
  settings.benefits = Array.from({ length: 3 }, (_, index) => ({
    title: benefits[index]?.title ?? '',
    description: benefits[index]?.description ?? '',
  }));
}

async function load() {
  loading.value = true;

  try {
    const [settingsResponse, linkResponse] = await Promise.all([
      api.get('/admin/storefront'),
      api.get('/admin/company/public-link'),
    ]);

    applySettings(settingsResponse.data?.data ?? {});
    publicStoreUrl.value = linkResponse.data?.data?.public_store_url ?? '';
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar as configurações da loja.');
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;

  try {
    const { data } = await api.put('/admin/storefront', {
      catalog: settings.catalog,
      benefits: settings.benefits.filter((benefit) => benefit.title || benefit.description),
      footer: settings.footer,
    });

    applySettings(data.data ?? {});
    storefront.reset();
    toast.success('Loja atualizada.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível salvar a loja.');
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.store-admin-main{display:flex;flex-direction:column;gap:20px;width:100%;min-width:0;}
.store-admin-section{display:flex;flex-direction:column;gap:16px;}
.store-admin-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;}
.store-admin-divider{height:1px;background:var(--border);margin:2px 0;}
.benefit-editor{
  display:grid;grid-template-columns:34px minmax(0,1fr);gap:12px;align-items:start;
  padding:12px;border:1px solid var(--border);border-radius:12px;background:var(--bg-card-alt);
}
.benefit-editor-index{
  display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;
  background:var(--accent-soft);color:var(--accent);font-size:11px;font-weight:800;
}
@media(max-width:640px){
  .benefit-editor{grid-template-columns:1fr;}
  .benefit-editor-index{display:none;}
}
</style>
