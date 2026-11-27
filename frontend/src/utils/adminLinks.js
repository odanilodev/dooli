// Páginas de administração que o menu esconde de quem não tem o perfil exigido.
export const SUPER_ADMIN_ONLY_LINKS = ['/admin/menus', '/admin/empresas'];

export const ADMIN_ONLY_LINKS = [
  ...SUPER_ADMIN_ONLY_LINKS,
  '/admin/usuarios',
  '/admin/tipos-de-usuario',
  '/admin/vendedores',
  '/admin/comissoes',
];
