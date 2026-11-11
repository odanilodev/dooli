<template>
  <main class="content public-content">
    <nav class="public-breadcrumb" aria-label="Você está aqui">
      <router-link :to="publicRoute('public-loja')">Loja</router-link>
      <span>/</span>
      <span class="current">{{ product?.title || 'Produto' }}</span>
    </nav>

    <div v-if="loading" class="product-top" aria-label="Carregando produto">
      <div class="product-media product-skeleton-media"></div>
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
      <router-link :to="publicRoute('public-loja')" class="btn btn-outline btn-sm">Voltar para a loja</router-link>
    </div>

    <template v-else-if="product">
      <section class="product-top">
        <div class="product-media" :style="{ background: grad }">
          <img
            v-if="product.image_url || product.image"
            :src="product.image_url || product.image"
            :alt="product.title"
          />
          <svg v-else class="icon" width="58" height="58" viewBox="0 0 24 24"><path :d="icon" /></svg>
          <span class="product-media-shade" aria-hidden="true"></span>
          <span v-if="onSale" class="product-media-sale">Oferta especial</span>
        </div>

        <div class="product-info">
          <div class="product-heading">
            <p class="public-eyebrow">{{ storefront.company?.name }}</p>
            <h1>{{ product.title }}</h1>
            <p v-if="product.description" class="product-description">{{ product.description }}</p>
          </div>

          <div class="product-meta">
            <span>
              <svg class="icon" width="15" height="15" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l2.5 2.5L16 9"/></svg>
              {{ product.access_label }}
            </span>
            <span>
              <svg class="icon" width="15" height="15" viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg>
              Pagamento seguro
            </span>
            <span v-if="product.is_subscription">
              <svg class="icon" width="15" height="15" viewBox="0 0 24 24"><path d="M20 7h-6a4 4 0 0 0 0 8h2a4 4 0 0 1 0 8H9"/><path d="M16 3l4 4-4 4M8 21l-4-4 4-4"/></svg>
              Cancele quando quiser
            </span>
          </div>

          <div class="product-buy">
            <div class="product-price-row">
              <span class="product-buy-label">Investimento</span>
              <span class="product-price product-price-lg">
                <span v-if="onSale" class="price-old">{{ formatCurrency(product.price) }}</span>
                <span class="price-now">{{ formatCurrency(currentPrice) }}<small v-if="product.is_subscription">/mês</small></span>
              </span>
            </div>

            <button type="button" class="btn btn-primary btn-block product-buy-btn" :disabled="!storefront.gateways.length" @click="buy">
              {{ product.is_subscription ? 'Assinar agora' : 'Comprar agora' }}
              <svg class="icon" width="16" height="16" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>

            <p v-if="!storefront.gateways.length" class="public-warning">
              Pagamento indisponível no momento. A empresa ainda não configurou um gateway.
            </p>

            <p class="product-payment-note">Pague com PIX ou cartão em ambiente protegido.</p>
          </div>
        </div>
      </section>

      <section class="product-detail">
        <div class="product-tabs" role="tablist" aria-label="Detalhes do produto">
          <button
            type="button"
            class="product-tab"
            :class="{ active: activeTab === 'about' }"
            role="tab"
            :aria-selected="activeTab === 'about'"
            @click="activeTab = 'about'"
          >
            Sobre
          </button>
          <button
            v-if="includedCourses.length"
            type="button"
            class="product-tab"
            :class="{ active: activeTab === 'content' }"
            role="tab"
            :aria-selected="activeTab === 'content'"
            @click="activeTab = 'content'"
          >
            Conteúdo
          </button>
          <button
            type="button"
            class="product-tab"
            :class="{ active: activeTab === 'how' }"
            role="tab"
            :aria-selected="activeTab === 'how'"
            @click="activeTab = 'how'"
          >
            Como funciona
          </button>
        </div>

        <div class="product-detail-grid">
          <div class="product-tab-panel">
            <template v-if="activeTab === 'about'">
              <h2>Sobre o curso</h2>
              <p class="product-about">
                {{ product.description || 'Conteúdo digital com acesso liberado após a confirmação do pagamento.' }}
              </p>

              <template v-if="includedCourses.length">
                <h3 class="product-subtitle">
                  {{ includedCourses.length === 1 ? 'Curso incluído' : 'Cursos incluídos' }}
                </h3>
                <ul class="product-course-grid">
                  <li v-for="course in includedCourses" :key="course.id" class="product-course-tile">
                    <span class="product-course-cover" :style="{ background: grad }">
                      <img v-if="course.image_url || course.image" :src="course.image_url || course.image" :alt="course.name" loading="lazy" />
                      <svg v-else class="icon" width="26" height="26" viewBox="0 0 24 24"><path :d="icon" /></svg>
                    </span>
                    <strong>{{ course.name }}</strong>
                    <span v-if="course.description" class="product-course-desc">{{ course.description }}</span>
                  </li>
                </ul>
              </template>
            </template>

            <template v-else-if="activeTab === 'content'">
              <h2>Conteúdo do produto</h2>
              <ul class="product-included-list">
                <li v-for="course in includedCourses" :key="course.id">
                  <span class="product-included-check">
                    <svg class="icon" width="13" height="13" viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/></svg>
                  </span>
                  {{ course.name }}
                </li>
              </ul>
            </template>

            <template v-else>
              <h2>Como funciona</h2>
              <div class="product-how-grid">
                <div class="product-how-step">
                  <span>01</span>
                  <div>
                    <strong>Escolha</strong>
                    <p>Selecione este produto e confira os detalhes do acesso.</p>
                  </div>
                </div>
                <div class="product-how-step">
                  <span>02</span>
                  <div>
                    <strong>Pague</strong>
                    <p>Finalize com PIX ou cartão em um ambiente protegido.</p>
                  </div>
                </div>
                <div class="product-how-step">
                  <span>03</span>
                  <div>
                    <strong>Acesse</strong>
                    <p>O conteúdo é liberado após a confirmação do pagamento.</p>
                  </div>
                </div>
              </div>
            </template>
          </div>

          <aside class="product-include-card">
            <header class="include-head">
              <div>
                <p class="public-eyebrow">Incluso</p>
                <h2>Este curso inclui</h2>
              </div>
              <span v-if="includedCourses.length" class="include-count">
                {{ includedCourses.length }} {{ includedCourses.length === 1 ? 'curso' : 'cursos' }}
              </span>
            </header>

            <ul class="include-benefits">
              <li>
                <span class="include-tile" aria-hidden="true">
                  <svg class="icon" width="15" height="15" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l2.5 2.5L16 9"/></svg>
                </span>
                <span>{{ product.access_label }}</span>
              </li>
              <li>
                <span class="include-tile" aria-hidden="true">
                  <svg class="icon" width="15" height="15" viewBox="0 0 24 24"><path d="M4 6h16v12H4z"/><path d="M2 6h20"/><path d="M12 18v2M8 22h8"/></svg>
                </span>
                <span>Acesso online</span>
              </li>
            </ul>

            <div class="include-buy">
              <span class="product-price">
                <span v-if="onSale" class="price-old">{{ formatCurrency(product.price) }}</span>
                <span class="price-now">{{ formatCurrency(currentPrice) }}<small v-if="product.is_subscription">/mês</small></span>
              </span>
              <button type="button" class="btn btn-primary btn-block" :disabled="!storefront.gateways.length" @click="buy">
                {{ product.is_subscription ? 'Assinar agora' : 'Comprar agora' }}
                <svg class="icon" width="16" height="16" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
              </button>
            </div>

            <div class="trust-note include-trust">
              <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg>
              <div>
                <strong>Compra 100% segura</strong>
                <span>Pagamento processado em ambiente protegido.</span>
              </div>
            </div>
          </aside>
        </div>
      </section>
    </template>
  </main>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { paletteAt } from '../data/palette';
