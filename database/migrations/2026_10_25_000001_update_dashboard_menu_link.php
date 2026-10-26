<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')
            ->where('link', '/')
            ->update([
                'link' => '/inicio',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('menus')
            ->where('link', '/inicio')
            ->where('name', 'Dashboard')
            ->update([
                'link' => '/',
                'updated_at' => now(),
            ]);
    }
};
