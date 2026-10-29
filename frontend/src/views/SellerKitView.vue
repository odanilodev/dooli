<template>
  <main class="content public-content seller-kit">
    <div v-if="loading" class="public-empty">
      <strong>Carregando kit...</strong>
    </div>

    <div v-else-if="error" class="public-empty">
      <strong>Kit indisponível</strong>
      <span>{{ error }}</span>
      <router-link :to="publicRoute('public-loja')" class="btn btn-outline btn-sm">Ir para a loja</router-link>
    </div>

    <template v-else-if="kit">
      <header class="seller-kit-head">
        <p class="public-eyebrow">Kit de links</p>
        <h1>{{ kit.seller.name }}</h1>
        <p>Copie os links abaixo e envie para seus clientes. O código de indicação já vai junto.</p>
      </header>

      <section class="seller-kit-store">
        <div>
          <span class="seller-kit-label">Link geral da loja</span>
          <strong>{{ kit.store_url }}</strong>
        </div>
        <div class="seller-kit-actions">
          <button class="btn btn-outline btn-sm" type="button" @click="copyText(kit.store_url, 'Link da loja copiado.')">Copiar link</button>
          <button class="btn btn-primary btn-sm" type="button" @click="shareText('Loja', kit.store_url)">Compartilhar</button>
        </div>
      </section>

      <div class="seller-kit-toolbar">
        <div>
          <h2>Produtos</h2>
          <p>{{ filteredProducts.length }} produto(s) disponível(is)</p>
        </div>
        <input v-model="search" class="form-control seller-kit-search" type="search" placeholder="Buscar produto...">
      </div>

      <div v-if="filteredProducts.length" class="seller-kit-grid">
        <article v-for="(product, index) in filteredProducts" :key="product.id" class="seller-kit-card">
          <div class="seller-kit-media" :style="{ background: paletteAt(index).grad }">
            <img v-if="product.image_url" :src="product.image_url" :alt="product.title" loading="lazy">
            <svg v-else class="icon" width="34" height="34" viewBox="0 0 24 24"><path :d="paletteAt(index).icon" /></svg>
          </div>

          <div class="seller-kit-body">
            <h3>{{ product.title }}</h3>
            <span>{{ product.access_label }}</span>
            <strong>{{ formatCurrency(Number(product.sale) > 0 ? product.sale : product.price) }}<small v-if="product.is_subscription">/mês</small></strong>

            <div class="seller-kit-card-actions">
              <button class="btn btn-outline btn-sm" type="button" @click="copyText(product.share_url, 'Link do produto copiado.')">Copiar link</button>
              <button class="btn btn-primary btn-sm" type="button" @click="shareProduct(product)">Compartilhar</button>
            </div>
          </div>
        </article>
      </div>

      <div v-else class="public-empty">
        <strong>Nenhum produto encontrado</strong>
        <span>Tente outro termo de busca.</span>
      </div>
    </template>
  </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';
import { useTenant } from '../composables/useTenant';
import { paletteAt } from '../data/palette';
import { formatCurrency } from '../utils/format';

const route = useRoute();
const toast = useToast();
const { tenant, companySlug, publicRoute } = useTenant();
const kit = ref(null);
const loading = ref(true);
const error = ref(null);
const search = ref('');

const token = computed(() => String(route.params.token || ''));
const filteredProducts = computed(() => {
  const term = search.value.trim().toLowerCase();

  if (!kit.value || !term) {
    return kit.value?.products ?? [];
  }

  return kit.value.products.filter((product) => String(product.title).toLowerCase().includes(term));
});

onMounted(load);

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

async function copyText(value, successMessage) {
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(value);
    } else {
      fallbackCopy(value);
    }

    toast.success(successMessage);
  } catch (e) {
    toast.error('Não foi possível copiar o link.');
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

async function shareText(title, url) {
  if (navigator.share) {
    try {
      await navigator.share({ title, url });

      return;
    } catch (e) {
      if (e?.name === 'AbortError') {
        return;
      }
    }
  }

  await copyText(url, 'Link copiado para compartilhar.');
}

function shareProduct(product) {
  return shareText(product.title, product.share_url);
}
</script>

<style scoped>
.seller-kit{display:flex;flex-direction:column;gap:24px;}
.seller-kit-head h1{font-size:clamp(34px,4vw,52px);line-height:1.04;letter-spacing:-.04em;}
.seller-kit-head p:last-child{max-width:620px;margin-top:8px;color:var(--text-secondary);font-size:13px;line-height:1.6;}
.seller-kit-store{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;padding:20px;border:1px solid var(--border);border-radius:16px;background:var(--bg-card);}
.seller-kit-store > div:first-child{display:flex;flex-direction:column;gap:5px;min-width:0;}
.seller-kit-store strong{overflow-wrap:anywhere;font-size:13px;}
.seller-kit-label{color:var(--text-muted);font-size:10.5px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;}
.seller-kit-actions,.seller-kit-card-actions{display:flex;gap:8px;flex-wrap:wrap;}
.seller-kit-toolbar{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.seller-kit-toolbar h2{font-size:20px;letter-spacing:-.02em;}
.seller-kit-toolbar p{margin-top:3px;color:var(--text-muted);font-size:12px;}
.seller-kit-search{max-width:320px;}
.seller-kit-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;}
.seller-kit-card{display:flex;flex-direction:column;overflow:hidden;border:1px solid var(--border);border-radius:14px;background:var(--bg-card);}
.seller-kit-media{position:relative;display:flex;align-items:center;justify-content:center;aspect-ratio:16/9;color:#fff;}
.seller-kit-media img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
.seller-kit-body{display:flex;flex:1;flex-direction:column;gap:5px;padding:15px;}
.seller-kit-body h3{font-size:15px;line-height:1.35;}
.seller-kit-body > span{color:var(--text-muted);font-size:11px;}
.seller-kit-body > strong{margin:5px 0 9px;font-size:18px;color:var(--accent);letter-spacing:-.02em;}
.seller-kit-body > strong small{font-size:10px;color:var(--text-muted);}
.seller-kit-card-actions{margin-top:auto;}
.seller-kit-card-actions .btn{flex:1 1 auto;}
@media(max-width:640px){
  .seller-kit-store{align-items:flex-start;flex-direction:column;}
  .seller-kit-search{max-width:none;width:100%;}
  .seller-kit-grid{grid-template-columns:1fr;}
}
</style>
