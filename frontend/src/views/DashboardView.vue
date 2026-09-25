<template>
  <main class="content">

    <!-- HERO -->
    <section class="hero">
      <svg class="hero-illustration" width="440" height="290" viewBox="0 0 440 290" fill="none">
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

      <span class="hero-eyebrow">CURSO EM DESTAQUE</span>
      <h1 class="hero-title">Do Zero ao Trader <em>Consistente</em></h1>
      <p class="hero-desc">Aprenda estratégias, gestão de risco e psicologia para evoluir no mercado financeiro.</p>
      <div class="hero-actions">
        <router-link to="/cursos" class="btn btn-primary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5l12 7-12 7V5z"/></svg>
          Continuar assistindo
        </router-link>
        <router-link to="/cursos" class="btn btn-ghost-light">Ver detalhes</router-link>
      </div>
      <span class="hero-quote">&quot;Disciplina hoje, liberdade amanhã.&quot;</span>

      <button class="hero-nav prev" aria-label="Anterior" @click="prevSlide">
        <svg class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2.2"><path d="M15 6l-6 6 6 6"/></svg>
      </button>
      <button class="hero-nav next" aria-label="Próximo" @click="nextSlide">
        <svg class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2.2"><path d="M9 6l6 6-6 6"/></svg>
      </button>
      <div class="hero-dots">
        <span v-for="n in 4" :key="n" :class="{ active: activeSlide === n - 1 }" @click="activeSlide = n - 1"></span>
      </div>
    </section>

    <!-- CONTINUAR ASSISTINDO -->
    <section class="section" v-if="continueCards.length">
      <div class="section-head">
        <h2 class="section-title">Continuar assistindo</h2>
        <router-link to="/cursos" class="section-link">Ver todos →</router-link>
      </div>
      <div class="card-grid">
        <ThumbCard
          v-for="item in continueCards"
          :key="item.id"
          :title="item.title"
          :sub="item.sub"
          :dur="item.dur"
          :pct="item.pct"
          :icon="item.icon"
          :grad="item.grad"
          :to="item.to"
        />
      </div>
    </section>

    <!-- MEUS CURSOS -->
    <section class="section">
      <div class="section-head">
        <h2 class="section-title">Meus cursos</h2>
        <router-link to="/cursos" class="section-link">Ver todos →</router-link>
      </div>
      <div class="card-grid">
        <CourseCard
          v-for="course in courses"
          :key="course.id"
          :title="course.title"
          :count="course.count"
          :pct="course.pct"
          :badge="course.badge"
          :icon="course.icon"
          :grad="course.grad"
          :to="{ name: 'curso', params: { courseId: course.id } }"
        />
      </div>
    </section>

    <!-- RECOMENDADO -->
    <section class="section" v-if="recommendedProducts.length">
      <div class="section-head">
        <h2 class="section-title">Recomendado para você</h2>
        <router-link to="/loja" class="section-link">Ir para a loja →</router-link>
      </div>
      <div class="card-grid">
        <RecoCard
          v-for="item in recommendedProducts"
          :key="item.id"
          :title="item.title"
          :icon="item.icon"
          :grad="item.grad"
          :to="item.to"
        />
      </div>
    </section>

  </main>
</template>

<script setup>
import { computed, ref } from 'vue';
import ThumbCard from '../components/ThumbCard.vue';
import CourseCard from '../components/CourseCard.vue';
import RecoCard from '../components/RecoCard.vue';
import { useCoursesStore } from '../stores/courses';
import { useShopStore } from '../stores/store';
import { paletteAt } from '../data/palette';

const store = useCoursesStore();
const shop = useShopStore();
store.fetchMyCourses();
store.fetchContinueWatching();
shop.fetchProducts({ per_page: 12 });

const courses = computed(() => store.cards);
const continueCards = computed(() => store.continueCards);

const enrolledCourseIds = computed(() => new Set(store.items.map((course) => course.id)));
const recommendedProducts = computed(() =>
  shop.products
    .filter((product) => !product.course_id || !enrolledCourseIds.value.has(product.course_id))
    .slice(0, 5)
    .map((product, index) => {
      const palette = paletteAt(index);

      return {
        id: product.id,
        title: product.title,
        icon: palette.icon,
        grad: palette.grad,
        to: { name: 'loja-produto', params: { slug: product.slug } },
      };
    }),
);

const activeSlide = ref(0);
function nextSlide() {
  activeSlide.value = (activeSlide.value + 1) % 4;
}
function prevSlide() {
  activeSlide.value = (activeSlide.value + 3) % 4;
}
</script>
