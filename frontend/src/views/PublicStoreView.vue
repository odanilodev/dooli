<template>
  <main class="content public-content">
    <section class="store-hero">
      <div class="store-hero-copy">
        <p class="public-eyebrow">{{ storefront.company?.name || 'Loja online' }}</p>
        <h1>Conhecimento que abre caminhos.</h1>
        <p class="store-hero-desc">
          Cursos e materiais selecionados para você aprender com profundidade e aplicar no seu ritmo.
        </p>

        <div class="store-hero-meta">
          <span>{{ products.length }} produto(s) disponíveis</span>
          <span>Compra segura</span>
          <span>Acesso online</span>
        </div>

        <a href="#catalogo" class="btn btn-primary">Explorar catálogo</a>
      </div>

      <router-link
        v-if="featured"
        :to="{ name: 'public-produto', params: { companySlug, productSlug: featured.slug } }"
        class="store-hero-feature"
      >
        <div class="store-hero-feature-media" :style="{ background: heroGrad }">
          <img v-if="featured.image_url || featured.image" :src="featured.image_url || featured.image" :alt="featured.title">
          <span class="store-hero-feature-label">Destaque da loja</span>
        </div>
        <div class="store-hero-feature-body">
          <span class="store-hero-feature-access">{{ featured.access_label }}</span>
          <strong>{{ featured.title }}</strong>
          <span class="store-hero-feature-price">{{ formatCurrency(featuredPrice) }}<small v-if="featured.is_subscription">/mês</small></span>
        </div>
      </router-link>

      <div v-else class="store-hero-empty" aria-hidden="true">
        <span class="store-hero-orbit"></span>
        <span class="store-hero-orbit store-hero-orbit-sm"></span>
        <strong>Novos conteúdos em breve</strong>
      </div>
    </section>

    <section id="catalogo" class="store-catalog">
      <div class="store-catalog-head">
        <div>
          <p class="public-eyebrow">Catálogo</p>
          <h2>Escolha seu próximo passo</h2>
          <p class="store-catalog-sub">
            {{ resultLabel }}
          </p>
        </div>

        <label class="store-search" for="store-search">
          <svg class="icon" width="17" height="17" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          <span class="sr-only">Buscar produtos</span>
          <input id="store-search" v-model="query" type="search" placeholder="Buscar por curso ou conteúdo">
        </label>
      </div>

      <div v-if="tags.length" class="store-filters" aria-label="Filtrar por categoria">
        <button
          type="button"
          class="public-filter"
          :class="{ active: !activeTag }"
          :aria-pressed="!activeTag"
          @click="activeTag = ''"
        >
          Todos
        </button>
        <button
          v-for="tag in tags"
          :key="tag"
          type="button"
          class="public-filter"
          :class="{ active: activeTag === tag }"
          :aria-pressed="activeTag === tag"
          @click="activeTag = tag"
        >
          {{ tag }}
        </button>
      </div>

      <div v-if="storefront.loading && !products.length" class="store-grid" aria-label="Carregando produtos">
        <div v-for="n in 6" :key="n" class="store-skeleton">
          <span class="store-skeleton-media"></span>
          <span class="store-skeleton-line"></span>
          <span class="store-skeleton-line store-skeleton-line-sm"></span>
        </div>
      </div>

      <div v-else-if="filteredProducts.length" class="store-grid">
        <PublicProductCard
          v-for="(product, index) in filteredProducts"
          :key="product.id"
          :product="product"
          :index="index"
          :to="{ name: 'public-produto', params: { companySlug, productSlug: product.slug } }"
        />
      </div>

      <div v-else-if="storefront.error" class="public-empty">
        <svg class="icon" width="38" height="38" viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>
        <strong>Não foi possível carregar a loja</strong>
        <span>{{ storefront.error }}</span>
        <button type="button" class="btn btn-outline btn-sm" @click="retry">Tentar novamente</button>
      </div>

      <div v-else-if="!products.length" class="public-empty">
        <svg class="icon" width="38" height="38" viewBox="0 0 24 24"><path d="M4 7h16v12H4z"/><path d="M8 7V5h8v2"/></svg>
        <strong>Nenhum produto publicado</strong>
        <span>A loja ainda não tem produtos disponíveis. Volte em breve.</span>
      </div>

      <div v-else class="public-empty">
        <svg class="icon" width="38" height="38" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <strong>Nenhum resultado encontrado</strong>
        <span>Tente outro termo ou remova os filtros selecionados.</span>
        <button type="button" class="btn btn-outline btn-sm" @click="clearFilters">Limpar busca e filtros</button>
      </div>
    </section>
  </main>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import PublicProductCard from '../components/PublicProductCard.vue';
