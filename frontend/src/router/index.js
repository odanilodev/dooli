import { createRouter, createWebHistory } from 'vue-router';

import { useAuthStore } from '../stores/auth';
import { useTenantStore } from '../stores/tenant';

import AppLayout from '../layouts/AppLayout.vue';
import AuthLayout from '../layouts/AuthLayout.vue';
import PublicLayout from '../layouts/PublicLayout.vue';

import HomeView from '../views/HomeView.vue';
import CoursesView from '../views/CoursesView.vue';
import CertificatesView from '../views/CertificatesView.vue';
import CertificatePrintView from '../views/CertificatePrintView.vue';
import LiveClassesView from '../views/LiveClassesView.vue';
import LessonView from '../views/LessonView.vue';
import StoreView from '../views/StoreView.vue';
import StoreProductView from '../views/StoreProductView.vue';
import RobotsView from '../views/RobotsView.vue';
import RobotView from '../views/RobotView.vue';
import PublicStoreView from '../views/PublicStoreView.vue';
import PublicProductView from '../views/PublicProductView.vue';
import PublicCheckoutView from '../views/PublicCheckoutView.vue';
import PublicOrderView from '../views/PublicOrderView.vue';
import PublicSubscriptionsView from '../views/PublicSubscriptionsView.vue';
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
import AdminStoreView from '../views/AdminStoreView.vue';
import AdminLiveClassesView from '../views/AdminLiveClassesView.vue';
import AdminReportsView from '../views/AdminReportsView.vue';
import AdminUsersView from '../views/AdminUsersView.vue';
import AdminUserTypesView from '../views/AdminUserTypesView.vue';
import AdminMenusView from '../views/AdminMenusView.vue';
import AdminCompaniesView from '../views/AdminCompaniesView.vue';
import AdminGatewaysView from '../views/AdminGatewaysView.vue';
import AdminOrdersView from '../views/AdminOrdersView.vue';
import AdminSubscriptionsView from '../views/AdminSubscriptionsView.vue';
import LoginView from '../views/LoginView.vue';
import RegisterView from '../views/RegisterView.vue';
import ForgotPasswordView from '../views/ForgotPasswordView.vue';
import ResetPasswordView from '../views/ResetPasswordView.vue';
import NotFoundView from '../views/NotFoundView.vue';

const publicAuthMeta = {
  quote: 'Aprenda com quem entende de mercado e evolua com',
  quoteHighlight: 'estratégia, disciplina e consistência.',
};

const routes = [
  /*
  | Public storefront on the tenant domain (clean URLs)
  */
  {
    path: '/',
    component: PublicLayout,
    children: [
      { path: '', name: 'public-loja', component: PublicStoreView, meta: { title: 'Loja' } },
      {
        path: 'produto/:productSlug',
        name: 'public-produto',
        component: PublicProductView,
        meta: { title: 'Produto' },
      },
      {
        path: 'checkout/:productSlug',
        name: 'public-checkout',
        component: PublicCheckoutView,
        meta: { title: 'Checkout', requiresCompanyAuth: true },
      },
      {
        path: 'pedido/:orderId',
        name: 'public-pedido',
        component: PublicOrderView,
        meta: { title: 'Pedido', requiresCompanyAuth: true },
      },
      {
        path: 'minhas-assinaturas',
        name: 'public-assinaturas',
        component: PublicSubscriptionsView,
        meta: { title: 'Minhas assinaturas', requiresCompanyAuth: true },
      },
    ],
  },

  /*
  | Authentication (tenant login/register + platform login/recovery)
  */
  {
    path: '/',
    component: AuthLayout,
    meta: { guestOnly: true },
    children: [
      {
        path: 'entrar',
        name: 'public-login',
        component: LoginView,
        meta: { title: 'Entrar', ...publicAuthMeta },
      },
      {
        path: 'cadastro',
        name: 'public-cadastro',
        component: RegisterView,
        meta: {
          title: 'Criar conta',
          quote: 'Seu próximo passo começa com uma conta e um curso escolhido por você.',
          quoteHighlight: 'Estude no seu ritmo e evolua sempre.',
        },
      },
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

  /*
  | Authenticated application (dashboard lives at /inicio)
  */
  {
    path: '/',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      { path: 'inicio', name: 'dashboard', component: HomeView, meta: { title: 'Início' } },
      { path: 'cursos', name: 'cursos', component: CoursesView, meta: { title: 'Meus cursos', searchPlaceholder: 'Buscar nos meus cursos...' } },
      { path: 'cursos/:courseId', name: 'curso', component: LessonView, meta: { title: 'Curso', searchPlaceholder: 'Buscar no curso...' } },
      { path: 'cursos/:courseId/aula/:lessonId', name: 'aula', component: LessonView, meta: { title: 'Aula', searchPlaceholder: 'Buscar no curso...' } },
      { path: 'aulas-ao-vivo', name: 'aulas-ao-vivo', component: LiveClassesView, meta: { title: 'Aulas ao vivo' } },
      { path: 'certificados', name: 'certs', component: CertificatesView, meta: { title: 'Meus certificados' } },
      { path: 'certificados/:id', name: 'certificado', component: CertificatePrintView, meta: { title: 'Certificado' } },
      { path: 'loja', name: 'loja', component: StoreView, meta: { title: 'Loja', searchPlaceholder: 'Buscar na loja...' } },
      { path: 'loja/:slug', name: 'loja-produto', component: StoreProductView, meta: { title: 'Produto', searchPlaceholder: 'Buscar na loja...' } },
      { path: 'robos', name: 'robos', component: RobotsView, meta: { title: 'Robôs' } },
      { path: 'robos/:slug', name: 'robo', component: RobotView, meta: { title: 'Robô' } },
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
      { path: 'admin/loja', name: 'admin-loja', component: AdminStoreView, meta: { title: 'Loja (admin)', requiresAdminMenu: true } },
      { path: 'admin/gateways', name: 'admin-gateways', component: AdminGatewaysView, meta: { title: 'Gateways (admin)', requiresAdminMenu: true } },
      { path: 'admin/pedidos', name: 'admin-pedidos', component: AdminOrdersView, meta: { title: 'Pedidos (admin)', requiresAdminMenu: true } },
      { path: 'admin/assinaturas', name: 'admin-assinaturas', component: AdminSubscriptionsView, meta: { title: 'Assinaturas (admin)', requiresAdminMenu: true } },
      { path: 'admin/usuarios', name: 'admin-usuarios', component: AdminUsersView, meta: { title: 'Usuários (admin)', requiresAdmin: true } },
      { path: 'admin/tipos-de-usuario', name: 'admin-tipos-de-usuario', component: AdminUserTypesView, meta: { title: 'Tipos de usuário (admin)', requiresAdmin: true } },
      { path: 'admin/menus', name: 'admin-menus', component: AdminMenusView, meta: { title: 'Menus (admin)', requiresSuperAdmin: true } },
      { path: 'admin/empresas', name: 'admin-empresas', component: AdminCompaniesView, meta: { title: 'Empresas (admin)', requiresSuperAdmin: true } },
    ],
  },

  /*
  | Legacy slug routes (local dev and backwards compatibility)
  */
  {
    path: '/e/:companySlug',
    component: PublicLayout,
    children: [
      {
        path: '',
        redirect: (to) => ({ name: 'legacy-loja', params: { companySlug: to.params.companySlug } }),
      },
      { path: 'loja', name: 'legacy-loja', component: PublicStoreView, meta: { title: 'Loja' } },
      {
        path: 'loja/:productSlug',
        name: 'legacy-produto',
        component: PublicProductView,
        meta: { title: 'Produto' },
      },
      {
        path: 'checkout/:productSlug',
        name: 'legacy-checkout',
        component: PublicCheckoutView,
        meta: { title: 'Checkout', requiresCompanyAuth: true },
      },
      {
        path: 'pedido/:orderId',
        name: 'legacy-pedido',
        component: PublicOrderView,
        meta: { title: 'Pedido', requiresCompanyAuth: true },
      },
      {
        path: 'assinaturas',
        name: 'legacy-assinaturas',
        component: PublicSubscriptionsView,
        meta: { title: 'Minhas assinaturas', requiresCompanyAuth: true },
      },
    ],
  },
  {
    path: '/e/:companySlug',
    component: AuthLayout,
    meta: { guestOnly: true },
    children: [
      {
        path: 'entrar',
        name: 'legacy-login',
        component: LoginView,
        meta: {
          title: 'Entrar',
          ...publicAuthMeta,
        },
      },
      {
        path: 'cadastro',
        name: 'legacy-cadastro',
        component: RegisterView,
        meta: {
          title: 'Criar conta',
          quote: 'Seu próximo passo começa com uma conta e um curso escolhido por você.',
          quoteHighlight: 'Estude no seu ritmo e evolua sempre.',
        },
      },
    ],
  },

  { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView, meta: { title: 'Página não encontrada' } },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 };
  },
});

