import { createRouter, createWebHashHistory } from 'vue-router';

import { useAuthStore } from '../stores/auth';

import AppLayout from '../layouts/AppLayout.vue';
import AuthLayout from '../layouts/AuthLayout.vue';

import DashboardView from '../views/DashboardView.vue';
import CoursesView from '../views/CoursesView.vue';
import LessonView from '../views/LessonView.vue';
import TurmasView from '../views/TurmasView.vue';
import ProfileView from '../views/ProfileView.vue';
import SettingsView from '../views/SettingsView.vue';
import LoginView from '../views/LoginView.vue';
import RegisterView from '../views/RegisterView.vue';
import NotFoundView from '../views/NotFoundView.vue';

const routes = [
  {
    path: '/',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'dashboard', component: DashboardView, meta: { title: 'Início' } },
      { path: 'cursos', name: 'cursos', component: CoursesView, meta: { title: 'Meus cursos', searchPlaceholder: 'Buscar nos meus cursos...' } },
      { path: 'aula/:id?', name: 'aula', component: LessonView, meta: { title: 'Indicadores técnicos', searchPlaceholder: 'Buscar em meus cursos...' } },
      { path: 'turmas', name: 'turmas', component: TurmasView, meta: { title: 'Minhas turmas' } },
      { path: 'perfil', name: 'perfil', component: ProfileView, meta: { title: 'Meu perfil' } },
      { path: 'configuracoes', name: 'configuracoes', component: SettingsView, meta: { title: 'Configurações' } },
      { path: ':pathMatch(.*)*', name: 'not-found', component: NotFoundView, meta: { title: 'Página não encontrada' } },
    ],
  },
  {
    path: '/',
    component: AuthLayout,
    meta: { guestOnly: true },
    children: [
      {
        path: 'login',
        name: 'login',
        component: LoginView,
        meta: {
          title: 'Entrar',
          quote: 'Aprenda a operar com estratégia, gerencie seu risco e conquiste',
          quoteHighlight: 'resultados consistentes no mercado financeiro.',
        },
      },
      {
        path: 'cadastro',
        name: 'cadastro',
        component: RegisterView,
        meta: {
          title: 'Criar conta',
          quote: 'Junte-se a milhares de alunos que já estão evoluindo com',
          quoteHighlight: 'disciplina e estratégia no mercado financeiro.',
        },
      },
    ],
  },
];

const router = createRouter({
  history: createWebHashHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 };
  },
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();

  if (!auth.initialized) {
    await auth.init();
  }

  if (to.meta?.requiresAuth && !auth.isAuthenticated) {
    return {
      name: 'login',
      query: to.fullPath !== '/' ? { redirect: to.fullPath } : undefined,
    };
  }

  if (to.meta?.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' };
  }

  return true;
});

router.afterEach((to) => {
  document.title = to.meta?.title ? `Delta Class — ${to.meta.title}` : 'Delta Class';
});

export default router;
