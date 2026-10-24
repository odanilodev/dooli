<template>
  <div class="public-shell">
    <header class="public-header">
      <div class="public-header-inner">
        <router-link :to="{ name: 'public-loja', params: { companySlug } }" class="public-brand">
          <BrandLogo />
        </router-link>

        <nav v-if="auth.isAuthenticated" class="public-nav" aria-label="Navegação da loja">
          <router-link :to="{ name: 'public-assinaturas', params: { companySlug } }">Minhas assinaturas</router-link>
          <router-link to="/">Meus cursos</router-link>
        </nav>

        <div class="public-actions">
          <button class="public-icon-btn" type="button" aria-label="Buscar produtos" @click="focusSearch">
            <svg class="icon" width="17" height="17" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          </button>

          <button class="theme-toggle" type="button" aria-label="Alternar tema" @click="toggleTheme">
            <svg class="icon sun" width="13" height="13" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2 12h2M20 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/></svg>
            <svg class="icon moon" width="13" height="13" viewBox="0 0 24 24" stroke-width="2"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z"/></svg>
            <span class="knob"></span>
          </button>

          <template v-if="!auth.isAuthenticated">
            <router-link :to="{ name: 'public-login', params: { companySlug } }" class="public-signin">Entrar</router-link>
            <button class="public-header-cta" type="button" @click="focusSearch">Explorar cursos</button>
          </template>

          <div v-else ref="userMenuRef" class="user-menu public-user-menu" :class="{ open: userMenuOpen }">
            <button
              type="button"
              class="user-trigger"
              aria-haspopup="true"
              :aria-expanded="userMenuOpen"
              aria-label="Menu do usuário"
              @click="userMenuOpen = !userMenuOpen"
            >
              <span class="avatar">{{ auth.initials }}</span>
              <span class="user-text">
                <span class="user-name">{{ auth.user?.name || 'Visitante' }}</span>
                <span class="user-role">{{ auth.role || 'Aluno' }}</span>
              </span>
              <svg class="icon" width="15" height="15" viewBox="0 0 24 24" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="user-dropdown">
              <router-link :to="{ name: 'perfil' }" @click="userMenuOpen = false">
                <svg class="icon" width="15" height="15" viewBox="0 0 24 24" stroke-width="1.8"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.9 3.1-7 7-7s7 3.1 7 7"/></svg>
                Meu perfil
              </router-link>
              <router-link to="/" @click="userMenuOpen = false">
                <svg class="icon" width="15" height="15" viewBox="0 0 24 24" stroke-width="1.8"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/></svg>
                Meus cursos
              </router-link>
              <router-link :to="{ name: 'public-assinaturas', params: { companySlug } }" @click="userMenuOpen = false">
                <svg class="icon" width="15" height="15" viewBox="0 0 24 24" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>
                Minhas assinaturas
              </router-link>
              <a href="#" @click.prevent="logout">
                <svg class="icon" width="15" height="15" viewBox="0 0 24 24" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                Sair
              </a>
            </div>
          </div>
        </div>
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
        <span>{{ footerNote }}</span>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import BrandLogo from '../components/BrandLogo.vue';
import { useTheme } from '../composables/useTheme';
import { useAuthStore } from '../stores/auth';
import { resetUserStores } from '../stores/resetUserStores';
import { useStorefrontStore } from '../stores/storefront';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const storefront = useStorefrontStore();
const { toggleTheme } = useTheme();

const userMenuOpen = ref(false);
const userMenuRef = ref(null);

const companySlug = computed(() => String(route.params.companySlug || ''));
const footerNote = computed(
  () => storefront.company?.storefront?.settings?.footer?.note || 'Pagamento processado com segurança.',
);

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

async function focusSearch() {
  await router.push({
    name: 'public-loja',
    params: { companySlug: companySlug.value },
    hash: '#catalogo',
  });
  await nextTick();
  document.getElementById('store-search')?.focus();
}

async function logout() {
  userMenuOpen.value = false;
  await auth.logout();
  resetUserStores();
  await router.push({ name: 'public-loja', params: { companySlug: companySlug.value } });
}

function onClickOutside(event) {
  if (userMenuRef.value && !userMenuRef.value.contains(event.target)) {
    userMenuOpen.value = false;
  }
}

onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>

<style scoped>
.public-shell{
  --public-max:1800px;
  --public-gutter:clamp(20px,3.6vw,64px);
  min-height:100vh;display:flex;flex-direction:column;
  background:var(--bg-page);
  color:var(--text-primary);
}
.public-header{
  position:sticky;top:0;z-index:20;
  border-bottom:1px solid color-mix(in srgb,var(--border) 72%,transparent);
  background:color-mix(in srgb,var(--bg-card) 86%,transparent);
  backdrop-filter:blur(18px);
}
.public-header-inner{
  width:100%;max-width:var(--public-max);margin:0 auto;padding:0 var(--public-gutter);
  min-height:76px;display:flex;align-items:center;justify-content:space-between;gap:28px;
}
.public-brand{display:flex;align-items:center;text-decoration:none;color:inherit;min-width:0;}
.public-nav{
  display:flex;align-items:center;gap:6px;margin-right:auto;
  font-size:12.5px;font-weight:700;flex-wrap:wrap;justify-content:flex-start;
}
.public-nav > a{
  padding:8px 11px;border-radius:8px;color:var(--text-secondary);
  text-decoration:none;transition:color .15s ease,background .15s ease;
}
.public-nav > a.router-link-active{color:var(--text-primary);}
.public-nav > a:hover{color:var(--text-primary);background:var(--bg-card-alt);}
.public-actions{display:flex;align-items:center;gap:10px;flex-shrink:0;}
.public-user-menu{flex-shrink:0;}
.public-icon-btn{
  display:flex;align-items:center;justify-content:center;width:36px;height:36px;
  border:0;border-radius:8px;background:transparent;color:var(--text-muted);cursor:pointer;
  transition:background .15s ease,color .15s ease;
}
.public-icon-btn:hover{background:var(--bg-card-alt);color:var(--text-primary);}
.public-signin{color:var(--text-secondary);font-size:12.5px;font-weight:700;padding:8px 6px;}
.public-signin:hover{color:var(--text-primary);}
.public-header-cta{
  padding:9px 15px;border:0;border-radius:8px;background:var(--accent-grad);color:var(--accent-ink);
  font:inherit;font-size:12px;font-weight:800;cursor:pointer;white-space:nowrap;
  transition:filter .15s ease,transform .15s ease;
}
.public-header-cta:hover{filter:brightness(1.04);transform:translateY(-1px);}
.public-body{flex:1;width:100%;}
.public-error{max-width:720px;margin:80px auto;padding:28px;text-align:center;border:1px solid var(--border);border-radius:22px;background:var(--bg-card);}
.public-footer{border-top:1px solid color-mix(in srgb,var(--border) 72%,transparent);background:color-mix(in srgb,var(--bg-card) 55%,var(--bg-page));}
.public-footer-inner{
  width:100%;max-width:var(--public-max);margin:0 auto;padding:26px var(--public-gutter);
  display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
  color:var(--text-muted);font-size:12.5px;
}
@media(max-width:1000px){
  .public-header-inner{flex-wrap:wrap;gap:8px 16px;padding-top:10px;padding-bottom:8px;min-height:0;}
  .public-brand{flex:1;}
  .public-actions{order:2;}
  .public-nav{
    order:3;width:100%;justify-content:flex-start;gap:2px;
    padding-top:6px;border-top:1px solid color-mix(in srgb,var(--border) 68%,transparent);
  }
  .public-nav > a{padding:7px 9px;font-size:12px;}
  .public-footer-inner{padding-top:22px;padding-bottom:22px;}
}
@media(max-width:760px){
  .public-user-menu .user-text{display:none;}
}
@media(max-width:560px){
  .public-header-cta{display:none;}
  .public-nav{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;}
  .public-nav::-webkit-scrollbar{display:none;}
  .public-actions{gap:4px;}
  .public-icon-btn{width:32px;height:32px;}
  .public-user-menu .user-trigger{padding:2px;gap:6px;}
  .public-user-menu .user-dropdown{right:-4px;}
}
@media(max-width:480px){
  .public-nav > a{padding:7px 8px;font-size:12.5px;}
}
</style>
