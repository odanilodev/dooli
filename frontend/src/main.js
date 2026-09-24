import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/auth';
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

  if (wasAuthenticated && router.currentRoute.value.name !== 'login') {
    router.push({ name: 'login' });
  }
});

auth.init();

app.mount('#app');