import { paletteAt } from '../data/palette';
import { useStorefrontStore } from '../stores/storefront';
import { formatCurrency } from '../utils/format';

const route = useRoute();
const storefront = useStorefrontStore();

const query = ref('');
const activeTag = ref('');

const companySlug = computed(() => String(route.params.companySlug || ''));
const products = computed(() => storefront.products);
const featured = computed(() => products.value[0] ?? null);
const featuredPrice = computed(() => (Number(featured.value?.sale) > 0 ? featured.value.sale : featured.value?.price));
const heroGrad = computed(() => paletteAt(Number(featured.value?.id) || 0).grad);

const tags = computed(() => {
  const unique = new Map();

  products.value.forEach((product) => {
    (product.tags ?? []).forEach((tag) => {
      const label = String(tag || '').trim();
      const key = normalize(label);

      if (key && !unique.has(key)) {
        unique.set(key, label);
      }
    });
  });

  return [...unique.values()].sort((a, b) => a.localeCompare(b, 'pt-BR'));
});

const filteredProducts = computed(() => {
  const term = normalize(query.value.trim());

  return products.value.filter((product) => {
    const matchesTag = !activeTag.value
      || (product.tags ?? []).some((tag) => normalize(tag) === normalize(activeTag.value));
    const matchesQuery = !term
      || normalize(product.title).includes(term)
      || normalize(product.description).includes(term);

    return matchesTag && matchesQuery;
  });
});

const resultLabel = computed(() => {
  if (!products.value.length) {
    return 'Todos os produtos da loja em um só lugar.';
  }

  if (filteredProducts.value.length === products.value.length) {
    return `${products.value.length} produto(s) disponíveis na loja.`;
  }

  return `${filteredProducts.value.length} de ${products.value.length} produto(s) encontrados.`;
});

watch(companySlug, (slug) => {
  query.value = '';
  activeTag.value = '';
  storefront.fetchProducts(slug);
}, { immediate: true });

