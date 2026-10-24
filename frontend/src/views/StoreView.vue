<template>
  <main class="content">
    <StoreHero v-if="heroBanners.length" :banners="heroBanners" compact />

    <StoreCatalog
      :products="products"
      :copy="catalog"
      :product-route="productRoute"
      :external-query="query"
      :loading="store.loading"
      :error="store.error"
      variant="internal"
    />
  </main>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import StoreCatalog from '../components/store/StoreCatalog.vue';
import StoreHero from '../components/store/StoreHero.vue';
import { useSearch } from '../composables/useSearch';
import { useShopStore } from '../stores/store';

const { query } = useSearch();
const store = useShopStore();

const settings = computed(() => store.storefront?.settings ?? null);
const catalog = computed(() => settings.value?.catalog ?? {});
const products = computed(() => store.products);
const heroBanners = computed(() => (store.storefront?.banners ?? []).filter((banner) => banner.image_url));

function productRoute(product) {
  return { name: 'loja-produto', params: { slug: product.slug } };
}

onMounted(() => {
  store.fetchProducts();
  store.fetchStorefront();
});
</script>
