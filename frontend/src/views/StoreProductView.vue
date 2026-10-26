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
        <img v-if="product.image_url || product.image" :src="product.image_url || product.image" :alt="product.title" />
        <svg v-else class="icon" width="52" height="52" viewBox="0 0 24 24"><path :d="icon" /></svg>
      </div>

      <div class="product-detail-info">
        <h1 class="page-title">{{ product.title }}</h1>

        <p class="product-detail-desc">{{ product.description }}</p>

        <div class="filter-tabs" v-if="tags.length">
          <span v-for="tag in tags" :key="tag" class="filter-tab">{{ tag }}</span>
        </div>

        <div v-if="includedCourses.length" class="store-product-includes">
          <strong>Este produto inclui</strong>
          <ul>
            <li v-for="course in includedCourses" :key="course.id">
              <svg class="icon" width="14" height="14" viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/></svg>
              {{ course.name }}
            </li>
          </ul>
        </div>

        <div class="store-product-buy">
          <div class="store-product-buy-head">
            <span>Investimento</span>
            <span class="store-product-access">{{ product.access_label }}</span>
          </div>

          <div class="product-price product-price-lg">
            <span v-if="onSale" class="price-old">{{ formatCurrency(product.price) }}</span>
            <span class="price-now">
              {{ formatCurrency(currentPrice) }}<small v-if="product.is_subscription">/mês</small>
            </span>
          </div>

          <button
            type="button"
            class="btn btn-primary btn-block store-product-buy-btn"
            :disabled="!canBuy"
            @click="buy"
          >
            {{ product.is_subscription ? 'Assinar agora' : 'Comprar agora' }}
            <svg class="icon" width="16" height="16" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </button>

          <p v-if="!companySlug" class="store-product-warning">
            Não foi possível carregar os dados de pagamento desta loja. Tente novamente mais tarde.
          </p>
          <p v-else-if="!gateway" class="store-product-warning">
            Pagamento indisponível no momento. A empresa ainda não configurou um gateway.
          </p>
          <p v-else class="store-product-note">
            Pague com PIX ou cartão em ambiente protegido.
          </p>
        </div>
      </div>
    </section>

  </main>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { paletteAt } from '../data/palette';
import { useTenant } from '../composables/useTenant';
import { formatCurrency } from '../utils/format';
import { useShopStore } from '../stores/store';

const route = useRoute();
const router = useRouter();
const store = useShopStore();
const { publicRoute } = useTenant();

const product = ref(null);
const loading = ref(false);
const error = ref(null);

async function load(slug) {
  loading.value = true;
  error.value = null;
  product.value = null;

  try {
    const [, data] = await Promise.all([
      store.fetchStorefront(),
      store.fetchProduct(slug),
    ]);

    product.value = data;
  } catch (e) {
    error.value = e?.message || 'Não foi possível carregar este produto.';
  } finally {
    loading.value = false;
  }
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
const includedCourses = computed(() => product.value?.included_courses ?? []);
const companySlug = computed(() => store.storefront?.company?.slug ?? '');
const gateway = computed(() => store.storefront?.company?.gateways?.[0] ?? null);
const canBuy = computed(() => Boolean(companySlug.value && gateway.value));

function buy() {
  if (!canBuy.value) {
    return;
  }

  router.push(publicRoute('public-checkout', { productSlug: product.value.slug }, { from: 'internal' }));
}
</script>

<style scoped>
.store-product-includes{
  display:flex;flex-direction:column;gap:9px;padding:15px 16px;
  border:1px solid var(--border);border-radius:12px;background:var(--bg-card-alt);
}
.store-product-includes > strong{font-size:12px;font-weight:800;}
.store-product-includes ul{display:flex;flex-direction:column;gap:7px;margin:0;padding:0;}
.store-product-includes li{
  display:flex;align-items:flex-start;gap:8px;
  color:var(--text-secondary);font-size:12px;line-height:1.5;
}
.store-product-includes svg{flex-shrink:0;margin-top:1px;color:var(--accent);}
.store-product-buy{
  display:flex;flex-direction:column;gap:12px;margin-top:4px;padding:18px;
  border:1px solid var(--border);border-radius:14px;background:var(--bg-card);
}
.store-product-buy-head{display:flex;align-items:center;justify-content:space-between;gap:12px;}
.store-product-buy-head > span:first-child{
  color:var(--text-muted);font-size:10.5px;font-weight:800;
  letter-spacing:.07em;text-transform:uppercase;
}
.store-product-access{color:var(--text-muted);font-size:10.5px;font-weight:600;}
.store-product-buy .product-price{margin:0;}
.store-product-buy .price-now{font-size:30px;line-height:1.1;letter-spacing:-.03em;}
.store-product-buy .price-now small{font-size:11px;font-weight:700;color:var(--text-muted);}
.store-product-buy-btn{padding:13px 18px;border-radius:8px;font-size:13px;}
.store-product-warning{
  margin:0;padding:10px 12px;border-radius:8px;
  background:var(--accent-soft);color:var(--text-secondary);font-size:11.5px;line-height:1.5;
}
.store-product-note{margin:0;color:var(--text-muted);font-size:11px;line-height:1.5;text-align:center;}
@media(max-width:720px){
  .store-product-buy{padding:16px;}
  .store-product-buy .price-now{font-size:27px;}
}
</style>
