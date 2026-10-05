<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')
            ->where('name', 'Conteúdo')
            ->update([
                'name' => 'Cursos',
                'link' => '/admin/cursos',
            ]);
    }

    public function down(): void
    {
        DB::table('menus')
            ->where('name', 'Cursos')
            ->where('link', '/admin/cursos')
            ->update([
                'name' => 'Conteúdo',
                'link' => '/admin/conteudo',
            ]);
    }
};
