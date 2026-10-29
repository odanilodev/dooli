<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var list<array{name: string, link: string, icon: string}>
     */
    private const ITEMS = [
        ['name' => 'Vendedores', 'link' => '/admin/vendedores', 'icon' => 'sellers'],
        ['name' => 'Comissões', 'link' => '/admin/comissoes', 'icon' => 'commissions'],
    ];

    public function up(): void
    {
        $orders = DB::table('menus')->where('link', '/admin/pedidos')->first();

        if (! $orders || ! $orders->sub) {
            return;
        }

        $parentId = (string) $orders->sub;
        $links = array_column(self::ITEMS, 'link');
        $existing = DB::table('menus')
            ->where('sub', $parentId)
            ->whereIn('link', $links)
            ->get()
            ->keyBy('link');

        $children = DB::table('menus')
            ->where('sub', $parentId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->reject(fn (object $item) => in_array($item->link, $links, true))
            ->values();

        $ordered = [];

        foreach ($children as $child) {
            $ordered[] = $child;

            if ($child->link === '/admin/pedidos') {
                foreach (self::ITEMS as $item) {
                    $ordered[] = $existing->get($item['link']) ?? (object) [
                        'id' => null,
                        'name' => $item['name'],
                        'link' => $item['link'],
                        'icon' => $item['icon'],
                    ];
                }
            }
        }

        foreach ($ordered as $index => $item) {
            $sortOrder = $index + 1;

            if ($item->id === null) {
                DB::table('menus')->insert([
                    'name' => $item->name,
                    'type' => 'link',
                    'link' => $item->link,
                    'icon' => $item->icon,
                    'sort_order' => $sortOrder,
                    'sub' => $parentId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            $updates = ['sort_order' => $sortOrder];

            if ((int) $item->sort_order !== $sortOrder) {
                $updates['updated_at'] = now();
            }

            DB::table('menus')->where('id', $item->id)->update($updates);
        }
    }

    public function down(): void
    {
        DB::table('menus')
            ->whereIn('link', array_column(self::ITEMS, 'link'))
            ->delete();
    }
};
