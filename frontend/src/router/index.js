import { createRouter, createWebHashHistory } from 'vue-router';

import { useAuthStore } from '../stores/auth';

import AppLayout from '../layouts/AppLayout.vue';
import AuthLayout from '../layouts/AuthLayout.vue';

import DashboardView from '../views/DashboardView.vue';
import CoursesView from '../views/CoursesView.vue';
import LessonView from '../views/LessonView.vue';
import StoreView from '../views/StoreView.vue';
import StoreProductView from '../views/StoreProductView.vue';
import ProfileView from '../views/ProfileView.vue';
import SettingsView from '../views/SettingsView.vue';
import AdminVideoPlatformsView from '../views/AdminVideoPlatformsView.vue';
import AdminContentIndexView from '../views/AdminContentIndexView.vue';
import AdminCourseWizardView from '../views/AdminCourseWizardView.vue';
import AdminContentView from '../views/AdminContentView.vue';
import AdminTeamsView from '../views/AdminTeamsView.vue';
import AdminVideosView from '../views/AdminVideosView.vue';
import AdminDocumentsView from '../views/AdminDocumentsView.vue';
import AdminAttachmentsView from '../views/AdminAttachmentsView.vue';
import LoginView from '../views/LoginView.vue';
import RegisterView from '../views/RegisterView.vue';
import ForgotPasswordView from '../views/ForgotPasswordView.vue';
import ResetPasswordView from '../views/ResetPasswordView.vue';
import NotFoundView from '../views/NotFoundView.vue';

const routes = [
  {
    path: '/',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'dashboard', component: DashboardView, meta: { title: 'Início' } },
      { path: 'cursos', name: 'cursos', component: CoursesView, meta: { title: 'Meus cursos', searchPlaceholder: 'Buscar nos meus cursos...' } },
      { path: 'cursos/:courseId', name: 'curso', component: LessonView, meta: { title: 'Curso', searchPlaceholder: 'Buscar no curso...' } },
      { path: 'cursos/:courseId/aula/:lessonId', name: 'aula', component: LessonView, meta: { title: 'Aula', searchPlaceholder: 'Buscar no curso...' } },
      { path: 'loja', name: 'loja', component: StoreView, meta: { title: 'Loja', searchPlaceholder: 'Buscar na loja...' } },
      { path: 'loja/:slug', name: 'loja-produto', component: StoreProductView, meta: { title: 'Produto', searchPlaceholder: 'Buscar na loja...' } },
      { path: 'perfil', name: 'perfil', component: ProfileView, meta: { title: 'Meu perfil' } },
      { path: 'configuracoes', name: 'configuracoes', component: SettingsView, meta: { title: 'Configurações' } },
      { path: 'admin/video-platforms', name: 'admin-video-platforms', component: AdminVideoPlatformsView, meta: { title: 'Plataformas de vídeo', requiresAdmin: true } },
      { path: 'admin/conteudo', name: 'admin-conteudo', component: AdminContentIndexView, meta: { title: 'Conteúdo (admin)', requiresAdmin: true } },
      { path: 'admin/conteudo/novo', name: 'admin-conteudo-novo', component: AdminCourseWizardView, meta: { title: 'Novo curso (admin)', requiresAdmin: true } },
      { path: 'admin/conteudo/:courseId', name: 'admin-conteudo-curso', component: AdminContentView, meta: { title: 'Curso (admin)', requiresAdmin: true } },
      { path: 'admin/turmas', name: 'admin-turmas', component: AdminTeamsView, meta: { title: 'Turmas (admin)', requiresAdmin: true } },
      { path: 'admin/videos', name: 'admin-videos', component: AdminVideosView, meta: { title: 'Vídeos (admin)', requiresAdmin: true } },
      { path: 'admin/documentos', name: 'admin-documentos', component: AdminDocumentsView, meta: { title: 'Documentos (admin)', requiresAdmin: true } },
      { path: 'admin/anexos', name: 'admin-anexos', component: AdminAttachmentsView, meta: { title: 'Anexos (admin)', requiresAdmin: true } },
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
      {
        path: 'recuperar-senha',
        name: 'recuperar-senha',
        component: ForgotPasswordView,
        meta: {
          title: 'Recuperar senha',
          quote: 'Recupere o acesso à sua conta e continue evoluindo com',
          quoteHighlight: 'disciplina e estratégia no mercado financeiro.',
        },
      },
      {
        path: 'reset-password',
        name: 'reset-password',
        component: ResetPasswordView,
        meta: {
          title: 'Redefinir senha',
          quote: 'Crie uma nova senha e retome seus estudos com',
          quoteHighlight: 'segurança e tranquilidade.',
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

  if (to.meta?.requiresAdmin && !auth.isAdmin) {
    return { name: 'dashboard' };
  }

  return true;
});

router.afterEach((to) => {
  document.title = to.meta?.title ? `Delta Class — ${to.meta.title}` : 'Delta Class';
});

export default router;
