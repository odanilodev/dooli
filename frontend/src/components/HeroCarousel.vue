<template>
  <section
    v-if="count"
    class="hero"
    @mouseenter="stopAutoplay"
    @mouseleave="startAutoplay"
    @click.capture="onClickCapture"
    @dragstart.prevent
  >
    <div
      class="hero-track"
      :class="{ 'no-transition': noTransition, 'is-dragging': dragging }"
      :style="trackStyle"
      @transitionend="onTransitionEnd"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerUp"
    >
      <div
        v-for="(item, index) in loopSlides"
        :key="index"
        class="hero-slide"
        :aria-hidden="isClone(index) || undefined"
      >
        <div class="hero-bg" :style="{ backgroundImage: `url(${item.image_url})` }"></div>
        <div class="hero-mask" aria-hidden="true"></div>

        <span class="hero-eyebrow">{{ item.eyebrow }}</span>
        <h1 class="hero-title">{{ item.title }} <em v-if="item.title_highlight">{{ item.title_highlight }}</em></h1>
        <p class="hero-desc">{{ item.description }}</p>
        <div class="hero-actions">
          <router-link v-if="item.cta_label" :to="item.cta_link || '/cursos'" class="btn btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5l12 7-12 7V5z"/></svg>
            {{ item.cta_label }}
          </router-link>
          <router-link v-if="item.secondary_label" :to="item.secondary_link || '/cursos'" class="btn btn-ghost-light">{{ item.secondary_label }}</router-link>
        </div>
        <span v-if="item.quote" class="hero-quote">"{{ item.quote }}"</span>
      </div>
    </div>

    <template v-if="count > 1">
      <button class="hero-nav prev" aria-label="Anterior" @click="prevSlide">
        <svg class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2.2"><path d="M15 6l-6 6 6 6"/></svg>
      </button>
      <button class="hero-nav next" aria-label="Próximo" @click="nextSlide">
        <svg class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2.2"><path d="M9 6l6 6-6 6"/></svg>
      </button>
      <div class="hero-dots">
        <span v-for="(item, index) in slides" :key="index" :class="{ active: realIndex === index }" @click="goTo(index)"></span>
      </div>
    </template>
  </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const AUTOPLAY_MS = 6000;

const props = defineProps({
  banners: { type: Array, default: () => [] },
});

const slides = computed(() => (props.banners ?? []).filter((banner) => banner.image_url));
const count = computed(() => slides.value.length);

const loopSlides = computed(() => {
  if (count.value <= 1) {
    return slides.value;
  }

  return [slides.value[count.value - 1], ...slides.value, slides.value[0]];
});

const position = ref(0);
const noTransition = ref(true);

const dragging = ref(false);
const dragOffset = ref(0);

let startX = 0;
let startWidth = 1;
let pointerId = null;
let moved = false;
let suppressClick = false;

const trackStyle = computed(() => ({
  transform: `translateX(calc(-${position.value * 100}% + ${dragOffset.value}px))`,
}));

const realIndex = computed(() => {
  if (count.value <= 1) {
    return 0;
  }

  return (((position.value - 1) % count.value) + count.value) % count.value;
});

function isClone(index) {
  if (count.value <= 1) {
    return false;
  }

  return index === 0 || index === loopSlides.value.length - 1;
}

let timer = null;

function stopAutoplay() {
  if (timer) {
    clearInterval(timer);
    timer = null;
  }
}

function startAutoplay() {
  stopAutoplay();

  if (count.value > 1) {
    timer = setInterval(advanceForward, AUTOPLAY_MS);
  }
}

function restartAutoplay() {
  startAutoplay();
}

function advanceForward() {
  if (count.value <= 1 || position.value >= loopSlides.value.length - 1) {
    return;
  }

  position.value += 1;
}

function nextSlide() {
  advanceForward();
  restartAutoplay();
}

function prevSlide() {
  if (count.value > 1 && position.value > 0) {
    position.value -= 1;
  }

  restartAutoplay();
}

function goTo(index) {
  position.value = index + 1;
  restartAutoplay();
}

function onPointerDown(event) {
  if (count.value <= 1 || event.button > 0) {
    return;
  }

  stopAutoplay();
  suppressClick = false;
  dragging.value = true;
  moved = false;
  startX = event.clientX;
  startWidth = event.currentTarget.offsetWidth || 1;
  pointerId = event.pointerId;
  dragOffset.value = 0;
  noTransition.value = true;
  event.currentTarget.setPointerCapture?.(event.pointerId);
}

function onPointerMove(event) {
  if (!dragging.value || event.pointerId !== pointerId) {
    return;
  }

  const dx = event.clientX - startX;

  if (Math.abs(dx) > 5) {
    moved = true;
  }

  dragOffset.value = dx;
}

function onPointerUp(event) {
  if (!dragging.value || event.pointerId !== pointerId) {
    return;
  }

  dragging.value = false;
  const dx = dragOffset.value;
  const threshold = Math.min(startWidth * 0.15, 90);

  if (dx <= -threshold) {
    advanceForward();
  } else if (dx >= threshold && position.value > 0) {
    position.value -= 1;
  }

  dragOffset.value = 0;
  noTransition.value = false;
  pointerId = null;
  startAutoplay();

  if (moved) {
    suppressClick = true;
  }
}

function onClickCapture(event) {
  if (suppressClick) {
    event.preventDefault();
    event.stopPropagation();
    suppressClick = false;
  }
}

function jumpTo(target) {
  noTransition.value = true;
  position.value = target;
  nextTick(() => {
    noTransition.value = false;
  });
}

function resetPosition() {
  jumpTo(count.value > 1 ? 1 : 0);
}

function onTransitionEnd(event) {
  if (event.target !== event.currentTarget || event.propertyName !== 'transform') {
    return;
  }

  if (position.value === 0) {
    jumpTo(count.value);
  } else if (position.value === count.value + 1) {
    jumpTo(1);
  }
}

watch(count, () => {
  resetPosition();
  startAutoplay();
}, { immediate: true });

onBeforeUnmount(stopAutoplay);
</script>
