<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')
            ->where('link', '/admin/pedidos')
            ->update([
                'name' => 'Vendas',
                'link' => '/admin/vendas',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('menus')
            ->where('link', '/admin/vendas')
            ->update([
                'name' => 'Pedidos',
                'link' => '/admin/pedidos',
                'updated_at' => now(),
            ]);
    }
};
