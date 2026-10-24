<template>
  <section id="catalogo" class="store-catalog" :class="{ 'is-internal': variant === 'internal' }">
    <header v-if="hasHeader" class="store-catalog-head">
      <p v-if="copy.eyebrow" class="public-eyebrow">{{ copy.eyebrow }}</p>
      <h2 v-if="copy.title">{{ copy.title }}</h2>
      <p v-if="copy.description">{{ copy.description }}</p>
    </header>

    <button class="store-filters-toggle" type="button" @click="filtersOpen = !filtersOpen">
      <svg class="icon" width="17" height="17" viewBox="0 0 24 24"><path d="M4 7h16M7 12h10M10 17h4"/></svg>
      Filtros
      <span v-if="hasActiveFilters" class="store-filters-count">{{ activeFilterCount }}</span>
      <svg class="icon store-filters-chevron" :class="{ open: filtersOpen }" width="15" height="15" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
    </button>

    <div class="store-catalog-layout">
      <aside class="store-filters-panel" :class="{ open: filtersOpen }">
        <label class="store-search" for="store-search">
          <svg class="icon" width="17" height="17" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          <span class="sr-only">Buscar produtos</span>
          <input id="store-search" v-model="query" type="search" placeholder="Buscar cursos...">
        </label>

        <div v-if="tagOptions.length" class="store-filter-group">
          <p class="store-filter-title">Categorias</p>
          <button
            type="button"
            class="store-filter-row"
            :class="{ active: !activeTag }"
            @click="activeTag = ''"
          >
            <span class="store-filter-check">
              <svg v-if="!activeTag" class="icon" width="11" height="11" viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/></svg>
            </span>
            Todas
            <span class="store-filter-count">{{ products.length }}</span>
          </button>
          <button
            v-for="tag in tagOptions"
            :key="tag.key"
            type="button"
            class="store-filter-row"
            :class="{ active: activeTag === tag.key }"
            @click="activeTag = tag.key"
          >
            <span class="store-filter-check">
              <svg v-if="activeTag === tag.key" class="icon" width="11" height="11" viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/></svg>
            </span>
            {{ tag.label }}
            <span class="store-filter-count">{{ tag.count }}</span>
          </button>
        </div>

        <div v-if="typeOptions.length" class="store-filter-group">
          <p class="store-filter-title">Tipo</p>
          <button
            v-for="type in typeOptions"
            :key="type.key"
            type="button"
            class="store-filter-row"
            :class="{ active: activeType === type.key }"
            @click="activeType = type.key"
          >
            <span class="store-filter-check">
              <svg v-if="activeType === type.key" class="icon" width="11" height="11" viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/></svg>
            </span>
            {{ type.label }}
            <span class="store-filter-count">{{ type.count }}</span>
          </button>
        </div>

        <button v-if="hasActiveFilters" class="store-clear-filters" type="button" @click="clearFilters">
          Limpar filtros
        </button>
      </aside>

      <div class="store-results">
        <div class="store-results-bar">
          <span>{{ resultLabel }}</span>
          <label class="store-sort">
            <span class="sr-only">Ordenar por</span>
            <select v-model="sortBy">
              <option value="relevance">Mais relevantes</option>
              <option value="price_asc">Menor preço</option>
              <option value="price_desc">Maior preço</option>
              <option value="recent">Mais recentes</option>
            </select>
          </label>
        </div>

        <div v-if="loading && !products.length" class="store-grid" aria-label="Carregando produtos">
          <div v-for="n in 6" :key="n" class="store-skeleton">
            <span class="store-skeleton-media"></span>
            <span class="store-skeleton-line"></span>
            <span class="store-skeleton-line store-skeleton-line-sm"></span>
          </div>
        </div>

        <div v-else-if="visibleProducts.length" class="store-grid">
          <PublicProductCard
            v-for="(product, index) in visibleProducts"
            :key="product.id"
            :product="product"
            :index="index"
            :to="productRoute(product)"
          />
        </div>

        <div v-else-if="error" class="public-empty">
          <svg class="icon" width="38" height="38" viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>
          <strong>Não foi possível carregar a loja</strong>
          <span>{{ error }}</span>
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
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import PublicProductCard from '../PublicProductCard.vue';

