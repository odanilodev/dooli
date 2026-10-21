<template>
  <router-link :to="to" class="store-card">
    <div class="store-card-media" :style="{ background: grad }">
      <img
        v-if="product.image_url || product.image"
        :src="product.image_url || product.image"
        :alt="product.title"
        loading="lazy"
        decoding="async"
      />
      <svg v-else class="icon store-card-fallback" width="42" height="42" viewBox="0 0 24 24"><path :d="icon" /></svg>

      <span class="store-card-shade" aria-hidden="true"></span>
      <span v-if="onSale" class="store-card-sale">Oferta</span>
      <span class="store-card-open" aria-hidden="true">
        <svg class="icon" width="15" height="15" viewBox="0 0 24 24"><path d="M7 17L17 7M9 7h8v8"/></svg>
      </span>
    </div>

    <div class="store-card-body">
      <h3 class="store-card-title">{{ product.title }}</h3>
      <span class="store-card-meta">{{ product.access_label }}</span>

      <div class="store-card-foot">
        <div class="store-card-price">
          <span v-if="onSale" class="price-old">{{ formatCurrency(product.price) }}</span>
          <span class="price-now">{{ formatCurrency(currentPrice) }}<small v-if="product.is_subscription">/mês</small></span>
        </div>
      </div>
    </div>
  </router-link>
</template>

<script setup>
import { computed } from 'vue';
import { paletteAt } from '../data/palette';
import { formatCurrency } from '../utils/format';

const props = defineProps({
  product: { type: Object, required: true },
  index: { type: Number, default: 0 },
  to: { type: [String, Object], default: '/loja' },
});

const palette = computed(() => paletteAt(props.index));
const icon = computed(() => palette.value.icon);
const grad = computed(() => palette.value.grad);

const onSale = computed(() => Number(props.product.sale) > 0);
const currentPrice = computed(() => (onSale.value ? props.product.sale : props.product.price));
</script>

<style scoped>
.store-card{
  display:flex;flex-direction:column;height:100%;overflow:hidden;
  border:1px solid var(--border);border-radius:12px;background:var(--bg-card);
  color:inherit;text-decoration:none;
  transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease;
}
.store-card:hover{
  transform:translateY(-3px);border-color:color-mix(in srgb,var(--accent) 55%,var(--border));
  box-shadow:0 18px 34px rgba(15,23,42,.13);
}
.store-card-media{
  position:relative;display:flex;align-items:center;justify-content:center;overflow:hidden;
  aspect-ratio:16/9;background:#101b2a;
}
.store-card-media img{
  position:absolute;inset:0;width:100%;height:100%;object-fit:cover;
  transition:transform .45s cubic-bezier(.2,.7,.2,1);
}
.store-card:hover .store-card-media img{transform:scale(1.04);}
.store-card-fallback{position:relative;z-index:1;color:#fff;opacity:.68;}
.store-card-shade{
  position:absolute;inset:0;z-index:1;pointer-events:none;
  background:linear-gradient(180deg,rgba(3,9,16,.02) 52%,rgba(3,9,16,.42) 100%);
}
.store-card-sale,.store-card-open{z-index:2;}
.store-card-sale{
  position:absolute;top:9px;left:9px;padding:4px 8px;border-radius:5px;
  background:var(--accent-grad);color:var(--accent-ink);
  font-size:9px;font-weight:900;letter-spacing:.04em;text-transform:uppercase;
}
.store-card-open{
  position:absolute;top:9px;right:9px;display:flex;align-items:center;justify-content:center;
  width:28px;height:28px;border-radius:50%;background:rgba(5,14,23,.62);color:#fff;
  border:1px solid rgba(255,255,255,.22);backdrop-filter:blur(6px);
  transition:background .18s ease,transform .18s ease;
}
.store-card:hover .store-card-open{background:var(--accent);color:var(--accent-ink);transform:translate(1px,-1px);}
.store-card-body{display:flex;flex:1;flex-direction:column;gap:5px;padding:13px 14px 14px;}
.store-card-title{
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
  margin:0;font-size:14.5px;font-weight:800;line-height:1.35;
}
.store-card-meta{color:var(--text-muted);font-size:10.5px;font-weight:600;}
.store-card-foot{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-top:auto;padding-top:8px;}
.store-card-price{display:flex;flex-direction:column;gap:1px;}
.store-card-price .price-now{font-size:16px;letter-spacing:-.02em;}
.store-card-price .price-now small{font-size:9.5px;font-weight:700;color:var(--text-muted);}
.store-card-price .price-old{font-size:10px;}
@media(max-width:700px){
  .store-card-title{font-size:15.5px;}
  .store-card-price .price-now{font-size:18px;}
}
@media(prefers-reduced-motion:reduce){
  .store-card,.store-card-media img,.store-card-open{transition:none;}
}
</style>
