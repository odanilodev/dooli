<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * @var list<array{0: string, 1: ?string, 2: ?string}>
     */
    private const ADMIN_ITEMS = [
        ['Cursos', '/admin/cursos', 'content'],
        ['Módulos', '/admin/modulos', 'modules'],
        ['Aulas ao vivo', '/admin/aulas-ao-vivo', 'live'],
        ['Turmas', '/admin/turmas', 'teams'],
        ['Relatórios', '/admin/relatorios', 'report'],
        ['Comentários', '/admin/comentarios', 'comments'],
        ['Vídeos', '/admin/videos', 'videos'],
        ['Documentos', '/admin/documentos', 'documents'],
        ['Anexos', '/admin/anexos', 'attachments'],
        ['Banners', '/admin/banners', 'banners'],
        ['Produtos', '/admin/produtos', 'store'],
        ['Gateways', '/admin/gateways', 'gateway'],
        ['Pedidos', '/admin/pedidos', 'orders'],
        ['Assinaturas', '/admin/assinaturas', 'subscriptions'],
        ['Certificados', '/admin/certificados', 'certificate'],
        ['Plataformas de vídeo', '/admin/video-platforms', 'video-platforms'],
        ['Menus', '/admin/menus', 'menu'],
        ['Empresas', '/admin/empresas', 'company'],
        ['Usuários', '/admin/usuarios', 'users'],
        ['Tipos de usuário', '/admin/tipos-de-usuario', 'user-types'],
    ];

    /**
     * @var list<array{0: string, 1: ?string, 2: ?string}>
     */
    private const ACCOUNT_ITEMS = [
        ['Meu perfil', '/perfil', 'profile'],
        ['Configurações', '/configuracoes', 'settings'],
    ];

    public function run(): void
    {
        $names = [
            'Dashboard',
            'Administração',
            'Conta',
            ...array_column(self::ADMIN_ITEMS, 0),
            ...array_column(self::ACCOUNT_ITEMS, 0),
        ];

        Menu::query()->whereNotIn('name', $names)->delete();

        $this->menu('Dashboard', [
            'type' => 'link',
            'link' => '/',
            'icon' => 'dashboard',
            'sort_order' => 1,
            'sub' => null,
        ]);

        $admin = $this->menu('Administração', [
            'type' => 'section',
            'link' => null,
            'icon' => null,
            'sort_order' => 2,
            'sub' => null,
        ]);

        foreach (self::ADMIN_ITEMS as $index => [$name, $link, $icon]) {
            $this->menu($name, [
                'type' => 'link',
                'link' => $link,
                'icon' => $icon,
                'sort_order' => $index + 1,
                'sub' => (string) $admin->id,
            ]);
        }

        $account = $this->menu('Conta', [
            'type' => 'section',
            'link' => null,
            'icon' => null,
            'sort_order' => 3,
            'sub' => null,
        ]);

        foreach (self::ACCOUNT_ITEMS as $index => [$name, $link, $icon]) {
            $this->menu($name, [
                'type' => 'link',
                'link' => $link,
                'icon' => $icon,
                'sort_order' => $index + 1,
                'sub' => (string) $account->id,
            ]);
        }
    }

    private function menu(string $name, array $attributes): Menu
    {
        return Menu::query()->updateOrCreate(['name' => $name], $attributes);
    }
}
