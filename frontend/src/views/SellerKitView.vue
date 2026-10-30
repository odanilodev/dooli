<template>
  <main class="content public-content seller-kit">
    <div v-if="loading" class="seller-kit-loading" aria-label="Carregando kit">
      <section class="seller-kit-hero-skeleton" aria-hidden="true">
        <span class="seller-skeleton seller-skeleton-avatar"></span>
        <div class="seller-skeleton-lines">
          <span class="seller-skeleton seller-skeleton-line seller-skeleton-line-lg"></span>
          <span class="seller-skeleton seller-skeleton-line"></span>
          <span class="seller-skeleton seller-skeleton-line seller-skeleton-line-sm"></span>
        </div>
        <span class="seller-skeleton seller-skeleton-store"></span>
      </section>

      <div class="seller-kit-grid" aria-hidden="true">
        <div v-for="n in 6" :key="n" class="seller-kit-skeleton-card">
          <span class="seller-skeleton seller-skeleton-media"></span>
          <span class="seller-skeleton seller-skeleton-line"></span>
          <span class="seller-skeleton seller-skeleton-line seller-skeleton-line-sm"></span>
        </div>
      </div>
    </div>

    <div v-else-if="error" class="public-empty">
      <strong>Kit indisponível</strong>
      <span>{{ error }}</span>
      <router-link :to="publicRoute('public-loja')" class="btn btn-outline btn-sm">Ir para a loja</router-link>
    </div>

    <template v-else-if="kit">
      <section class="seller-kit-hero">
        <div class="seller-kit-identity">
          <div class="seller-kit-avatar" aria-hidden="true">{{ initials }}</div>

          <div class="seller-kit-identity-body">
            <p class="public-eyebrow">Kit de links</p>
            <h1>{{ kit.seller.name }}</h1>
            <p class="seller-kit-description">
              Copie e compartilhe os links abaixo. O código
              <strong>{{ kit.seller.code }}</strong> já acompanha cada indicação.
            </p>
          </div>
        </div>

        <div class="seller-kit-store">
          <div class="seller-kit-store-head">
            <span class="seller-kit-label">Link geral da loja</span>
            <span class="seller-kit-status">Indicação ativa</span>
          </div>

          <code class="seller-kit-store-url">{{ kit.store_url }}</code>

          <div class="seller-kit-store-actions">
            <button class="btn btn-primary btn-sm" type="button" aria-live="polite" @click="copyStoreLink">
              <svg v-if="copiedKey === 'store'" class="icon" width="14" height="14" viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/></svg>
              <svg v-else class="icon" width="14" height="14" viewBox="0 0 24 24"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
              {{ copiedKey === 'store' ? 'Copiado!' : 'Copiar link' }}
            </button>
            <button class="btn btn-outline btn-sm" type="button" @click="shareStoreLink">
              <svg class="icon" width="15" height="15" viewBox="0 0 24 24"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="M16 6l-4-4-4 4"/><path d="M12 2v14"/></svg>
              Compartilhar
            </button>
          </div>
        </div>
      </section>

      <section class="seller-kit-catalog">
        <div class="seller-kit-toolbar">
          <div>
            <h2>Produtos</h2>
            <p>{{ resultLabel }}</p>
          </div>

          <label class="seller-kit-search" for="seller-kit-search">
            <svg class="icon" width="17" height="17" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
            <span class="sr-only">Buscar produto</span>
            <input id="seller-kit-search" v-model="search" type="search" placeholder="Buscar produto...">
            <button
              v-if="search"
              class="seller-kit-search-clear"
              type="button"
              aria-label="Limpar busca"
              @click="search = ''"
            >
              ×
            </button>
          </label>
        </div>

        <div v-if="kit.products.length" class="seller-kit-filterbar">
          <div class="seller-kit-chips" role="group" aria-label="Filtrar por tipo de produto">
            <button
              v-for="option in typeOptions"
              :key="option.key"
              class="seller-kit-chip"
              :class="{ active: productType === option.key }"
              type="button"
              @click="productType = option.key"
            >
              {{ option.label }}
              <span>{{ option.count }}</span>
            </button>
          </div>

          <label class="seller-kit-sort">
            <span>Ordenar por</span>
            <select v-model="sortBy" class="form-control">
              <option value="relevance">Relevância</option>
              <option value="price_asc">Menor preço</option>
              <option value="price_desc">Maior preço</option>
              <option value="name">Nome</option>
            </select>
          </label>
        </div>

        <div v-if="filteredProducts.length" class="seller-kit-grid">
          <SellerKitProductCard
            v-for="(product, index) in filteredProducts"
            :key="product.id"
            :product="product"
            :index="index"
            :copied="copiedKey === product.id"
            @copy="copyProduct"
            @share="shareProduct"
          />
        </div>

        <div v-else-if="!kit.products.length" class="public-empty">
          <svg class="icon" width="38" height="38" viewBox="0 0 24 24"><path d="M4 7h16v12H4z"/><path d="M8 7V5h8v2"/></svg>
          <strong>Nenhum produto disponível</strong>
          <span>Assim que houver produtos ativos na loja, eles aparecerão aqui.</span>
          <router-link :to="publicRoute('public-loja')" class="btn btn-outline btn-sm">Ir para a loja</router-link>
        </div>

        <div v-else class="public-empty">
          <svg class="icon" width="38" height="38" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          <strong>Nenhum produto encontrado</strong>
          <span>Tente outro termo ou remova os filtros selecionados.</span>
          <button class="btn btn-outline btn-sm" type="button" @click="clearFilters">Limpar busca e filtros</button>
        </div>
      </section>
    </template>
  </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api/http';
