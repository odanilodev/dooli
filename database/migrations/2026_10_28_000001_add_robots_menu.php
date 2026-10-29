<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('menus')->where('link', '/robos')->exists()) {
            return;
        }

        $top = DB::table('menus')->whereNull('sub')->orderBy('sort_order')->orderBy('id')->get();
        $position = 1;

        foreach ($top as $menu) {
            DB::table('menus')->where('id', $menu->id)->update(['sort_order' => $position, 'updated_at' => now()]);
            $position++;

            if ($menu->link === '/inicio') {
                DB::table('menus')->insert([
                    'name' => 'Robôs',
                    'type' => 'link',
                    'link' => '/robos',
                    'icon' => 'robots',
                    'sort_order' => $position++,
                    'sub' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('menus')->where('link', '/robos')->delete();
    }
};
