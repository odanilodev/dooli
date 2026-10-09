import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/auth';
import { useBrandingStore } from './stores/branding';
import { resetUserStores } from './stores/resetUserStores';
import { setUnauthorizedHandler } from './api/http';
import './assets/style.css';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

const auth = useAuthStore(pinia);

setUnauthorizedHandler(() => {
  const wasAuthenticated = auth.isAuthenticated;
  auth.clear();
  resetUserStores();

  if (wasAuthenticated && router.currentRoute.value.name !== 'login') {
    router.push({ name: 'login' });
  }
});

auth.init();
useBrandingStore(pinia).fetch();

app.mount('#app');