import SellerKitProductCard from '../components/seller/SellerKitProductCard.vue';
import { useToast } from '../composables/useToast';
import { useTenant } from '../composables/useTenant';

const route = useRoute();
const toast = useToast();
const { tenant, companySlug, publicRoute } = useTenant();
const kit = ref(null);
const loading = ref(true);
const error = ref(null);
const search = ref('');
const productType = ref('all');
const sortBy = ref('relevance');
const copiedKey = ref(null);
let copyTimer = null;

const token = computed(() => String(route.params.token || ''));
const products = computed(() => kit.value?.products ?? []);
const initials = computed(() => {
  const parts = String(kit.value?.seller?.name || '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2);

  return parts.map((part) => part[0]).join('').toUpperCase() || 'V';
});

const typeOptions = computed(() => {
  const subscriptions = products.value.filter((product) => product.is_subscription).length;

  return [
    { key: 'all', label: 'Todos', count: products.value.length },
    { key: 'one_time', label: 'Compra única', count: products.value.length - subscriptions },
    { key: 'subscription', label: 'Assinaturas', count: subscriptions },
  ].filter((option) => option.key === 'all' || option.count > 0);
});

const filteredProducts = computed(() => {
  const term = normalize(search.value.trim());
  const filtered = products.value.filter((product) => {
    const matchesType = productType.value === 'all'
      || (productType.value === 'subscription' ? product.is_subscription : !product.is_subscription);
    const matchesSearch = !term || normalize(product.title).includes(term);

    return matchesType && matchesSearch;
  });

  return [...filtered].sort((a, b) => {
    if (sortBy.value === 'price_asc') {
      return currentPrice(a) - currentPrice(b);
    }

    if (sortBy.value === 'price_desc') {
      return currentPrice(b) - currentPrice(a);
    }

    if (sortBy.value === 'name') {
      return String(a.title).localeCompare(String(b.title), 'pt-BR');
    }

    return products.value.indexOf(a) - products.value.indexOf(b);
  });
});

const resultLabel = computed(() => {
  const total = products.value.length;
  const visible = filteredProducts.value.length;

  if (!total) {
    return 'Nenhum produto disponível.';
  }

  if (visible === total) {
    return total === 1 ? '1 produto disponível' : `${total} produtos disponíveis`;
  }

  return `${visible} de ${total} produtos`;
});

onMounted(load);

onBeforeUnmount(() => {
  if (copyTimer) {
    clearTimeout(copyTimer);
  }
});

async function load() {
  loading.value = true;
  error.value = null;

  try {
    const endpoint = tenant.isDomainMode
      ? `/public/tenant/seller-kits/${encodeURIComponent(token.value)}`
      : `/public/companies/${encodeURIComponent(companySlug.value)}/seller-kits/${encodeURIComponent(token.value)}`;

    const { data } = await api.get(endpoint);
    kit.value = data.data;
  } catch (e) {
    kit.value = null;
    error.value = e?.message || 'Este kit não está disponível.';
  } finally {
    loading.value = false;
  }
}

async function copyStoreLink() {
  await copyText(kit.value.store_url, 'Link da loja copiado.', 'store');
}

async function copyProduct(product) {
  await copyText(product.share_url, 'Link do produto copiado.', product.id);
}

function shareStoreLink() {
  const seller = kit.value?.seller?.name;
  const message = seller
    ? `Confira a loja de ${seller}: ${kit.value.store_url}`
    : `Confira a loja: ${kit.value.store_url}`;

  return shareOnWhatsApp(message);
}

function shareProduct(product) {
  return shareOnWhatsApp(`Confira ${product.title}: ${product.share_url}`);
}

function shareOnWhatsApp(message) {
  const url = `https://wa.me/?text=${encodeURIComponent(message)}`;

  window.open(url, '_blank', 'noopener,noreferrer');
}

async function copyText(value, successMessage, key = null) {
  try {
    if (navigator.clipboard?.writeText) {
      try {
        await navigator.clipboard.writeText(value);
      } catch (e) {
        fallbackCopy(value);
      }
    } else {
      fallbackCopy(value);
    }

    if (key !== null) {
      markCopied(key);
    }

    toast.success(successMessage);

    return true;
  } catch (e) {
    toast.error('Não foi possível copiar o link.');

    return false;
  }
}

function fallbackCopy(value) {
  const textarea = document.createElement('textarea');
  textarea.value = value;
  textarea.setAttribute('readonly', '');
  textarea.style.position = 'fixed';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.select();
  document.execCommand('copy');
  textarea.remove();
}

function markCopied(key) {
  copiedKey.value = key;

  if (copyTimer) {
    clearTimeout(copyTimer);
  }

  copyTimer = setTimeout(() => {
    if (copiedKey.value === key) {
      copiedKey.value = null;
    }
  }, 1800);
}

function clearFilters() {
  search.value = '';
  productType.value = 'all';
  sortBy.value = 'relevance';
}

function normalize(value) {
  return String(value || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
}

function currentPrice(product) {
  return Number(Number(product.sale) > 0 ? product.sale : product.price);
}
</script>

<style scoped>
.seller-kit{display:flex;flex-direction:column;gap:22px;}
.seller-kit-hero{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,420px);gap:14px;align-items:stretch;}
.seller-kit-identity{
  position:relative;display:flex;align-items:flex-start;gap:14px;overflow:hidden;
  padding:18px;border:1px solid var(--border);border-radius:16px;
  background:var(--bg-card);box-shadow:var(--shadow-sm);
}
.seller-kit-identity::after{
  content:'';position:absolute;right:-50px;top:-80px;width:240px;height:240px;border-radius:50%;
  background:radial-gradient(circle,var(--accent-soft),transparent 70%);pointer-events:none;
}
.seller-kit-avatar{
  position:relative;z-index:1;display:flex;align-items:center;justify-content:center;flex-shrink:0;
  width:52px;height:52px;border-radius:50%;background:var(--accent-grad);color:var(--accent-ink);
  font-size:17px;font-weight:800;box-shadow:0 6px 16px color-mix(in srgb,var(--accent) 28%,transparent);
}
.seller-kit-identity-body{position:relative;z-index:1;display:flex;flex-direction:column;gap:4px;min-width:0;}
.seller-kit-identity h1{font-size:clamp(28px,3.2vw,38px);line-height:1.05;letter-spacing:-.04em;}
.seller-kit-description{max-width:560px;color:var(--text-secondary);font-size:12.5px;line-height:1.5;}
.seller-kit-description strong{color:var(--text-primary);}
.seller-kit-store{
  display:flex;flex-direction:column;gap:8px;padding:16px;border:1px solid var(--border);
  border-radius:16px;background:var(--bg-card-alt);box-shadow:var(--shadow-sm);
}
.seller-kit-store-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}
.seller-kit-label{color:var(--text-muted);font-size:10.5px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;}
.seller-kit-status{
  padding:3px 7px;border-radius:999px;background:var(--accent-soft);color:var(--accent);
  font-size:9px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;
}
.seller-kit-store-url{
  display:block;padding:7px 10px;border:1px solid var(--border);border-radius:9px;
  background:var(--bg-card);color:var(--text-secondary);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
  font-size:11px;line-height:1.45;overflow-wrap:anywhere;
}
.seller-kit-store-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:auto;}
.seller-kit-catalog{display:flex;flex-direction:column;gap:18px;}
.seller-kit-toolbar{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.seller-kit-toolbar h2{font-size:24px;letter-spacing:-.03em;}
.seller-kit-toolbar p{margin-top:3px;color:var(--text-muted);font-size:12px;}
.seller-kit-search{
  display:flex;align-items:center;gap:9px;width:min(100%,320px);padding:10px 12px;
  border:1px solid var(--border);border-radius:10px;background:var(--bg-card);color:var(--text-muted);
  transition:border-color .15s ease,box-shadow .15s ease;
}
.seller-kit-search:focus-within{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft);}
.seller-kit-search input{width:100%;border:0;outline:0;background:transparent;color:var(--text-primary);font:inherit;font-size:12.5px;}
.seller-kit-search input::placeholder{color:var(--text-muted);}
.seller-kit-search-clear{
  display:flex;align-items:center;justify-content:center;flex-shrink:0;width:20px;height:20px;
  padding:0;border:0;border-radius:50%;background:var(--bg-card-alt);color:var(--text-muted);
  font:inherit;font-size:14px;line-height:1;cursor:pointer;
}
.seller-kit-search-clear:hover{color:var(--text-primary);}
.seller-kit-filterbar{
  display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;
  padding:10px 12px;border:1px solid var(--border);border-radius:14px;background:var(--bg-card);
}
.seller-kit-chips{display:flex;gap:8px;flex-wrap:wrap;}
.seller-kit-chip{
  display:inline-flex;align-items:center;gap:6px;padding:7px 11px;border:1px solid var(--border);
  border-radius:999px;background:var(--bg-card-alt);color:var(--text-secondary);
  font:inherit;font-size:11.5px;font-weight:700;cursor:pointer;
  transition:background .15s ease,border-color .15s ease,color .15s ease;
}
.seller-kit-chip:hover{border-color:color-mix(in srgb,var(--accent) 45%,var(--border));color:var(--text-primary);}
.seller-kit-chip.active{border-color:transparent;background:var(--accent-grad);color:var(--accent-ink);}
.seller-kit-chip span{
  display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;
  padding:0 5px;border-radius:9px;background:color-mix(in srgb,currentColor 12%,transparent);
  font-size:9.5px;font-weight:800;
}
.seller-kit-sort{display:flex;align-items:center;gap:8px;color:var(--text-muted);font-size:11.5px;font-weight:700;white-space:nowrap;}
.seller-kit-sort .form-control{width:auto;min-width:130px;padding:8px 10px;font-size:11.5px;}
.seller-kit-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:16px;}
.seller-kit-loading{display:flex;flex-direction:column;gap:24px;}
.seller-kit-hero-skeleton{
  display:grid;grid-template-columns:52px minmax(0,1fr) 320px;gap:14px;align-items:center;
  padding:18px;border:1px solid var(--border);border-radius:16px;background:var(--bg-card);
}
.seller-skeleton{display:block;border-radius:10px;background:var(--bg-card-alt);animation:seller-pulse 1.4s ease-in-out infinite;}
.seller-skeleton-avatar{width:52px;height:52px;border-radius:50%;}
.seller-skeleton-lines{display:flex;flex-direction:column;gap:10px;}
.seller-skeleton-line{height:14px;}
.seller-skeleton-line-lg{width:55%;height:30px;}
.seller-skeleton-line-sm{width:38%;}
.seller-skeleton-store{height:100%;min-height:132px;border-radius:16px;}
.seller-kit-skeleton-card{
  display:flex;flex-direction:column;gap:10px;overflow:hidden;padding-bottom:14px;
  border:1px solid var(--border);border-radius:12px;background:var(--bg-card);
}
.seller-skeleton-media{aspect-ratio:16/9;border-radius:0;}
.seller-kit-skeleton-card .seller-skeleton-line{margin:0 14px;}
@keyframes seller-pulse{0%,100%{opacity:.55;}50%{opacity:1;}}
@media(max-width:900px){
  .seller-kit-hero{grid-template-columns:1fr;}
  .seller-kit-hero-skeleton{grid-template-columns:52px minmax(0,1fr);}
  .seller-skeleton-store{grid-column:1 / -1;min-height:120px;}
}
@media(max-width:640px){
  .seller-kit-identity{flex-direction:row;align-items:flex-start;padding:16px;gap:12px;}
  .seller-kit-avatar{width:44px;height:44px;font-size:15px;}
  .seller-kit-identity h1{font-size:28px;}
  .seller-kit-identity-body{gap:3px;}
  .seller-kit-description{font-size:12px;}
  .seller-kit-store{padding:16px;}
  .seller-kit-store-url{font-size:10.5px;}
  .seller-kit-toolbar{align-items:flex-start;}
  .seller-kit-search{width:100%;}
  .seller-kit-filterbar{align-items:flex-start;flex-direction:column;}
  .seller-kit-sort{width:100%;justify-content:space-between;}
  .seller-kit-sort .form-control{flex:1;min-width:0;}
  .seller-kit-grid{grid-template-columns:1fr;}
  .seller-kit-hero-skeleton{grid-template-columns:1fr;padding:20px;}
  .seller-skeleton-avatar{width:44px;height:44px;}
}
@media(prefers-reduced-motion:reduce){
  .seller-skeleton{animation:none;}
}
</style>