const props = defineProps({
  products: { type: Array, default: () => [] },
  copy: { type: Object, default: () => ({}) },
  productRoute: { type: Function, required: true },
  externalQuery: { type: String, default: null },
  loading: { type: Boolean, default: false },
  error: { type: String, default: null },
  variant: { type: String, default: 'public' },
});

const hasHeader = computed(() => Boolean(
  props.copy?.eyebrow || props.copy?.title || props.copy?.description,
));

const query = ref(props.externalQuery ?? '');
const activeTag = ref('');
const activeType = ref('');
const sortBy = ref('relevance');
const filtersOpen = ref(false);

watch(
  () => props.externalQuery,
  (value) => {
    if (value !== null && value !== undefined) {
      query.value = value;
    }
  },
);

const tagOptions = computed(() => {
  const unique = new Map();

  props.products.forEach((product) => {
    (product.tags ?? []).forEach((tag) => {
      const label = String(tag || '').trim();
      const key = normalize(label);

      if (!key) {
        return;
      }

      if (!unique.has(key)) {
        unique.set(key, { key, label, count: 0 });
      }

      unique.get(key).count += 1;
    });
  });

  return [...unique.values()].sort((a, b) => a.label.localeCompare(b.label, 'pt-BR'));
});

const typeOptions = computed(() => {
  const subscriptions = props.products.filter((product) => product.is_subscription).length;
  const oneTime = props.products.length - subscriptions;
  const options = [
    { key: '', label: 'Todos', count: props.products.length },
    { key: 'one_time', label: 'Compra única', count: oneTime },
    { key: 'subscription', label: 'Assinatura', count: subscriptions },
  ];

  return options.filter((option) => option.key === '' || option.count > 0);
});

const visibleProducts = computed(() => {
  const term = normalize(query.value.trim());
  const filtered = props.products.filter((product) => {
    const matchesTag = !activeTag.value
      || (product.tags ?? []).some((tag) => normalize(tag) === activeTag.value);
    const matchesType = !activeType.value
      || (activeType.value === 'subscription' ? product.is_subscription : !product.is_subscription);
    const matchesQuery = !term
      || normalize(product.title).includes(term)
      || normalize(product.description).includes(term);

    return matchesTag && matchesType && matchesQuery;
  });

  return [...filtered].sort((a, b) => {
    if (sortBy.value === 'price_asc') {
      return currentPrice(a) - currentPrice(b);
    }

    if (sortBy.value === 'price_desc') {
      return currentPrice(b) - currentPrice(a);
    }

    if (sortBy.value === 'recent') {
      return new Date(b.created_at || 0) - new Date(a.created_at || 0);
    }

    return props.products.indexOf(a) - props.products.indexOf(b);
  });
});

const hasActiveFilters = computed(() => Boolean(query.value.trim() || activeTag.value || activeType.value));
const activeFilterCount = computed(() => [query.value.trim(), activeTag.value, activeType.value].filter(Boolean).length);

const resultLabel = computed(() => {
  const total = props.products.length;
  const visible = visibleProducts.value.length;

  if (!total) {
    return 'Nenhum produto disponível.';
  }

  if (visible === total) {
    return total === 1 ? '1 curso encontrado' : `${total} cursos encontrados`;
  }

  return `${visible} de ${total} ${total === 1 ? 'curso' : 'cursos'}`;
});

