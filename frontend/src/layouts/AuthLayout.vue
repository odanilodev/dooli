<template>
  <div class="auth-shell">
    <div class="auth-visual">
      <span class="auth-visual-glow"></span>
      <router-link :to="brandLink" class="brand" style="position:relative;">
        <BrandLogo variant="auth" />
      </router-link>
      <p class="auth-visual-quote">{{ route.meta.quote }} <span>{{ route.meta.quoteHighlight }}</span></p>
      <span class="auth-visual-foot">© 2026 Delta Class. Todos os direitos reservados.</span>
    </div>

    <div class="auth-form-side">
      <router-view />
    </div>
  </div>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import BrandLogo from '../components/BrandLogo.vue';
import { useTenant } from '../composables/useTenant';
import { useStorefrontStore } from '../stores/storefront';

const route = useRoute();
const storefront = useStorefrontStore();
const { isTenant, companySlug, publicRoute } = useTenant();

const brandLink = computed(() => (isTenant.value
  ? publicRoute('public-loja')
  : { name: 'login' }));

watch(companySlug, async (slug) => {
  if (slug) {
    try {
      await storefront.loadCompany(slug);
    } catch (e) {
      /* the form still renders; API calls will surface the error */
    }
  }
}, { immediate: true });
</script>
