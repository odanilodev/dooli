<template>
  <router-link :to="to" class="card card-product" style="display:block;">
    <div class="card-thumb" :style="{ background: grad }">
      <span class="glow"></span>
      <img v-if="product.image" :src="product.image" :alt="product.title" class="product-thumb-image" />
      <svg v-else class="icon" width="30" height="30" viewBox="0 0 24 24"><path :d="icon" /></svg>
      <span v-if="onSale" class="card-dur sale-badge">Oferta</span>
    </div>
    <div class="card-body">
      <span class="card-title">{{ product.title }}</span>
      <span class="product-price">
        <span v-if="onSale" class="price-old">{{ formatCurrency(product.price) }}</span>
        <span class="price-now">{{ formatCurrency(currentPrice) }}</span>
      </span>
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
