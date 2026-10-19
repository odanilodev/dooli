<template>
  <main class="content public-content">
    <div class="page-header">
      <div>
        <p class="public-eyebrow">{{ storefront.company?.name }}</p>
        <h1 class="page-title">Loja</h1>
        <p class="page-subtitle">{{ products.length }} produto(s) disponíveis</p>
      </div>
    </div>

    <div class="catalog-toolbar">
      <p class="page-subtitle">Escolha seu próximo curso e comece a estudar hoje.</p>
    </div>

    <div v-if="storefront.loading && !products.length" class="empty-state">
      <strong>Carregando produtos...</strong>
    </div>

    <div v-else class="card-grid" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr));">
      <ProductCard
        v-for="(product, index) in products"
        :key="product.id"
        :product="product"
        :index="index"
        :to="{ name: 'public-produto', params: { companySlug, productSlug: product.slug } }"
      />
    </div>

    <div v-if="!storefront.loading && products.length === 0" class="empty-state">
      <strong>Nenhum produto disponível</strong>
      <span>A loja ainda não tem produtos publicados.</span>
    </div>
  </main>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import ProductCard from '../components/ProductCard.vue';
import { useStorefrontStore } from '../stores/storefront';

const route = useRoute();
const storefront = useStorefrontStore();
const companySlug = computed(() => String(route.params.companySlug || ''));
const products = computed(() => storefront.products);

onMounted(() => {
  storefront.fetchProducts(companySlug.value);
});
</script>

<style scoped>
.public-content{max-width:1200px;margin:0 auto;padding:34px clamp(18px,5vw,64px) 70px;}
.public-eyebrow{margin:0 0 4px;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--accent);}
</style>
