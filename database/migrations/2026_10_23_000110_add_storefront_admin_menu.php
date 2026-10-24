<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('menus')->where('link', '/admin/loja')->exists()) {
            return;
        }

        $admin = DB::table('menus')
            ->where('name', 'Administração')
            ->where('type', 'section')
            ->first();

        if (! $admin) {
            return;
        }

        $children = DB::table('menus')
            ->where('sub', (string) $admin->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $ordered = [];

        foreach ($children as $child) {
            $ordered[] = $child;

            if ($child->link === '/admin/produtos') {
                $ordered[] = null;
            }
        }

        if (! in_array(null, $ordered, true)) {
            $ordered[] = null;
        }

        foreach ($ordered as $index => $item) {
            if ($item === null) {
                DB::table('menus')->insert([
                    'name' => 'Loja',
                    'type' => 'link',
                    'link' => '/admin/loja',
                    'icon' => 'storefront',
                    'sort_order' => $index + 1,
                    'sub' => (string) $admin->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            if ((int) $item->sort_order !== $index + 1) {
                DB::table('menus')->where('id', $item->id)->update([
                    'sort_order' => $index + 1,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('menus')->where('link', '/admin/loja')->delete();
    }
};
