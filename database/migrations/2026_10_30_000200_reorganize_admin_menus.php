<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ADMIN_SECTION = 'Administração';

    private const ACCOUNT_SECTION = 'Conta';

    /**
     * @var array<string, array{icon: string, items: list<array{0: string, 1: string, 2: string}>}>
     */
    private const GROUPS = [
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
        'Loja e vendas' => [
            'icon' => 'store',
            'items' => [
                ['Produtos', '/admin/produtos', 'store'],
                ['Loja', '/admin/loja', 'storefront'],
                ['Banners', '/admin/banners', 'banners'],
                ['Vendedores', '/admin/vendedores', 'sellers'],
                ['Comissões', '/admin/comissoes', 'commissions'],
            ],
        ],
        'Financeiro' => [
            'icon' => 'payments',
            'items' => [
                ['Pedidos', '/admin/pedidos', 'orders'],
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
     * @var list<string>
     */
    private const LEGACY_ORDER = [
        '/admin/cursos',
        '/admin/modulos',
        '/admin/aulas-ao-vivo',
        '/admin/turmas',
        '/admin/relatorios',
        '/admin/comentarios',
        '/admin/videos',
        '/admin/documentos',
        '/admin/anexos',
        '/admin/banners',
        '/admin/produtos',
        '/admin/loja',
        '/admin/gateways',
        '/admin/pedidos',
        '/admin/vendedores',
        '/admin/comissoes',
        '/admin/pagamentos',
        '/admin/assinaturas',
        '/admin/certificados',
        '/admin/video-platforms',
        '/admin/menus',
        '/admin/empresas',
        '/admin/usuarios',
        '/admin/tipos-de-usuario',
    ];

    public function up(): void
    {
        if (! DB::table('menus')->where('name', self::ADMIN_SECTION)->where('type', 'section')->exists()) {
            return;
        }

        $this->ensureLink('Dashboard', '/inicio', 'dashboard', 1, null);
        $adminId = $this->ensureSection(self::ADMIN_SECTION, '/admin/cursos', 2);
        $accountId = $this->ensureSection(self::ACCOUNT_SECTION, '/perfil', 6);

        foreach (self::GROUPS as $name => $group) {
            $groupId = $this->ensureGroup($name, $group['icon'], $adminId, $this->groupSort($name));

            foreach ($group['items'] as $index => [$itemName, $link, $icon]) {
                $this->ensureLink($itemName, $link, $icon, $index + 1, $groupId);
            }
        }

        $this->ensureLink('Meu perfil', '/perfil', 'profile', 1, $accountId);
        $this->ensureLink('Configurações', '/configuracoes', 'settings', 2, $accountId);
    }

    public function down(): void
    {
        $admin = DB::table('menus')
            ->where('name', self::ADMIN_SECTION)
            ->where('type', 'section')
            ->first();

        if (! $admin) {
            return;
        }

        $groupIds = DB::table('menus')
            ->whereIn('name', array_keys(self::GROUPS))
            ->where('type', 'dropdown')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($groupIds !== []) {
            DB::table('menus')
                ->whereIn('sub', $groupIds)
                ->update([
                    'sub' => (string) $admin->id,
                    'updated_at' => now(),
                ]);
        }

        foreach (self::LEGACY_ORDER as $index => $link) {
            DB::table('menus')
                ->where('link', $link)
                ->update([
                    'sub' => (string) $admin->id,
                    'sort_order' => $index + 1,
                    'updated_at' => now(),
                ]);
        }

        DB::table('menus')
            ->whereIn('name', array_keys(self::GROUPS))
            ->where('type', 'dropdown')
            ->delete();

        DB::table('menus')
            ->where('name', self::ACCOUNT_SECTION)
            ->update(['sort_order' => 4, 'updated_at' => now()]);

        DB::table('menus')
            ->where('name', self::ADMIN_SECTION)
            ->update(['sort_order' => 3, 'updated_at' => now()]);
    }

    private function ensureSection(string $name, string $fallbackLink, int $sortOrder): int
    {
        $section = DB::table('menus')
            ->where('name', $name)
            ->where('type', 'section')
            ->first();

        if (! $section) {
            $fallback = DB::table('menus')->where('link', $fallbackLink)->first();

            if ($fallback && $fallback->sub) {
                $section = DB::table('menus')->where('id', $fallback->sub)->first();
            }
        }

        if ($section) {
            DB::table('menus')->where('id', $section->id)->update([
                'name' => $name,
                'type' => 'section',
                'link' => null,
                'sub' => null,
                'icon' => null,
                'sort_order' => $sortOrder,
                'updated_at' => now(),
            ]);

            return (int) $section->id;
        }

        return (int) DB::table('menus')->insertGetId([
            'name' => $name,
            'type' => 'section',
            'link' => null,
            'sub' => null,
            'icon' => null,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureGroup(string $name, string $icon, int $adminId, int $sortOrder): int
    {
        $group = DB::table('menus')->where('name', $name)->first();

        if ($group) {
            DB::table('menus')->where('id', $group->id)->update([
                'name' => $name,
                'type' => 'dropdown',
                'link' => null,
                'sub' => (string) $adminId,
                'icon' => $icon,
                'sort_order' => $sortOrder,
                'updated_at' => now(),
            ]);

            return (int) $group->id;
        }

        return (int) DB::table('menus')->insertGetId([
            'name' => $name,
            'type' => 'dropdown',
            'link' => null,
            'sub' => (string) $adminId,
            'icon' => $icon,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureLink(
        string $name,
        string $link,
        string $icon,
        int $sortOrder,
        ?int $parentId,
    ): int {
        $item = DB::table('menus')->where('link', $link)->first();

        $attributes = [
            'name' => $name,
            'type' => 'link',
            'link' => $link,
            'sub' => $parentId === null ? null : (string) $parentId,
            'icon' => $icon,
            'sort_order' => $sortOrder,
            'updated_at' => now(),
        ];

        if ($item) {
            DB::table('menus')->where('id', $item->id)->update($attributes);

            return (int) $item->id;
        }

        return (int) DB::table('menus')->insertGetId([
            ...$attributes,
            'created_at' => now(),
        ]);
    }

    private function groupSort(string $name): int
    {
        return array_search($name, array_keys(self::GROUPS), true) + 1;
    }
};
