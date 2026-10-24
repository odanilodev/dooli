<template>
  <div class="store-page">
    <StoreHero v-if="heroBanners.length" :banners="heroBanners" :benefits="visibleBenefits" />

    <main class="content public-content store-content">
      <StoreCatalog
        :products="products"
        :copy="settings.catalog"
        :product-route="productRoute"
        :loading="storefront.loading"
        :error="storefront.error"
      />
    </main>
  </div>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import StoreCatalog from '../components/store/StoreCatalog.vue';
import StoreHero from '../components/store/StoreHero.vue';
import { useStorefrontStore } from '../stores/storefront';

const route = useRoute();
const storefront = useStorefrontStore();

const companySlug = computed(() => String(route.params.companySlug || ''));
const products = computed(() => storefront.products);
const storefrontData = computed(() => storefront.company?.storefront ?? null);
const settings = computed(() => withDefaults(storefrontData.value?.settings));

const heroBanners = computed(() => (storefrontData.value?.banners ?? []).filter((banner) => banner.image_url));

const visibleBenefits = computed(() => (settings.value.benefits ?? []).filter((benefit) => benefit.title || benefit.description));

function productRoute(product) {
  return {
    name: 'public-produto',
    params: { companySlug: companySlug.value, productSlug: product.slug },
  };
}

watch(companySlug, (slug) => {
  if (!slug) {
    return;
  }

  storefront.fetchProducts(slug);
}, { immediate: true });

function withDefaults(data) {
  return {
    catalog: data?.catalog ?? {},
    benefits: data?.benefits ?? [
      { title: 'Conteúdo atualizado', description: 'Aulas e materiais sempre relevantes.' },
      { title: 'Compra segura', description: 'Pagamento processado com proteção.' },
      { title: 'Acesso em todos os dispositivos', description: 'Estude onde e quando quiser.' },
    ],
    internal: data?.internal ?? null,
    footer: data?.footer ?? { note: 'Pagamento processado com segurança.' },
  };
}
</script>

<style scoped>
.store-page{display:flex;flex-direction:column;}
.store-content{padding-top:clamp(42px,4.6vw,68px);}
</style>