function normalize(value) {
  return String(value || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
}

function clearFilters() {
  query.value = '';
  activeTag.value = '';
}

function retry() {
  storefront.fetchProducts(companySlug.value);
}
</script>

<style scoped>
.store-hero{
  display:grid;grid-template-columns:minmax(0,1.25fr) minmax(280px,.75fr);gap:28px;align-items:stretch;
  padding:clamp(26px,4vw,46px);border:1px solid var(--border);border-radius:24px;overflow:hidden;
  background:
    radial-gradient(circle at 88% 8%,color-mix(in srgb,var(--accent) 20%,transparent),transparent 18rem),
    linear-gradient(135deg,var(--bg-card) 0%,color-mix(in srgb,var(--bg-card) 72%,var(--accent) 28%) 100%);
  box-shadow:var(--shadow-sm);
}
.store-hero-copy{display:flex;flex-direction:column;align-items:flex-start;justify-content:center;gap:16px;}
.store-hero-copy h1{max-width:620px;font-size:clamp(30px,4.2vw,48px);font-weight:800;line-height:1.08;letter-spacing:-.025em;}
.store-hero-desc{max-width:540px;color:var(--text-secondary);font-size:14.5px;line-height:1.65;}
.store-hero-meta{display:flex;flex-wrap:wrap;gap:7px;}
.store-hero-meta span{
  padding:6px 10px;border:1px solid var(--border);border-radius:999px;
  background:color-mix(in srgb,var(--bg-card) 72%,transparent);color:var(--text-secondary);
  font-size:11.5px;font-weight:700;
}
.store-hero-copy .btn{margin-top:4px;}
.store-hero-feature{
  position:relative;display:flex;flex-direction:column;align-self:center;overflow:hidden;
  width:100%;max-width:360px;justify-self:end;
  border:1px solid rgba(255,255,255,.12);border-radius:18px;background:rgba(5,14,23,.78);
  box-shadow:0 24px 60px rgba(0,0,0,.28);color:#fff;
  transition:transform .2s ease,box-shadow .2s ease;
}
.store-hero-feature:hover{transform:translateY(-4px);box-shadow:0 30px 70px rgba(0,0,0,.35);}
.store-hero-feature-media{position:relative;aspect-ratio:16/9;display:flex;align-items:flex-end;padding:14px;overflow:hidden;}
.store-hero-feature-media img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
.store-hero-feature-label{
  position:relative;z-index:1;padding:5px 9px;border-radius:999px;
  background:rgba(5,14,23,.7);border:1px solid rgba(255,255,255,.18);
  font-size:10.5px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;
}
.store-hero-feature-body{display:flex;flex-direction:column;gap:6px;padding:16px;}
.store-hero-feature-access{color:color-mix(in srgb,#fff 65%,var(--accent));font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;}
.store-hero-feature-body strong{font-size:16px;line-height:1.35;}
.store-hero-feature-price{font-size:18px;font-weight:800;color:color-mix(in srgb,var(--accent) 82%,#fff);}
.store-hero-feature-price small{font-size:10.5px;font-weight:700;color:rgba(255,255,255,.58);}
.store-hero-empty{
  position:relative;display:flex;align-items:center;justify-content:center;align-self:center;justify-self:end;
  width:100%;max-width:340px;min-height:220px;padding:24px;overflow:hidden;text-align:center;
  border:1px solid var(--border);border-radius:18px;background:var(--bg-card-alt);color:var(--text-muted);
}
.store-hero-orbit{position:absolute;width:230px;height:230px;border:1px solid color-mix(in srgb,var(--accent) 28%,transparent);border-radius:50%;}
.store-hero-orbit-sm{width:150px;height:150px;border-style:dashed;}
.store-hero-empty strong{position:relative;font-size:14px;}
.store-catalog{display:flex;flex-direction:column;gap:20px;scroll-margin-top:110px;}
.store-catalog-head{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap;}
.store-catalog-head h2{margin-top:4px;font-size:clamp(22px,2.5vw,30px);font-weight:800;letter-spacing:-.02em;}
.store-catalog-sub{margin-top:5px;color:var(--text-muted);font-size:12.5px;}
.store-search{
  display:flex;align-items:center;gap:9px;min-width:min(100%,310px);
  padding:11px 14px;border:1px solid var(--border);border-radius:12px;
  background:var(--bg-card);color:var(--text-muted);
  transition:border-color .15s ease,box-shadow .15s ease;
}
.store-search:focus-within{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft);}
.store-search input{width:100%;border:0;outline:0;background:transparent;color:var(--text-primary);font:inherit;font-size:13px;}
.store-search input::placeholder{color:var(--text-muted);}
.store-filters{display:flex;gap:8px;flex-wrap:wrap;}
.public-filter{
  padding:8px 14px;border:1px solid var(--border);border-radius:999px;background:var(--bg-card);
  color:var(--text-secondary);font:inherit;font-size:12px;font-weight:700;cursor:pointer;
  transition:border-color .15s ease,background .15s ease,color .15s ease;
}
.public-filter:hover{border-color:var(--accent);color:var(--text-primary);}
.public-filter.active{border-color:transparent;background:var(--accent-grad);color:var(--accent-ink);}
.store-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px;}
.store-skeleton{
  display:flex;flex-direction:column;gap:12px;padding:12px;overflow:hidden;
  border:1px solid var(--border);border-radius:18px;background:var(--bg-card);
}
.store-skeleton-media,.store-skeleton-line{
  display:block;border-radius:12px;background:var(--bg-card-alt);
  animation:store-pulse 1.4s ease-in-out infinite;
}
.store-skeleton-media{aspect-ratio:16/9;}
.store-skeleton-line{height:15px;}
.store-skeleton-line-sm{width:58%;}
@keyframes store-pulse{0%,100%{opacity:.55;}50%{opacity:1;}}
@media(max-width:900px){
  .store-hero{grid-template-columns:1fr;}
  .store-hero-feature,.store-hero-empty{justify-self:start;max-width:420px;}
}
@media(max-width:640px){
  .store-hero{padding:24px 20px;border-radius:20px;}
  .store-hero-feature{max-width:none;}
  .store-hero-meta span{font-size:10.5px;}
  .store-grid{grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px;}
  .store-catalog-head{align-items:flex-start;flex-direction:column;}
  .store-search{width:100%;}
}
@media(prefers-reduced-motion:reduce){
  .store-hero-feature,.store-card,.store-card-media img{transition:none;}
  .store-skeleton-media,.store-skeleton-line{animation:none;}
}
</style>
