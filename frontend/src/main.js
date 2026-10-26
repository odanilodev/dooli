import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/auth';
import { useTenantStore } from './stores/tenant';
import { resetUserStores } from './stores/resetUserStores';
import { setUnauthorizedHandler } from './api/http';
import './assets/style.css';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

const auth = useAuthStore(pinia);
const tenant = useTenantStore(pinia);

setUnauthorizedHandler(() => {
  const wasAuthenticated = auth.isAuthenticated;
  auth.clear();
  resetUserStores();

  if (!wasAuthenticated) {
    return;
  }

  const current = router.currentRoute.value;
  const login = typeof current.params.companySlug === 'string'
    ? { name: 'legacy-login', params: { companySlug: current.params.companySlug } }
    : tenant.isDomainMode
      ? { name: 'public-login' }
      : { name: 'login' };

  if (current.name !== login.name) {
    router.push(login);
  }
});

auth.init();

app.mount('#app');
