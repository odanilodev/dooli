<template>
  <main class="content">

    <!-- HERO -->
    <HeroCarousel :banners="heroBanners" />

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
          :image="course.image"
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
import { computed } from 'vue';
import HeroCarousel from '../components/HeroCarousel.vue';
import ThumbCard from '../components/ThumbCard.vue';
import CourseCard from '../components/CourseCard.vue';
import RecoCard from '../components/RecoCard.vue';
import { useCoursesStore } from '../stores/courses';
import { useShopStore } from '../stores/store';
import { useBannersStore } from '../stores/banners';
import { paletteAt } from '../data/palette';

const store = useCoursesStore();
const shop = useShopStore();
const banners = useBannersStore();
store.fetchMyCourses();
store.fetchContinueWatching();
shop.fetchProducts({ per_page: 12 });
banners.fetchBanners();

const courses = computed(() => store.cards);
const continueCards = computed(() => store.continueCards);
const heroBanners = computed(() => banners.banners);

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
</script>
