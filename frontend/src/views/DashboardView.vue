<template>
  <main class="content">

    <!-- HERO -->
    <HeroCarousel :banners="heroBanners" />

    <!-- TURMAS -->
    <section v-for="team in homeTeams" :key="team.id" class="section">
      <div v-if="multiTeam" class="section-head">
        <h2 class="section-title">{{ team.name }}</h2>
        <button type="button" class="section-link link-btn" @click="openTeam(team.id)">Ver todos →</button>
      </div>

      <div v-if="team.continueCards.length" class="subsection">
        <h3 class="section-subtitle">Continuar assistindo</h3>
        <div class="card-grid">
          <ThumbCard
            v-for="item in team.continueCards"
            :key="item.id"
            :title="item.title"
            :sub="item.sub"
            :dur="item.dur"
            :pct="item.pct"
            :image="item.image"
            :icon="item.icon"
            :grad="item.grad"
            :to="item.to"
          />
        </div>
      </div>

      <div v-if="!multiTeam" class="section-head">
        <h2 class="section-title">Meus cursos</h2>
        <button type="button" class="section-link link-btn" @click="openTeam(team.id)">Ver todos →</button>
      </div>
      <h3 v-else class="section-subtitle">Cursos</h3>
      <div class="card-grid">
        <CourseCard
          v-for="course in team.courses"
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

      <p v-if="!team.courses.length" class="empty-inline">Nenhum curso nesta turma.</p>
    </section>

    <div class="empty-state" v-if="store.homeLoaded && homeTeams.length === 0">
      <strong>Você ainda não está matriculado em nenhuma turma</strong>
      <span>Assim que for matriculado, seus cursos aparecerão aqui.</span>
    </div>

    <!-- AULAS AO VIVO -->
    <section class="section" v-if="liveUpcoming.length">
      <div class="section-head">
        <h2 class="section-title">Aulas ao vivo</h2>
        <router-link to="/aulas-ao-vivo" class="section-link">Ver todas →</router-link>
      </div>
      <div class="card-grid">
        <ThumbCard
          v-for="(item, index) in liveUpcoming"
          :key="item.id"
          :title="item.title"
          :sub="formatDateTime(item.startsAt)"
          :dur="item.duration ? `${item.duration} min` : ''"
          :image="item.image"
          :icon="paletteAt(index).icon"
          :grad="paletteAt(index).grad"
          :to="{ name: 'aulas-ao-vivo' }"
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
          :image="item.image"
          :to="item.to"
        />
      </div>
    </section>

  </main>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import HeroCarousel from '../components/HeroCarousel.vue';
import ThumbCard from '../components/ThumbCard.vue';
import CourseCard from '../components/CourseCard.vue';
import RecoCard from '../components/RecoCard.vue';
import { useCoursesStore } from '../stores/courses';
import { useStudentTeamsStore } from '../stores/studentTeams';
import { useShopStore } from '../stores/store';
import { useBannersStore } from '../stores/banners';
import { useLiveClassesStore } from '../stores/liveClasses';
import { paletteAt } from '../data/palette';
import { formatDateTime } from '../utils/format';

const router = useRouter();
const store = useCoursesStore();
const studentTeams = useStudentTeamsStore();
const shop = useShopStore();
const banners = useBannersStore();
const live = useLiveClassesStore();

store.fetchHome();
shop.fetchProducts({ per_page: 12 });
banners.fetchBanners();

const homeTeams = computed(() => store.homeSections);
const multiTeam = computed(() => homeTeams.value.length > 1);
const heroBanners = computed(() => banners.banners);
const liveUpcoming = computed(() => live.upcoming.slice(0, 4));

function openTeam(teamId) {
  studentTeams.selectTeam(teamId);
  router.push({ name: 'cursos' });
}

const enrolledCourseIds = computed(() => store.homeCourseIds);
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
        image: product.image_url || product.image || '',
        to: { name: 'loja-produto', params: { slug: product.slug } },
      };
    }),
);
</script>

<style scoped>
.subsection{margin-top:14px;}
.section-subtitle{
  font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;
  color:var(--text-muted);margin:14px 0 10px;
}
.link-btn{background:none;border:none;cursor:pointer;padding:0;}
.empty-inline{margin-top:12px;font-size:13px;color:var(--text-muted);}
</style>
