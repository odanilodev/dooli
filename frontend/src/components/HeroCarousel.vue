<template>
  <section class="hero">
    <img v-if="slide.image_url" class="hero-illustration" :src="slide.image_url" alt="">
    <svg v-else class="hero-illustration" width="440" height="290" viewBox="0 0 440 290" fill="none">
      <defs>
        <radialGradient id="heroGlow" cx="50%" cy="45%" r="60%">
          <stop offset="0%" stop-color="#22c55e" stop-opacity="0.32"/>
          <stop offset="100%" stop-color="#22c55e" stop-opacity="0"/>
        </radialGradient>
      </defs>
      <circle cx="250" cy="150" r="165" fill="url(#heroGlow)"/>
      <g transform="rotate(-6 150 120)" opacity="0.95">
        <rect x="70" y="70" width="160" height="100" rx="10" fill="#0f2230" stroke="#1f3a4a" stroke-width="2"/>
        <rect x="82" y="82" width="136" height="76" rx="4" fill="#081119"/>
        <rect x="92" y="128" width="7" height="22" fill="#14b8a6"/>
        <rect x="104" y="112" width="7" height="38" fill="#22c55e"/>
        <rect x="116" y="138" width="7" height="12" fill="#ef4444"/>
        <rect x="128" y="98" width="7" height="52" fill="#22c55e"/>
        <rect x="140" y="120" width="7" height="30" fill="#14b8a6"/>
        <rect x="152" y="106" width="7" height="44" fill="#22c55e"/>
        <rect x="164" y="130" width="7" height="20" fill="#ef4444"/>
        <rect x="176" y="92" width="7" height="58" fill="#22c55e"/>
        <rect x="188" y="118" width="7" height="32" fill="#14b8a6"/>
      </g>
      <g transform="rotate(5 300 150)">
        <rect x="230" y="88" width="185" height="115" rx="12" fill="#0f2230" stroke="#1f3a4a" stroke-width="2"/>
        <rect x="244" y="102" width="157" height="87" rx="4" fill="#081119"/>
        <path d="M254 162 L280 142 L302 154 L326 118 L354 128 L382 98" stroke="#22c55e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="382" cy="98" r="4.5" fill="#22c55e"/>
      </g>
      <rect x="40" y="242" width="370" height="7" rx="3.5" fill="#0c1a20"/>
      <g fill="#081119">
        <circle cx="150" cy="204" r="23"/>
        <path d="M103 268c0-31 21-50 47-50s47 19 47 50z"/>
      </g>
    </svg>

    <span class="hero-eyebrow">{{ slide.eyebrow }}</span>
    <h1 class="hero-title">{{ slide.title }} <em v-if="slide.title_highlight">{{ slide.title_highlight }}</em></h1>
    <p class="hero-desc">{{ slide.description }}</p>
    <div class="hero-actions">
      <router-link v-if="slide.cta_label" :to="slide.cta_link || '/cursos'" class="btn btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5l12 7-12 7V5z"/></svg>
        {{ slide.cta_label }}
      </router-link>
      <router-link v-if="slide.secondary_label" :to="slide.secondary_link || '/cursos'" class="btn btn-ghost-light">{{ slide.secondary_label }}</router-link>
    </div>
    <span v-if="slide.quote" class="hero-quote">"{{ slide.quote }}"</span>

    <template v-if="slides.length > 1">
      <button class="hero-nav prev" aria-label="Anterior" @click="prevSlide">
        <svg class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2.2"><path d="M15 6l-6 6 6 6"/></svg>
      </button>
      <button class="hero-nav next" aria-label="Próximo" @click="nextSlide">
        <svg class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2.2"><path d="M9 6l6 6-6 6"/></svg>
      </button>
      <div class="hero-dots">
        <span v-for="(item, index) in slides" :key="index" :class="{ active: activeIndex === index }" @click="activeIndex = index"></span>
      </div>
    </template>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
  banners: { type: Array, default: () => [] },
});

const DEFAULT_SLIDE = {
  eyebrow: 'CURSO EM DESTAQUE',
  title: 'Do Zero ao Trader',
  title_highlight: 'Consistente',
  description: 'Aprenda estratégias, gestão de risco e psicologia para evoluir no mercado financeiro.',
  quote: 'Disciplina hoje, liberdade amanhã.',
  cta_label: 'Continuar assistindo',
  cta_link: '/cursos',
  secondary_label: 'Ver detalhes',
  secondary_link: '/cursos',
  image_url: null,
};

const slides = computed(() => {
  const list = (props.banners ?? []).filter((banner) => banner.title || banner.description || banner.image_url);

  return list.length ? list : [DEFAULT_SLIDE];
});

const activeIndex = ref(0);

const slide = computed(() => slides.value[activeIndex.value] ?? slides.value[0]);

watch(
  () => slides.value.length,
  (length) => {
    if (activeIndex.value >= length) {
      activeIndex.value = 0;
    }
  },
);

function nextSlide() {
  activeIndex.value = (activeIndex.value + 1) % slides.value.length;
}

function prevSlide() {
  activeIndex.value = (activeIndex.value + slides.value.length - 1) % slides.value.length;
}
</script>
