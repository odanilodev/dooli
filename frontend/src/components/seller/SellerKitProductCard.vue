<template>
  <article class="seller-product-card" :class="{ 'is-copied': copied }">
    <div class="seller-product-media" :style="{ background: grad }">
      <img
        v-if="product.image_url"
        :src="product.image_url"
        :alt="product.title"
        loading="lazy"
        decoding="async"
      >
      <svg v-else class="icon seller-product-fallback" width="42" height="42" viewBox="0 0 24 24"><path :d="icon" /></svg>

      <span class="seller-product-shade" aria-hidden="true"></span>
      <span v-if="onSale" class="seller-product-sale">Oferta</span>
      <a
        :href="product.share_url"
        class="seller-product-open"
        target="_blank"
        rel="noopener noreferrer"
        :aria-label="`Abrir ${product.title}`"
        title="Abrir produto"
      >
        <svg class="icon" width="15" height="15" viewBox="0 0 24 24"><path d="M7 17L17 7M9 7h8v8"/></svg>
      </a>
    </div>

    <div class="seller-product-body">
      <h3 class="seller-product-title">{{ product.title }}</h3>
      <span class="seller-product-meta">{{ product.access_label }}</span>

      <div class="seller-product-price">
        <span v-if="onSale" class="price-old">{{ formatCurrency(Number(product.price)) }}</span>
        <span class="price-now">
          {{ formatCurrency(currentPrice) }}<small v-if="product.is_subscription">/mês</small>
        </span>
      </div>

      <div class="seller-product-actions">
        <button
          class="btn btn-primary btn-sm seller-product-copy"
          type="button"
          aria-live="polite"
          @click="emit('copy', product)"
        >
          <svg v-if="copied" class="icon" width="14" height="14" viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/></svg>
          <svg v-else class="icon" width="14" height="14" viewBox="0 0 24 24"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          {{ copied ? 'Copiado!' : 'Copiar link' }}
        </button>
        <button
          class="btn btn-outline btn-sm seller-product-share"
          type="button"
          aria-label="Compartilhar produto"
          title="Compartilhar"
          @click="emit('share', product)"
        >
          <svg class="icon" width="15" height="15" viewBox="0 0 24 24"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="M16 6l-4-4-4 4"/><path d="M12 2v14"/></svg>
        </button>
      </div>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue';
import { paletteAt } from '../../data/palette';
import { formatCurrency } from '../../utils/format';

const props = defineProps({
  product: { type: Object, required: true },
  index: { type: Number, default: 0 },
  copied: { type: Boolean, default: false },
});

const emit = defineEmits(['copy', 'share']);
const palette = computed(() => paletteAt(props.index));
const icon = computed(() => palette.value.icon);
const grad = computed(() => palette.value.grad);
const onSale = computed(() => Number(props.product.sale) > 0);
const currentPrice = computed(() => Number(onSale.value ? props.product.sale : props.product.price));
</script>

<style scoped>
.seller-product-card{
  display:flex;flex-direction:column;height:100%;overflow:hidden;
  border:1px solid var(--border);border-radius:12px;background:var(--bg-card);
  color:inherit;
  transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease;
}
.seller-product-card:hover{
  transform:translateY(-3px);border-color:color-mix(in srgb,var(--accent) 55%,var(--border));
  box-shadow:0 18px 34px rgba(15,23,42,.13);
}
.seller-product-card.is-copied{border-color:color-mix(in srgb,var(--accent) 65%,var(--border));}
.seller-product-media{
  position:relative;display:flex;align-items:center;justify-content:center;overflow:hidden;
  aspect-ratio:16/9;background:#101b2a;
}
.seller-product-media img{
  position:absolute;inset:0;width:100%;height:100%;object-fit:cover;
  transition:transform .45s cubic-bezier(.2,.7,.2,1);
}
.seller-product-card:hover .seller-product-media img{transform:scale(1.04);}
.seller-product-fallback{position:relative;z-index:1;color:#fff;opacity:.68;}
.seller-product-shade{
  position:absolute;inset:0;z-index:1;pointer-events:none;
  background:linear-gradient(180deg,rgba(3,9,16,.02) 52%,rgba(3,9,16,.42) 100%);
}
.seller-product-sale,.seller-product-open{z-index:2;}
.seller-product-sale{
  position:absolute;top:9px;left:9px;padding:4px 8px;border-radius:5px;
  background:var(--accent-grad);color:var(--accent-ink);
  font-size:9px;font-weight:900;letter-spacing:.04em;text-transform:uppercase;
}
.seller-product-open{
  position:absolute;top:9px;right:9px;display:flex;align-items:center;justify-content:center;
  width:28px;height:28px;border-radius:50%;background:rgba(5,14,23,.62);color:#fff;
  border:1px solid rgba(255,255,255,.22);backdrop-filter:blur(6px);
  transition:background .18s ease,transform .18s ease;
}
.seller-product-open:hover{background:var(--accent);color:var(--accent-ink);transform:translate(1px,-1px);}
.seller-product-body{display:flex;flex:1;flex-direction:column;gap:5px;padding:13px 14px 14px;}
.seller-product-title{
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
  margin:0;font-size:14.5px;font-weight:800;line-height:1.35;
}
.seller-product-meta{color:var(--text-muted);font-size:10.5px;font-weight:600;}
.seller-product-price{display:flex;flex-direction:column;gap:1px;margin-top:auto;padding-top:8px;}
.seller-product-price .price-now{font-size:16px;letter-spacing:-.02em;}
.seller-product-price .price-now small{font-size:9.5px;font-weight:700;color:var(--text-muted);}
.seller-product-price .price-old{font-size:10px;}
.seller-product-actions{display:flex;align-items:stretch;gap:8px;padding-top:10px;}
.seller-product-copy{flex:1;min-width:0;padding-inline:10px;font-size:11.5px;}
.seller-product-share{width:36px;flex:none;padding:0;}
@media(max-width:700px){
  .seller-product-title{font-size:15.5px;}
  .seller-product-price .price-now{font-size:18px;}
}
@media(prefers-reduced-motion:reduce){
  .seller-product-card,.seller-product-media img,.seller-product-open{transition:none;}
}
</style>