function isLegacyRoute(to) {
  return typeof to.params.companySlug === 'string';
}

function loginTarget(to) {
  const redirect = typeof to.fullPath === 'string' && to.fullPath !== '/'
    ? { redirect: to.fullPath }
    : {};

  if (isLegacyRoute(to)) {
    return { name: 'legacy-login', params: { companySlug: to.params.companySlug }, query: redirect };
  }

  if (useTenantStore().isDomainMode) {
    return { name: 'public-login', query: redirect };
  }

  return { name: 'login', query: redirect };
}

const PUBLIC_ONLY_ROUTES = new Set([
  'public-loja',
  'public-produto',
  'public-checkout',
  'public-pedido',
  'public-assinaturas',
  'public-login',
  'public-cadastro',
]);

router.beforeEach(async (to) => {
  // Keep old hash links working after the SPA switched to history mode.
  if (typeof to.hash === 'string' && to.hash.startsWith('#/')) {
    const legacyLocation = to.hash.slice(1);

    if (
      legacyLocation.startsWith('/e/')
      || legacyLocation.startsWith('/reset-password')
      || legacyLocation.startsWith('/login')
    ) {
      return legacyLocation;
    }
  }

  const tenant = useTenantStore();
  const auth = useAuthStore();

  if (!tenant.resolved) {
    await tenant.resolve();
  }

  if (!auth.initialized) {
    await auth.init();
  }

  const legacy = isLegacyRoute(to);

  if (!tenant.isDomainMode && !legacy && PUBLIC_ONLY_ROUTES.has(to.name)) {
    return auth.isAuthenticated
      ? { name: 'dashboard' }
      : { name: 'login' };
  }

  if (tenant.isDomainMode && to.name === 'login') {
    return auth.isAuthenticated
      ? { name: 'dashboard' }
      : { name: 'public-login', query: to.query };
  }

  if (to.name === 'public-cadastro' && typeof to.query.next !== 'string') {
    return { name: 'public-loja' };
  }

  if (to.name === 'legacy-cadastro' && typeof to.query.next !== 'string') {
    return { name: 'legacy-loja', params: { companySlug: to.params.companySlug } };
  }

  if (to.meta?.requiresCompanyAuth && !auth.isAuthenticated) {
    return loginTarget(to);
  }

  if (to.meta?.requiresAuth && !auth.isAuthenticated) {
    return loginTarget(to);
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
  document.title = to.meta?.title ? `Delta Class · ${to.meta.title}` : 'Delta Class';
});

export default router;
