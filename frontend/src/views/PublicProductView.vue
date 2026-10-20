<template>
  <main class="content public-content">
    <nav class="public-breadcrumb" aria-label="Você está aqui">
      <router-link :to="{ name: 'public-loja', params: { companySlug } }">Loja</router-link>
      <span>/</span>
      <span class="current">{{ product?.title || 'Produto' }}</span>
    </nav>

    <div v-if="loading" class="product-view" aria-label="Carregando produto">
      <div class="product-media-card product-skeleton-media"></div>
      <div class="product-skeleton-info">
        <span class="product-skeleton-line"></span>
        <span class="product-skeleton-line product-skeleton-title"></span>
        <span class="product-skeleton-line"></span>
        <span class="product-skeleton-line product-skeleton-line-sm"></span>
      </div>
    </div>

    <div v-else-if="error" class="public-empty">
      <svg class="icon" width="38" height="38" viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>
      <strong>Produto não encontrado</strong>
      <span>{{ error }}</span>
      <router-link :to="{ name: 'public-loja', params: { companySlug } }" class="btn btn-outline btn-sm">Voltar para a loja</router-link>
    </div>

    <section v-else-if="product" class="product-view">
      <div class="product-media-card" :style="{ background: grad }">
        <img
          v-if="product.image_url || product.image"
          :src="product.image_url || product.image"
          :alt="product.title"
        />
        <svg v-else class="icon" width="58" height="58" viewBox="0 0 24 24"><path :d="icon" /></svg>
        <span class="product-media-glow"></span>
        <span v-if="onSale" class="product-media-sale">Oferta especial</span>
        <span class="product-media-access">{{ product.access_label }}</span>
      </div>

      <div class="product-info">
        <div class="product-heading">
          <p class="public-eyebrow">{{ storefront.company?.name }}</p>
          <div v-if="tags.length" class="product-tags">
            <span v-for="tag in tags" :key="tag">{{ tag }}</span>
          </div>
          <h1>{{ product.title }}</h1>
          <p v-if="product.description" class="product-description">{{ product.description }}</p>
        </div>

        <div class="product-buy-box">
          <div class="product-buy-price">
            <span class="product-buy-label">Investimento</span>
            <span class="product-price product-price-lg">
              <span v-if="onSale" class="price-old">{{ formatCurrency(product.price) }}</span>
              <span class="price-now">{{ formatCurrency(currentPrice) }}<small v-if="product.is_subscription">/mês</small></span>
            </span>
          </div>

          <button type="button" class="btn btn-primary btn-block" :disabled="!storefront.gateways.length" @click="buy">
            {{ product.is_subscription ? 'Assinar agora' : 'Comprar agora' }}
            <svg class="icon" width="16" height="16" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </button>

          <p v-if="!storefront.gateways.length" class="public-warning">
            Pagamento indisponível no momento. A empresa ainda não configurou um gateway.
          </p>

          <div class="product-assurance">
            <span>
              <svg class="icon" width="15" height="15" viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg>
              Pagamento seguro
            </span>
            <span>
              <svg class="icon" width="15" height="15" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l2.5 2.5L16 9"/></svg>
              {{ accessNote }}
            </span>
            <span v-if="product.is_subscription">
              <svg class="icon" width="15" height="15" viewBox="0 0 24 24"><path d="M20 7h-6a4 4 0 0 0 0 8h2a4 4 0 0 1 0 8H9"/><path d="M16 3l4 4-4 4M8 21l-4-4 4-4"/></svg>
              Cancele quando quiser
            </span>
          </div>
        </div>

        <section v-if="includedCourses.length" class="product-included">
          <div class="product-included-head">
            <p class="public-eyebrow">Conteúdo do produto</p>
            <h2>O que está incluído</h2>
          </div>
          <ul>
            <li v-for="course in includedCourses" :key="course.id">
              <span class="product-included-check">
                <svg class="icon" width="13" height="13" viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/></svg>
              </span>
              {{ course.name }}
            </li>
          </ul>
        </section>

        <section class="product-how">
          <div class="product-included-head">
            <p class="public-eyebrow">Simples e rápido</p>
            <h2>Como funciona</h2>
          </div>
          <div class="product-how-grid">
            <div class="product-how-step">
              <span>1</span>
              <strong>Escolha</strong>
              <p>Selecione este produto e confira os detalhes do acesso.</p>
            </div>
            <div class="product-how-step">
              <span>2</span>
              <strong>Pague</strong>
              <p>Finalize com PIX ou cartão em um ambiente protegido.</p>
            </div>
            <div class="product-how-step">
              <span>3</span>
              <strong>Acesse</strong>
              <p>O conteúdo é liberado após a confirmação do pagamento.</p>
            </div>
          </div>
        </section>
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
const accessNote = computed(() => {
  if (product.value?.is_subscription) {
    return 'Acesso enquanto a assinatura estiver ativa';
  }

  return product.value?.access_label || 'Acesso após confirmação';
});

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
.product-view{
  display:grid;grid-template-columns:minmax(0,1.05fr) minmax(320px,.95fr);
  gap:clamp(24px,4vw,48px);align-items:start;
}
.product-media-card{
  position:sticky;top:102px;display:flex;align-items:center;justify-content:center;overflow:hidden;
  min-height:320px;aspect-ratio:16/9;border:1px solid var(--border);border-radius:22px;
  box-shadow:var(--shadow-sm);
}
.product-media-card img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
.product-media-card > .icon{position:relative;z-index:1;color:#fff;opacity:.65;}
.product-media-glow{
  position:absolute;width:70%;aspect-ratio:1;border-radius:50%;
  background:radial-gradient(circle,rgba(255,255,255,.13),transparent 68%);
}
.product-media-sale,.product-media-access{
  position:absolute;z-index:2;padding:6px 10px;border-radius:999px;
  font-size:10.5px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;
}
.product-media-sale{top:16px;left:16px;background:var(--accent-grad);color:var(--accent-ink);}
.product-media-access{
  left:16px;bottom:16px;background:rgba(5,14,23,.72);border:1px solid rgba(255,255,255,.18);
  color:#fff;backdrop-filter:blur(6px);
}
.product-info{display:flex;flex-direction:column;gap:22px;min-width:0;}
.product-heading{display:flex;flex-direction:column;gap:12px;}
.product-heading h1{font-size:clamp(28px,3.8vw,42px);font-weight:800;line-height:1.1;letter-spacing:-.025em;}
.product-tags{display:flex;flex-wrap:wrap;gap:6px;}
.product-tags span{
  padding:4px 9px;border-radius:999px;background:var(--accent-soft);color:var(--accent);
  font-size:10.5px;font-weight:800;
}
.product-description{color:var(--text-secondary);font-size:14px;line-height:1.7;white-space:pre-line;}
.product-buy-box{
  display:flex;flex-direction:column;gap:14px;padding:20px;
  border:1px solid var(--border);border-radius:20px;background:var(--bg-card);
  box-shadow:var(--shadow-sm);
}
.product-buy-price{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.product-buy-label{color:var(--text-muted);font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;}
.product-buy-price .product-price{margin:0;}
.product-buy-price .price-now{font-size:32px;line-height:1;}
.product-buy-price .price-now small{font-size:12px;font-weight:700;color:var(--text-muted);}
.product-buy-box .btn{width:100%;padding:14px 20px;}
.product-assurance{display:flex;flex-direction:column;gap:8px;padding-top:3px;}
.product-assurance span{display:flex;align-items:center;gap:8px;color:var(--text-secondary);font-size:11.5px;line-height:1.4;}
.product-assurance svg{flex-shrink:0;color:var(--accent);}
.product-included{display:flex;flex-direction:column;gap:13px;padding-top:4px;}
.product-included-head h2{margin-top:4px;font-size:19px;font-weight:800;}
.product-included ul{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:9px;margin:0;padding:0;list-style:none;}
.product-included li{
  display:flex;align-items:flex-start;gap:9px;padding:11px 12px;
  border:1px solid var(--border);border-radius:12px;background:var(--bg-card);
  color:var(--text-secondary);font-size:12.5px;line-height:1.45;
}
.product-included-check{
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
  width:20px;height:20px;border-radius:50%;background:var(--accent-soft);color:var(--accent);
}
.product-how{display:flex;flex-direction:column;gap:12px;padding-top:8px;}
.product-how-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:9px;}
.product-how-step{
  display:flex;flex-direction:column;gap:4px;padding:12px;
  border:1px solid var(--border);border-radius:12px;background:var(--bg-card-alt);
}
.product-how-step > span{
  display:flex;align-items:center;justify-content:center;width:22px;height:22px;margin-bottom:2px;
  border-radius:50%;background:var(--accent-soft);color:var(--accent);font-size:10.5px;font-weight:900;
}
.product-how-step strong{font-size:12px;}
.product-how-step p{margin:0;color:var(--text-muted);font-size:10.5px;line-height:1.45;}
.product-skeleton-media{background:var(--bg-card-alt);border-style:solid;animation:product-pulse 1.4s ease-in-out infinite;}
.product-skeleton-info{display:flex;flex-direction:column;gap:12px;padding-top:10px;}
.product-skeleton-line{
  display:block;height:16px;border-radius:8px;background:var(--bg-card-alt);
  animation:product-pulse 1.4s ease-in-out infinite;
}
.product-skeleton-title{height:42px;width:78%;}
.product-skeleton-line-sm{width:52%;}
@keyframes product-pulse{0%,100%{opacity:.55;}50%{opacity:1;}}
@media(max-width:900px){
  .product-view{grid-template-columns:minmax(0,1fr);}
  .product-media-card{
    position:relative;top:0;width:100%;min-width:0;min-height:0;max-height:none;
  }
}
@media(max-width:560px){
  .product-media-card{aspect-ratio:16/9;border-radius:18px;}
  .product-buy-box{padding:16px;border-radius:16px;}
  .product-buy-price .price-now{font-size:28px;}
  .product-included ul{grid-template-columns:1fr;}
}
@media(prefers-reduced-motion:reduce){
  .product-skeleton-media,.product-skeleton-line{animation:none;}
}
</style>
