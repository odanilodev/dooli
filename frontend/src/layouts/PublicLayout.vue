<template>
  <div class="public-shell">
    <header class="public-header">
      <router-link :to="{ name: 'public-loja', params: { companySlug } }" class="public-brand">
        <BrandLogo />
      </router-link>

      <nav class="public-nav">
        <router-link :to="{ name: 'public-loja', params: { companySlug } }">Loja</router-link>
        <router-link v-if="auth.isAuthenticated" :to="{ name: 'public-assinaturas', params: { companySlug } }">Minhas assinaturas</router-link>
        <router-link v-if="auth.isAuthenticated" to="/">Meus cursos</router-link>
        <router-link v-if="!auth.isAuthenticated" :to="{ name: 'public-login', params: { companySlug } }">Entrar</router-link>
        <button v-else type="button" class="public-link-btn" @click="logout">Sair</button>
      </nav>
    </header>

    <p v-if="storefront.error && !storefront.company" class="public-error">
      {{ storefront.error }}
    </p>

    <router-view v-else />
  </div>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import BrandLogo from '../components/BrandLogo.vue';
import { useAuthStore } from '../stores/auth';
import { useStorefrontStore } from '../stores/storefront';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const storefront = useStorefrontStore();

const companySlug = computed(() => String(route.params.companySlug || ''));

watch(
  companySlug,
  async (slug) => {
    if (!slug) {
      return;
    }

    try {
      await storefront.loadCompany(slug);
    } catch (e) {
      /* the error is rendered above */
    }
  },
  { immediate: true },
);

async function logout() {
  await auth.logout();
  await router.push({ name: 'public-loja', params: { companySlug: companySlug.value } });
}
</script>

<style scoped>
.public-shell{min-height:100vh;background:var(--bg);color:var(--text-primary);}
.public-header{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:18px clamp(18px,5vw,64px);border-bottom:1px solid var(--border);background:var(--bg-card);position:sticky;top:0;z-index:20;}
.public-brand{display:flex;align-items:center;text-decoration:none;color:inherit;}
.public-nav{display:flex;align-items:center;gap:18px;font-size:13.5px;font-weight:700;}
.public-nav a{color:var(--text-secondary);text-decoration:none;}
.public-nav a.router-link-active,.public-nav a:hover{color:var(--accent);}
.public-link-btn{border:0;background:none;color:var(--text-secondary);font:inherit;font-weight:700;cursor:pointer;padding:0;}
.public-error{max-width:620px;margin:80px auto;padding:24px;text-align:center;border:1px solid var(--border);border-radius:16px;background:var(--bg-card);}
@media(max-width:640px){.public-header{align-items:flex-start;flex-direction:column;}.public-nav{flex-wrap:wrap;}}
</style>