function normalize(value) {
  return String(value || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
}

function currentPrice(product) {
  return Number(product.sale) > 0 ? Number(product.sale) : Number(product.price);
}

function clearFilters() {
  query.value = '';
  activeTag.value = '';
  activeType.value = '';
}
</script>

<style scoped>
.store-catalog{display:flex;flex-direction:column;gap:26px;scroll-margin-top:96px;}
.store-catalog-head{display:flex;flex-direction:column;gap:5px;}
.store-catalog-head h2{
  margin-top:4px;font-size:clamp(32px,3.5vw,48px);font-weight:800;
  line-height:1.05;letter-spacing:-.035em;
}
.store-catalog-head > p:last-child{color:var(--text-secondary);font-size:14px;line-height:1.55;}
.store-catalog-layout{display:grid;grid-template-columns:238px minmax(0,1fr);gap:clamp(24px,3vw,38px);align-items:start;}
.store-filters-panel{
  position:sticky;top:98px;display:flex;flex-direction:column;gap:24px;
  padding-right:22px;border-right:1px solid var(--border);
}
.store-search{
  display:flex;align-items:center;gap:9px;width:100%;padding:10px 12px;
  border:1px solid var(--border);border-radius:8px;background:var(--bg-input);color:var(--text-muted);
  transition:border-color .15s ease,box-shadow .15s ease;
}
.store-search:focus-within{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft);}
.store-search input{width:100%;border:0;outline:0;background:transparent;color:var(--text-primary);font:inherit;font-size:12.5px;}
.store-search input::placeholder{color:var(--text-muted);}
.store-filter-group{display:flex;flex-direction:column;gap:3px;}
.store-filter-title{
  margin:0 0 7px;color:var(--text-muted);font-size:10.5px;font-weight:800;
  letter-spacing:.08em;text-transform:uppercase;
}
.store-filter-row{
  display:flex;align-items:center;gap:8px;width:100%;padding:6px 0;
  border:0;background:none;color:var(--text-secondary);font:inherit;font-size:12.5px;
  font-weight:600;text-align:left;cursor:pointer;transition:color .15s ease;
}
.store-filter-row:hover,.store-filter-row.active{color:var(--text-primary);}
.store-filter-check{
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
  width:15px;height:15px;border:1px solid var(--border);border-radius:4px;
  color:var(--accent-ink);transition:background .15s ease,border-color .15s ease;
}
.store-filter-row.active .store-filter-check{border-color:var(--accent);background:var(--accent);}
.store-filter-count{margin-left:auto;color:var(--text-muted);font-size:10.5px;font-weight:700;}
.store-clear-filters{
  align-self:flex-start;padding:0;border:0;background:none;color:var(--accent);
  font:inherit;font-size:11.5px;font-weight:800;cursor:pointer;
}
.store-results{display:flex;flex-direction:column;gap:18px;min-width:0;}
.store-results-bar{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;}
.store-results-bar > span{color:var(--text-muted);font-size:12px;font-weight:600;}
.store-sort select{
  padding:9px 32px 9px 12px;border:1px solid var(--border);border-radius:8px;
  background:var(--bg-input);color:var(--text-primary);font:inherit;font-size:12px;font-weight:700;
  outline:none;cursor:pointer;
}
.store-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:28px 20px;}
.store-skeleton{display:flex;flex-direction:column;gap:12px;}
.store-skeleton-media,.store-skeleton-line{
  display:block;border-radius:12px;background:var(--bg-card-alt);
  animation:store-pulse 1.4s ease-in-out infinite;
}
.store-skeleton-media{aspect-ratio:16/9;border-radius:12px;}
.store-skeleton-line{height:15px;}
.store-skeleton-line-sm{width:56%;}
.store-filters-toggle{display:none;}
@keyframes store-pulse{0%,100%{opacity:.55;}50%{opacity:1;}}
@media(max-width:1200px){
  .store-catalog-layout{grid-template-columns:215px minmax(0,1fr);}
  .store-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
}
@media(max-width:900px){
  .store-catalog-layout{grid-template-columns:1fr;}
  .store-filters-toggle{
    display:flex;align-items:center;gap:8px;align-self:flex-start;padding:9px 13px;
    border:1px solid var(--border);border-radius:8px;background:var(--bg-card);
    color:var(--text-primary);font:inherit;font-size:12px;font-weight:800;cursor:pointer;
  }
  .store-filters-count{
    display:flex;align-items:center;justify-content:center;min-width:18px;height:18px;
    border-radius:9px;background:var(--accent);color:var(--accent-ink);font-size:10px;
  }
  .store-filters-chevron{margin-left:auto;transition:transform .2s ease;}
  .store-filters-chevron.open{transform:rotate(180deg);}
  .store-filters-panel{
    display:none;position:static;padding:18px;border:1px solid var(--border);
    border-radius:12px;background:var(--bg-card);
  }
  .store-filters-panel.open{display:flex;}
}
@media(max-width:700px){
  .store-grid{grid-template-columns:1fr;gap:28px;}
  .store-catalog-head h2{font-size:34px;}
  .store-results-bar{align-items:flex-start;}
}
.store-catalog.is-internal{gap:20px;}
.store-catalog.is-internal .store-catalog-head h2{font-size:clamp(26px,3vw,36px);}
.store-catalog.is-internal .store-catalog-layout{grid-template-columns:210px minmax(0,1fr);}
@media(max-width:900px){
  .store-catalog.is-internal .store-catalog-layout{grid-template-columns:1fr;}
}
</style>
