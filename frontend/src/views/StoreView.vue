<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Loja</h1>
        <p class="page-subtitle">{{ products.length }} produtos disponíveis</p>
      </div>
    </div>

    <div class="catalog-toolbar">
      <p class="page-subtitle">Conteúdos extras para acelerar seus estudos.</p>
    </div>

    <div class="card-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr));">
      <ProductCard
        v-for="(product, index) in filteredProducts"
        :key="product.id"
        :product="product"
        :index="index"
        :to="{ name: 'loja-produto', params: { slug: product.slug } }"
      />
    </div>

    <div class="empty-state" v-if="!store.loading && filteredProducts.length === 0">
      <strong>Nenhum produto encontrado</strong>
      <span>{{ query ? 'Tente ajustar a busca.' : 'A loja ainda não tem produtos publicados.' }}</span>
    </div>

  </main>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import ProductCard from '../components/ProductCard.vue';
import { useSearch } from '../composables/useSearch';
import { useShopStore } from '../stores/store';

const { query } = useSearch();
const store = useShopStore();

onMounted(() => {
  store.fetchProducts();
});

function normalize(str) {
  return (str || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

const products = computed(() => store.products);
const filteredProducts = computed(() => {
  const q = normalize(query.value.trim());

  if (!q) {
    return products.value;
  }

  return products.value.filter(
    (product) => normalize(product.title).includes(q) || normalize(product.description).includes(q),
  );
});
</script>
