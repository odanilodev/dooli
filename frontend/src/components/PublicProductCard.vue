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

      <span v-if="onSale" class="store-card-sale">Oferta</span>
      <span class="store-card-access">{{ product.access_label }}</span>
    </div>

    <div class="store-card-body">
      <div v-if="visibleTags.length" class="store-card-tags">
        <span v-for="tag in visibleTags" :key="tag" class="store-card-tag">{{ tag }}</span>
      </div>

      <h3 class="store-card-title">{{ product.title }}</h3>
      <p v-if="product.description" class="store-card-desc">{{ product.description }}</p>

      <div class="store-card-foot">
        <div class="store-card-price">
          <span v-if="onSale" class="price-old">{{ formatCurrency(product.price) }}</span>
          <span class="price-now">{{ formatCurrency(currentPrice) }}<small v-if="product.is_subscription">/mês</small></span>
        </div>
        <span class="store-card-cta">
          Ver detalhes
          <svg class="icon" width="14" height="14" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </span>
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
const visibleTags = computed(() => (props.product.tags ?? []).filter(Boolean).slice(0, 2));
</script>

<style scoped>
.store-card{
  display:flex;flex-direction:column;height:100%;overflow:hidden;
  border:1px solid var(--border);border-radius:18px;background:var(--bg-card);
  box-shadow:var(--shadow-sm);color:inherit;
  transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease;
}
.store-card:hover{transform:translateY(-4px);border-color:color-mix(in srgb,var(--accent) 55%,var(--border));box-shadow:var(--shadow);}
.store-card-media{
  position:relative;aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;overflow:hidden;
}
.store-card-media::after{
  content:'';position:absolute;inset:0;
  background:linear-gradient(180deg,transparent 52%,rgba(4,10,18,.42));
  pointer-events:none;
}
.store-card-media img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:transform .35s ease;}
.store-card:hover .store-card-media img{transform:scale(1.035);}
.store-card-fallback{position:relative;z-index:1;color:#fff;opacity:.68;}
.store-card-sale{
  position:absolute;top:12px;left:12px;z-index:2;
  padding:5px 9px;border-radius:999px;background:var(--accent-grad);color:var(--accent-ink);
  font-size:10.5px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;
}
.store-card-access{
  position:absolute;left:12px;bottom:12px;z-index:2;
  padding:5px 9px;border-radius:999px;background:rgba(5,14,23,.72);color:#fff;
  border:1px solid rgba(255,255,255,.16);font-size:10.5px;font-weight:700;backdrop-filter:blur(6px);
}
.store-card-body{display:flex;flex:1;flex-direction:column;gap:8px;padding:16px;}
.store-card-tags{display:flex;flex-wrap:wrap;gap:6px;}
.store-card-tag{
  padding:3px 8px;border-radius:999px;background:var(--accent-soft);color:var(--accent);
  font-size:10.5px;font-weight:800;letter-spacing:.02em;
}
.store-card-title{
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
  margin:0;font-size:16px;font-weight:800;line-height:1.32;
}
.store-card-desc{
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
  margin:0;color:var(--text-muted);font-size:12.5px;line-height:1.5;
}
.store-card-foot{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-top:auto;padding-top:8px;}
.store-card-price{display:flex;flex-direction:column;gap:1px;}
.store-card-price .price-now{font-size:17px;}
.store-card-price .price-now small{font-size:10.5px;font-weight:700;color:var(--text-muted);}
.store-card-cta{
  display:inline-flex;align-items:center;gap:5px;color:var(--accent);
  font-size:11.5px;font-weight:800;white-space:nowrap;
}
.store-card:hover .store-card-cta svg{transform:translateX(2px);}
.store-card-cta svg{transition:transform .18s ease;}
@media(max-width:520px){
  .store-card-body{padding:13px;}
  .store-card-title{font-size:14.5px;}
  .store-card-cta{display:none;}
}
</style>
