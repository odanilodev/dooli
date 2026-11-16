<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LINK = '/admin/cupons';

    public function up(): void
    {
        if (DB::table('menus')->where('link', self::LINK)->exists()) {
            return;
        }

        $store = DB::table('menus')
            ->where('name', 'Loja')
            ->where('type', 'dropdown')
            ->first();

        if (! $store) {
            return;
        }

        $sortOrder = (int) DB::table('menus')->where('sub', (string) $store->id)->max('sort_order') + 1;

        DB::table('menus')->insert([
            'name' => 'Cupons',
            'type' => 'link',
            'link' => self::LINK,
            'icon' => 'coupons',
            'sub' => (string) $store->id,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('link', self::LINK)->delete();
    }
};
