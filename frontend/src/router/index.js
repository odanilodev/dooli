import { createRouter, createWebHashHistory } from 'vue-router';

import { useAuthStore } from '../stores/auth';

import AppLayout from '../layouts/AppLayout.vue';
import AuthLayout from '../layouts/AuthLayout.vue';

import HomeView from '../views/HomeView.vue';
import CoursesView from '../views/CoursesView.vue';
import CertificatesView from '../views/CertificatesView.vue';
import CertificatePrintView from '../views/CertificatePrintView.vue';
import LiveClassesView from '../views/LiveClassesView.vue';
import LessonView from '../views/LessonView.vue';
import StoreView from '../views/StoreView.vue';
import StoreProductView from '../views/StoreProductView.vue';
import ProfileView from '../views/ProfileView.vue';
import SettingsView from '../views/SettingsView.vue';
import AdminVideoPlatformsView from '../views/AdminVideoPlatformsView.vue';
import AdminContentIndexView from '../views/AdminContentIndexView.vue';
import AdminModulesView from '../views/AdminModulesView.vue';
import AdminContentView from '../views/AdminContentView.vue';
import AdminTeamsView from '../views/AdminTeamsView.vue';
import AdminVideosView from '../views/AdminVideosView.vue';
import AdminDocumentsView from '../views/AdminDocumentsView.vue';
import AdminAttachmentsView from '../views/AdminAttachmentsView.vue';
import AdminBannersView from '../views/AdminBannersView.vue';
import AdminCertificatesView from '../views/AdminCertificatesView.vue';
import AdminCommentsView from '../views/AdminCommentsView.vue';
import AdminProductsView from '../views/AdminProductsView.vue';
import AdminLiveClassesView from '../views/AdminLiveClassesView.vue';
import AdminReportsView from '../views/AdminReportsView.vue';
import AdminUsersView from '../views/AdminUsersView.vue';
import AdminUserTypesView from '../views/AdminUserTypesView.vue';
import AdminMenusView from '../views/AdminMenusView.vue';
import AdminCompaniesView from '../views/AdminCompaniesView.vue';
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
      { path: '', name: 'dashboard', component: HomeView, meta: { title: 'Início' } },
      { path: 'cursos', name: 'cursos', component: CoursesView, meta: { title: 'Meus cursos', searchPlaceholder: 'Buscar nos meus cursos...' } },
      { path: 'cursos/:courseId', name: 'curso', component: LessonView, meta: { title: 'Curso', searchPlaceholder: 'Buscar no curso...' } },
      { path: 'cursos/:courseId/aula/:lessonId', name: 'aula', component: LessonView, meta: { title: 'Aula', searchPlaceholder: 'Buscar no curso...' } },
      { path: 'aulas-ao-vivo', name: 'aulas-ao-vivo', component: LiveClassesView, meta: { title: 'Aulas ao vivo' } },
      { path: 'certificados', name: 'certs', component: CertificatesView, meta: { title: 'Meus certificados' } },
      { path: 'certificados/:id', name: 'certificado', component: CertificatePrintView, meta: { title: 'Certificado' } },
      { path: 'loja', name: 'loja', component: StoreView, meta: { title: 'Loja', searchPlaceholder: 'Buscar na loja...' } },
      { path: 'loja/:slug', name: 'loja-produto', component: StoreProductView, meta: { title: 'Produto', searchPlaceholder: 'Buscar na loja...' } },
      { path: 'perfil', name: 'perfil', component: ProfileView, meta: { title: 'Meu perfil' } },
      { path: 'configuracoes', name: 'configuracoes', component: SettingsView, meta: { title: 'Configurações' } },
      { path: 'admin/video-platforms', name: 'admin-video-platforms', component: AdminVideoPlatformsView, meta: { title: 'Plataformas de vídeo', requiresAdminMenu: true } },
      { path: 'admin/cursos', name: 'admin-cursos', component: AdminContentIndexView, meta: { title: 'Cursos (admin)', requiresAdminMenu: true } },
      { path: 'admin/cursos/novo', redirect: { name: 'admin-cursos' } },
      { path: 'admin/cursos/:courseId', name: 'admin-curso', component: AdminContentView, meta: { title: 'Curso (admin)', requiresAdminMenu: true } },
      { path: 'admin/modulos', name: 'admin-modulos', component: AdminModulesView, meta: { title: 'Módulos (admin)', requiresAdminMenu: true } },
      { path: 'admin/aulas-ao-vivo', name: 'admin-live-classes', component: AdminLiveClassesView, meta: { title: 'Aulas ao vivo (admin)', requiresAdminMenu: true } },
      { path: 'admin/turmas', name: 'admin-turmas', component: AdminTeamsView, meta: { title: 'Turmas (admin)', requiresAdminMenu: true } },
      { path: 'admin/relatorios', name: 'admin-relatorios', component: AdminReportsView, meta: { title: 'Relatórios (admin)', requiresAdminMenu: true } },
      { path: 'admin/videos', name: 'admin-videos', component: AdminVideosView, meta: { title: 'Vídeos (admin)', requiresAdminMenu: true } },
      { path: 'admin/documentos', name: 'admin-documentos', component: AdminDocumentsView, meta: { title: 'Documentos (admin)', requiresAdminMenu: true } },
      { path: 'admin/anexos', name: 'admin-anexos', component: AdminAttachmentsView, meta: { title: 'Anexos (admin)', requiresAdminMenu: true } },
      { path: 'admin/banners', name: 'admin-banners', component: AdminBannersView, meta: { title: 'Banners (admin)', requiresAdminMenu: true } },
      { path: 'admin/certificados', name: 'admin-certificados', component: AdminCertificatesView, meta: { title: 'Certificados (admin)', requiresAdminMenu: true } },
      { path: 'admin/comentarios', name: 'admin-comentarios', component: AdminCommentsView, meta: { title: 'Comentários (admin)', requiresAdminMenu: true } },
      { path: 'admin/produtos', name: 'admin-produtos', component: AdminProductsView, meta: { title: 'Produtos da loja (admin)', requiresAdminMenu: true } },
      { path: 'admin/usuarios', name: 'admin-usuarios', component: AdminUsersView, meta: { title: 'Usuários (admin)', requiresAdmin: true } },
      { path: 'admin/tipos-de-usuario', name: 'admin-tipos-de-usuario', component: AdminUserTypesView, meta: { title: 'Tipos de usuário (admin)', requiresAdmin: true } },
      { path: 'admin/menus', name: 'admin-menus', component: AdminMenusView, meta: { title: 'Menus (admin)', requiresSuperAdmin: true } },
      { path: 'admin/empresas', name: 'admin-empresas', component: AdminCompaniesView, meta: { title: 'Empresas (admin)', requiresSuperAdmin: true } },
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

  // A platform super admin only manages companies and the global menu config.
  const SUPER_ADMIN_ROUTES = new Set(['admin-empresas', 'admin-menus']);

  if (
    auth.isSuperAdmin &&
    typeof to.name === 'string' &&
    ((to.name.startsWith('admin-') && !SUPER_ADMIN_ROUTES.has(to.name)) || to.name === 'dashboard')
  ) {
    return { name: 'admin-empresas' };
  }

  if (to.meta?.requiresAdmin && !auth.isAdmin) {
    return { name: 'dashboard' };
  }

  if (to.meta?.requiresAdminMenu && !auth.canAccessAdmin) {
    return { name: 'dashboard' };
  }

  if (to.meta?.requiresSuperAdmin && !auth.isSuperAdmin) {
    return { name: 'dashboard' };
  }

  return true;
});

router.afterEach((to) => {
  document.title = to.meta?.title ? `Delta Class — ${to.meta.title}` : 'Delta Class';
});

export default router;
