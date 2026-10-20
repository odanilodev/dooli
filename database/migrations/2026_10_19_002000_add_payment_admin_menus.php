<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const ITEMS = [
        ['Gateways', '/admin/gateways', 'gateway'],
        ['Pedidos', '/admin/pedidos', 'orders'],
        ['Assinaturas', '/admin/assinaturas', 'subscriptions'],
    ];

    public function up(): void
    {
        $admin = $this->adminSection();

        if (! $admin) {
            return;
        }

        $links = array_column(self::ITEMS, 1);
        $children = DB::table('menus')
            ->where('sub', (string) $admin->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();

        $existing = [];

        foreach ($children as $child) {
            if (in_array($child->link, $links, true)) {
                $existing[$child->link] = $child;
            }
        }

        $managed = array_map(
            fn (array $item) => $existing[$item[1]] ?? (object) [
                'id' => null,
                'name' => $item[0],
                'link' => $item[1],
                'icon' => $item[2],
            ],
            self::ITEMS,
        );

        $productIndex = null;

        foreach ($children as $index => $child) {
            if ($child->link === '/admin/produtos') {
                $productIndex = $index;

                break;
            }
        }

        $before = $productIndex === null ? [] : array_slice($children, 0, $productIndex + 1);
        $after = $productIndex === null ? $children : array_slice($children, $productIndex + 1);
        $withoutManaged = fn (array $items) => array_values(array_filter(
            $items,
            fn (object $item) => ! in_array($item->link, $links, true),
        ));

        $ordered = [
            ...$withoutManaged($before),
            ...$managed,
            ...$withoutManaged($after),
        ];

        foreach ($ordered as $index => $item) {
            $sortOrder = $index + 1;

            if ($item->id === null) {
                DB::table('menus')->insert([
                    'name' => $item->name,
                    'type' => 'link',
                    'link' => $item->link,
                    'icon' => $item->icon,
                    'sort_order' => $sortOrder,
                    'sub' => (string) $admin->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            if ((int) $item->sort_order !== $sortOrder) {
                DB::table('menus')->where('id', $item->id)->update([
                    'sort_order' => $sortOrder,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('menus')
            ->whereIn('link', array_column(self::ITEMS, 1))
            ->delete();
    }

    private function adminSection(): ?object
    {
        $admin = DB::table('menus')
            ->where('name', 'Administração')
            ->where('type', 'section')
            ->first();

        if ($admin) {
            return $admin;
        }

        $product = DB::table('menus')->where('link', '/admin/produtos')->first();

        if (! $product || ! $product->sub) {
            return null;
        }

        return DB::table('menus')->where('id', $product->sub)->first();
    }
};
