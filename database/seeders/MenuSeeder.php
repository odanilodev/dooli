<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * @var array<string, array{icon: string, items: list<array{0: string, 1: string, 2: string}>}>
     */
    private const ADMIN_GROUPS = [
        'Conteúdo' => [
            'icon' => 'content',
            'items' => [
                ['Cursos', '/admin/cursos', 'content'],
                ['Módulos', '/admin/modulos', 'modules'],
                ['Aulas ao vivo', '/admin/aulas-ao-vivo', 'live'],
                ['Vídeos', '/admin/videos', 'videos'],
                ['Documentos', '/admin/documentos', 'documents'],
                ['Anexos', '/admin/anexos', 'attachments'],
            ],
        ],
        'Alunos' => [
            'icon' => 'teams',
            'items' => [
                ['Turmas', '/admin/turmas', 'teams'],
                ['Certificados', '/admin/certificados', 'certificate'],
                ['Relatórios', '/admin/relatorios', 'report'],
                ['Comentários', '/admin/comentarios', 'comments'],
            ],
        ],
        'Loja' => [
            'icon' => 'store',
            'items' => [
                ['Produtos', '/admin/produtos', 'store'],
                ['Vitrine', '/admin/loja', 'storefront'],
                ['Banners', '/admin/banners', 'banners'],
            ],
        ],
        'Comercial' => [
            'icon' => 'sellers',
            'items' => [
                ['Vendedores', '/admin/vendedores', 'sellers'],
                ['Comissões', '/admin/comissoes', 'commissions'],
            ],
        ],
        'Financeiro' => [
            'icon' => 'payments',
            'items' => [
                ['Vendas', '/admin/vendas', 'orders'],
                ['Pagamentos', '/admin/pagamentos', 'payments'],
                ['Assinaturas', '/admin/assinaturas', 'subscriptions'],
                ['Gateways', '/admin/gateways', 'gateway'],
            ],
        ],
        'Sistema' => [
            'icon' => 'settings',
            'items' => [
                ['Usuários', '/admin/usuarios', 'users'],
                ['Tipos de usuário', '/admin/tipos-de-usuario', 'user-types'],
                ['Plataformas de vídeo', '/admin/video-platforms', 'video-platforms'],
                ['Menus', '/admin/menus', 'menu'],
                ['Empresas', '/admin/empresas', 'company'],
            ],
        ],
    ];

    /**
     * @var list<array{0: string, 1: string, 2: string}>
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
            ...array_keys(self::ADMIN_GROUPS),
            ...array_column(self::ACCOUNT_ITEMS, 0),
        ];

        foreach (self::ADMIN_GROUPS as $group) {
            $names = [...$names, ...array_column($group['items'], 0)];
        }

        Menu::query()->whereNotIn('name', $names)->delete();

        $this->menu('Dashboard', [
            'type' => 'link',
            'link' => '/inicio',
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

        foreach (self::ADMIN_GROUPS as $groupIndex => $group) {
            $groupMenu = $this->menu($groupIndex, [
                'type' => 'dropdown',
                'link' => null,
                'icon' => $group['icon'],
                'sort_order' => $this->groupSort($groupIndex),
                'sub' => (string) $admin->id,
            ]);

            foreach ($group['items'] as $index => [$name, $link, $icon]) {
                $this->menu($name, [
                    'type' => 'link',
                    'link' => $link,
                    'icon' => $icon,
                    'sort_order' => $index + 1,
                    'sub' => (string) $groupMenu->id,
                ]);
            }
        }

        $account = $this->menu('Conta', [
            'type' => 'section',
            'link' => null,
            'icon' => null,
            'sort_order' => 7,
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

    private function groupSort(string $name): int
    {
        return array_search($name, array_keys(self::ADMIN_GROUPS), true) + 1;
    }
}
