<template>
  <main class="content public-content">
    <router-link :to="{ name: 'public-loja', params: { companySlug } }" class="section-link">← Voltar para a loja</router-link>

    <div v-if="loading" class="empty-state">
      <strong>Carregando produto...</strong>
    </div>

    <div v-else-if="error" class="empty-state">
      <strong>Produto não encontrado</strong>
      <span>{{ error }}</span>
    </div>

    <section v-else-if="product" class="product-detail public-product">
      <div class="product-detail-media" :style="{ background: grad }">
        <img v-if="product.image_url || product.image" :src="product.image_url || product.image" :alt="product.title" />
        <svg v-else class="icon" width="52" height="52" viewBox="0 0 24 24"><path :d="icon" /></svg>
      </div>

      <div class="product-detail-info">
        <span class="public-badge">{{ product.access_label }}</span>
        <h1 class="page-title">{{ product.title }}</h1>

        <div class="product-price product-price-lg">
          <span v-if="onSale" class="price-old">{{ formatCurrency(product.price) }}</span>
          <span class="price-now">{{ formatCurrency(currentPrice) }}<small v-if="product.is_subscription">/mês</small></span>
        </div>

        <p class="product-detail-desc">{{ product.description }}</p>

        <div v-if="includedCourses.length" class="public-included">
          <h2>O que está incluído</h2>
          <ul>
            <li v-for="course in includedCourses" :key="course.id">{{ course.name }}</li>
          </ul>
        </div>

        <div class="filter-tabs" v-if="tags.length">
          <span v-for="tag in tags" :key="tag" class="filter-tab">{{ tag }}</span>
        </div>

        <button type="button" class="btn btn-primary public-buy" @click="buy">
          {{ product.is_subscription ? 'Assinar agora' : 'Comprar agora' }}
        </button>
        <p v-if="!storefront.gateways.length" class="public-warning">
          Pagamento indisponível no momento. A empresa ainda não configurou um gateway.
        </p>
      </div>
    </section>
  </main>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { paletteAt } from '../data/palette';
import { formatCurrency } from '../utils/format';
import { useAuthStore } from '../stores/auth';
import { useStorefrontStore } from '../stores/storefront';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const storefront = useStorefrontStore();

const product = ref(null);
const loading = ref(false);
const error = ref(null);

const companySlug = computed(() => String(route.params.companySlug || ''));
const productSlug = computed(() => String(route.params.productSlug || ''));

function load() {
  loading.value = true;
  error.value = null;
  product.value = null;

  storefront
    .fetchProduct(companySlug.value, productSlug.value)
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

watch([companySlug, productSlug], load, { immediate: true });

const palette = computed(() => paletteAt(Number(product.value?.id) || 0));
const icon = computed(() => palette.value.icon);
const grad = computed(() => palette.value.grad);

const onSale = computed(() => Number(product.value?.sale) > 0);
const currentPrice = computed(() => (onSale.value ? product.value.sale : product.value?.price));
const tags = computed(() => product.value?.tags ?? []);
const includedCourses = computed(() => product.value?.included_courses ?? []);

async function buy() {
  const checkout = {
    name: 'public-checkout',
    params: { companySlug: companySlug.value, productSlug: productSlug.value },
  };

  if (!auth.isAuthenticated) {
    await router.push({
      name: 'public-cadastro',
      params: { companySlug: companySlug.value },
      query: { next: router.resolve(checkout).fullPath },
    });

    return;
  }

  await router.push(checkout);
}
</script>

<style scoped>
.public-content{max-width:1200px;margin:0 auto;padding:34px clamp(18px,5vw,64px) 70px;}
.public-product{max-width:1050px;margin:20px auto 0;}
.public-badge{display:inline-flex;margin-bottom:10px;padding:5px 9px;border-radius:999px;background:var(--accent-soft);color:var(--accent);font-size:11.5px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;}
.product-price small{font-size:14px;font-weight:700;color:var(--text-muted);}
.public-included{margin:20px 0;padding:16px 18px;border:1px solid var(--border);border-radius:14px;background:var(--bg-card-alt);}
.public-included h2{margin:0 0 8px;font-size:14px;}
.public-included ul{margin:0;padding-left:18px;color:var(--text-secondary);font-size:13px;line-height:1.8;}
.public-buy{margin-top:24px;width:100%;max-width:320px;}
.public-warning{margin-top:12px;font-size:12.5px;color:#eab308;}
</style>
