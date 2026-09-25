<template>
  <main class="content">

    <router-link to="/loja" class="section-link">← Voltar para a loja</router-link>

    <div v-if="loading" class="empty-state">
      <strong>Carregando produto...</strong>
    </div>

    <div v-else-if="error" class="empty-state">
      <strong>Produto não encontrado</strong>
      <span>{{ error }}</span>
    </div>

    <section v-else-if="product" class="product-detail">
      <div class="product-detail-media" :style="{ background: grad }">
        <img v-if="product.image" :src="product.image" :alt="product.title" />
        <svg v-else class="icon" width="52" height="52" viewBox="0 0 24 24"><path :d="icon" /></svg>
      </div>

      <div class="product-detail-info">
        <h1 class="page-title">{{ product.title }}</h1>

        <div class="product-price product-price-lg">
          <span v-if="onSale" class="price-old">{{ formatCurrency(product.price) }}</span>
          <span class="price-now">{{ formatCurrency(currentPrice) }}</span>
        </div>

        <p class="product-detail-desc">{{ product.description }}</p>

        <div class="filter-tabs" v-if="tags.length">
          <span v-for="tag in tags" :key="tag" class="filter-tab">{{ tag }}</span>
        </div>
      </div>
    </section>

  </main>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { paletteAt } from '../data/palette';
import { formatCurrency } from '../utils/format';
import { useShopStore } from '../stores/store';

const route = useRoute();
const store = useShopStore();

const product = ref(null);
const loading = ref(false);
const error = ref(null);

function load(slug) {
  loading.value = true;
  error.value = null;
  product.value = null;

  store
    .fetchProduct(slug)
    .then((data) => {
      product.value = data;
    })
    .catch((e) => {
      error.value = e?.message || 'Não foi possível carregar este produto.';
    })
    .finally(() => {
      loading.value = false;
    });
}

watch(
  () => route.params.slug,
  (slug) => {
    if (slug) {
      load(slug);
    }
  },
  { immediate: true },
);

const palette = computed(() => paletteAt(Number(product.value?.id) || 0));
const icon = computed(() => palette.value.icon);
const grad = computed(() => palette.value.grad);

const onSale = computed(() => Number(product.value?.sale) > 0);
const currentPrice = computed(() => (onSale.value ? product.value.sale : product.value?.price));
const tags = computed(() => product.value?.tags ?? []);
</script>
