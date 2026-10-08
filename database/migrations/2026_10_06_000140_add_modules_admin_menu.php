<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('menus')->where('link', '/admin/modulos')->exists()) {
            return;
        }

        $admin = DB::table('menus')->where('name', 'Administração')->where('type', 'section')->first();

        if (! $admin) {
            return;
        }

        DB::table('menus')->insert([
            'name' => 'Módulos',
            'type' => 'link',
            'link' => '/admin/modulos',
            'icon' => 'modules',
            'sort_order' => 2,
            'sub' => (string) $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('link', '/admin/modulos')->delete();
    }
};
