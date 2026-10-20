<template>
  <div class="public-shell">
    <header class="public-header">
      <div class="public-header-inner">
        <router-link :to="{ name: 'public-loja', params: { companySlug } }" class="public-brand">
          <BrandLogo />
        </router-link>

        <nav class="public-nav" aria-label="Navegação da loja">
          <router-link :to="{ name: 'public-loja', params: { companySlug } }">Loja</router-link>
          <router-link v-if="auth.isAuthenticated" :to="{ name: 'public-assinaturas', params: { companySlug } }">Minhas assinaturas</router-link>
          <router-link v-if="auth.isAuthenticated" to="/">Meus cursos</router-link>
          <router-link v-if="!auth.isAuthenticated" :to="{ name: 'public-login', params: { companySlug } }">Entrar</router-link>
          <button v-else type="button" class="public-link-btn" @click="logout">Sair</button>

          <button class="theme-toggle" type="button" aria-label="Alternar tema" @click="toggleTheme">
            <svg class="icon sun" width="13" height="13" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2 12h2M20 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/></svg>
            <svg class="icon moon" width="13" height="13" viewBox="0 0 24 24" stroke-width="2"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z"/></svg>
            <span class="knob"></span>
          </button>
        </nav>
      </div>
    </header>

    <div class="public-body">
      <p v-if="storefront.error && !storefront.company" class="public-error">
        {{ storefront.error }}
      </p>

      <router-view v-else />
    </div>

    <footer v-if="storefront.company" class="public-footer">
      <div class="public-footer-inner">
        <span>{{ storefront.company.name }}</span>
        <span>Pagamento processado com segurança.</span>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import BrandLogo from '../components/BrandLogo.vue';
import { useTheme } from '../composables/useTheme';
import { useAuthStore } from '../stores/auth';
import { useStorefrontStore } from '../stores/storefront';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const storefront = useStorefrontStore();
const { toggleTheme } = useTheme();

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
.public-shell{
  min-height:100vh;display:flex;flex-direction:column;
  background:
    radial-gradient(circle at 0% 0%,color-mix(in srgb,var(--accent) 7%,transparent),transparent 32rem),
    var(--bg-page);
  color:var(--text-primary);
}
.public-header{
  position:sticky;top:0;z-index:20;
  border-bottom:1px solid var(--border);
  background:color-mix(in srgb,var(--bg-card) 92%,transparent);
  backdrop-filter:blur(14px);
}
.public-header-inner{
  max-width:1240px;margin:0 auto;padding:16px clamp(18px,5vw,64px);
  display:flex;align-items:center;justify-content:space-between;gap:24px;
}
.public-brand{display:flex;align-items:center;text-decoration:none;color:inherit;min-width:0;}
.public-nav{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700;flex-wrap:wrap;justify-content:flex-end;}
.public-nav > a,.public-link-btn{
  padding:8px 10px;border-radius:9px;color:var(--text-secondary);
  text-decoration:none;transition:background .15s ease,color .15s ease;
}
.public-nav > a.router-link-active,.public-nav > a:hover{background:var(--bg-card-alt);color:var(--text-primary);}
.public-link-btn{border:0;background:none;font:inherit;font-weight:700;cursor:pointer;}
.public-link-btn:hover{background:var(--bg-card-alt);color:var(--text-primary);}
.public-nav .theme-toggle{margin-left:6px;}
.public-body{flex:1;}
.public-error{max-width:620px;margin:80px auto;padding:24px;text-align:center;border:1px solid var(--border);border-radius:16px;background:var(--bg-card);}
.public-footer{border-top:1px solid var(--border);background:var(--bg-card);}
.public-footer-inner{
  max-width:1240px;margin:0 auto;padding:18px clamp(18px,5vw,64px);
  display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
  color:var(--text-muted);font-size:12px;
}
@media(max-width:720px){
  .public-header-inner{align-items:flex-start;flex-direction:column;gap:14px;}
  .public-nav{width:100%;justify-content:flex-start;}
  .public-nav .theme-toggle{margin-left:auto;}
}
@media(max-width:480px){
  .public-nav > a,.public-link-btn{padding:7px 8px;font-size:12.5px;}
}
</style>