import { formatCurrency } from '../utils/format';
import { useTenant } from '../composables/useTenant';
import { useAuthStore } from '../stores/auth';
import { useStorefrontStore } from '../stores/storefront';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const storefront = useStorefrontStore();
const { companySlug, publicRoute } = useTenant();

const product = ref(null);
const loading = ref(false);
const error = ref(null);
const activeTab = ref('about');

const productSlug = computed(() => String(route.params.productSlug || ''));

function load() {
  loading.value = true;
  error.value = null;
  product.value = null;
  activeTab.value = 'about';

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
const includedCourses = computed(() => product.value?.included_courses ?? []);
async function buy() {
  const checkout = publicRoute('public-checkout', { productSlug: productSlug.value });

  if (!auth.isAuthenticated) {
    await router.push(publicRoute('public-cadastro', {}, {
      next: router.resolve(checkout).fullPath,
    }));

    return;
  }

  await router.push(checkout);
}
</script>

<style scoped>
.product-top{
  display:grid;grid-template-columns:minmax(0,1.35fr) minmax(340px,.65fr);
  gap:clamp(32px,4vw,54px);align-items:start;
}
.product-media{
  position:relative;display:flex;align-items:center;justify-content:center;overflow:hidden;
  min-width:0;aspect-ratio:16/9;border:1px solid var(--border);border-radius:12px;background:#101b2a;
}
.product-media img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
.product-media-shade{
  position:absolute;inset:0;z-index:1;
  background:linear-gradient(180deg,rgba(3,9,16,0) 58%,rgba(3,9,16,.42) 100%);
}
.product-media > .icon{position:relative;z-index:2;color:#fff;opacity:.65;}
.product-media-sale{
  position:absolute;z-index:3;top:12px;left:12px;padding:5px 9px;border-radius:5px;
  background:var(--accent-grad);color:var(--accent-ink);
  font-size:9.5px;font-weight:900;letter-spacing:.04em;text-transform:uppercase;
}
.product-info{display:flex;flex-direction:column;gap:20px;min-width:0;padding-top:4px;}
.product-heading{display:flex;flex-direction:column;gap:10px;}
.product-heading h1{
  font-size:clamp(28px,2.7vw,38px);font-weight:800;line-height:1.12;letter-spacing:-.035em;
}
.product-description{
  display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden;
  color:var(--text-secondary);font-size:13.5px;line-height:1.65;white-space:pre-line;
}
.product-meta{display:flex;flex-wrap:wrap;gap:8px 16px;}
.product-meta span{display:flex;align-items:center;gap:6px;color:var(--text-muted);font-size:10.5px;font-weight:600;}
.product-meta svg{color:var(--accent);flex-shrink:0;}
.product-buy{display:flex;flex-direction:column;gap:13px;padding-top:18px;border-top:1px solid var(--border);}
.product-price-row{display:flex;flex-direction:column;gap:3px;}
.product-buy-label{color:var(--text-muted);font-size:10.5px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;}
.product-buy .product-price{margin:0;}
.product-buy .price-now{font-size:clamp(30px,3vw,38px);line-height:1.1;letter-spacing:-.035em;}
.product-buy .price-now small{font-size:11px;font-weight:700;color:var(--text-muted);}
.product-buy .price-old{font-size:12px;}
.product-buy-btn{width:100%;padding:13px 18px;border-radius:8px;font-size:13px;}
.product-payment-note{color:var(--text-muted);font-size:10.5px;line-height:1.45;text-align:center;}
/* align-items/grid-template-columns: neutralizam a regra global .product-detail (página do aluno), que encolhia a grade. */
.product-detail{display:flex;flex-direction:column;align-items:stretch;grid-template-columns:none;gap:26px;padding-top:clamp(32px,4vw,48px);border-top:1px solid var(--border);scroll-margin-top:96px;}
.product-tabs{display:flex;gap:26px;overflow-x:auto;border-bottom:1px solid var(--border);scrollbar-width:none;}
.product-tabs::-webkit-scrollbar{display:none;}
.product-tab{
  flex-shrink:0;padding:0 0 11px;border:0;border-bottom:2px solid transparent;background:none;
  color:var(--text-muted);font:inherit;font-size:12.5px;font-weight:800;cursor:pointer;
  transition:color .15s ease,border-color .15s ease;
}
.product-tab:hover{color:var(--text-primary);}
.product-tab.active{border-bottom-color:var(--accent);color:var(--text-primary);}
.product-detail-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(340px,.65fr);gap:clamp(32px,4vw,54px);align-items:start;}
.product-tab-panel{min-width:0;}
.product-tab-panel h2{margin:0 0 13px;font-size:21px;font-weight:800;letter-spacing:-.02em;}
.product-about{color:var(--text-secondary);font-size:13.5px;line-height:1.75;white-space:pre-line;}
.product-included-list{display:flex;flex-direction:column;margin:0;padding:0;}
.product-included-list li{
  display:flex;align-items:flex-start;gap:10px;padding:11px 0;
  border-bottom:1px solid var(--border);color:var(--text-secondary);font-size:13px;line-height:1.5;
}
.product-included-list li:first-child{padding-top:0;}
.product-included-check{
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
  width:21px;height:21px;border-radius:50%;background:var(--accent-soft);color:var(--accent);
}
.product-how-grid{display:flex;flex-direction:column;gap:0;}
.product-how-step{display:flex;gap:16px;padding:14px 0;border-bottom:1px solid var(--border);}
.product-how-step:first-child{padding-top:0;}
.product-how-step:last-child{border-bottom:0;}
.product-how-step > span{color:var(--accent);font-size:22px;font-weight:800;line-height:1;letter-spacing:-.04em;flex-shrink:0;}
.product-how-step > div{display:flex;flex-direction:column;gap:3px;}
.product-how-step strong{font-size:12.5px;}
.product-how-step p{margin:0;color:var(--text-muted);font-size:11.5px;line-height:1.5;}
.product-include-card{
  position:sticky;top:108px;display:flex;flex-direction:column;gap:18px;
  padding:22px;border:1px solid var(--border);border-radius:14px;
  background:linear-gradient(180deg,color-mix(in srgb,var(--accent) 6%,var(--bg-card)),var(--bg-card));
  box-shadow:var(--shadow-sm);
}
.include-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;}
.include-head h2{margin:4px 0 0;font-size:16px;font-weight:800;letter-spacing:-.02em;}
.include-count{
  flex-shrink:0;padding:4px 9px;border-radius:999px;background:var(--accent-soft);color:var(--accent);
  font-size:10px;font-weight:800;letter-spacing:.02em;white-space:nowrap;
}
.include-tile{
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
  width:30px;height:30px;border-radius:9px;background:var(--accent-soft);color:var(--accent);
}
.include-course-copy{display:flex;flex-direction:column;gap:2px;min-width:0;}
.include-course-copy strong{color:var(--text-primary);font-size:12.5px;font-weight:700;line-height:1.4;}
.include-course-copy span{
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
  color:var(--text-muted);font-size:10.5px;line-height:1.45;
}
.include-benefits{
  display:flex;flex-direction:column;gap:8px;margin:0;padding:16px 0 0;border-top:1px solid var(--border);
}
.include-benefits li{
  display:flex;align-items:center;gap:10px;color:var(--text-secondary);
  font-size:11.5px;font-weight:600;line-height:1.4;
}
.include-benefits .include-tile{width:26px;height:26px;border-radius:8px;}
.include-buy{display:flex;flex-direction:column;gap:12px;padding-top:16px;border-top:1px solid var(--border);}
.include-buy .product-price{margin:0;}
.include-buy .price-now{font-size:28px;line-height:1.1;letter-spacing:-.03em;}
.include-buy .price-now small{font-size:11px;font-weight:700;color:var(--text-muted);}
.include-buy .btn{padding:12px 18px;border-radius:8px;font-size:13px;}
.product-subtitle{margin:28px 0 14px;font-size:15px;font-weight:800;letter-spacing:-.01em;}
.product-course-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:16px;margin:0;padding:0;list-style:none;}
.product-course-tile{display:flex;flex-direction:column;gap:6px;min-width:0;}
.product-course-cover{
  position:relative;display:flex;align-items:center;justify-content:center;overflow:hidden;
  aspect-ratio:16/9;border:1px solid var(--border);border-radius:10px;margin-bottom:4px;
}
.product-course-cover img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
.product-course-cover .icon{color:#fff;opacity:.65;}
.product-course-tile strong{font-size:13px;font-weight:700;line-height:1.35;}
.product-course-desc{
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
  color:var(--text-muted);font-size:11.5px;line-height:1.5;
}
.include-trust{margin-top:0;}
.product-skeleton-media{background:var(--bg-card-alt);animation:product-pulse 1.4s ease-in-out infinite;}
.product-skeleton-info{display:flex;flex-direction:column;gap:13px;padding-top:10px;}
.product-skeleton-line{
  display:block;height:16px;border-radius:8px;background:var(--bg-card-alt);
  animation:product-pulse 1.4s ease-in-out infinite;
}
.product-skeleton-title{height:44px;width:82%;}
.product-skeleton-line-sm{width:54%;}
@keyframes product-pulse{0%,100%{opacity:.55;}50%{opacity:1;}}
@media(max-width:1000px){
  .product-top{grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr);gap:32px;}
  .product-detail-grid{grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr);gap:32px;}
}
@media(max-width:820px){
  .product-top{grid-template-columns:1fr;}
  .product-info{padding-top:0;}
  .product-description{-webkit-line-clamp:6;}
  .product-detail-grid{grid-template-columns:1fr;}
  .product-include-card{position:static;}
}
@media(max-width:600px){
  .product-media{border-radius:10px;}
  .product-heading h1{font-size:clamp(28px,8vw,36px);}
  .product-buy .price-now{font-size:30px;}
  .product-tabs{gap:20px;}
  .product-include-card{padding:17px;}
}
@media(prefers-reduced-motion:reduce){
  .product-skeleton-media,.product-skeleton-line{animation:none;}
}
</style>
