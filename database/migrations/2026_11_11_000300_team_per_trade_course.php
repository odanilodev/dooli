<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    private const DOMAIN = 'deltaclass.com.br';

    /**
     * Cada curso de trade de exemplo passa a ter a sua "Turma <curso>" (só com ele) e o
     * produto correspondente fica ligado apenas a essa turma, em vez das turmas gerais
     * que liberavam todos os cursos. Só age sobre o que já existe: não cria cursos nem
     * sobrescreve preço, status ou descrição. Quem já está nas turmas gerais não perde acesso.
     */
    public function up(): void
    {
        Artisan::call('courses:create-test', [
            '--domain' => self::DOMAIN,
            '--team-per-course' => true,
            '--only-existing' => true,
        ]);
    }

    public function down(): void
    {
        // Não restaura a ligação antiga: ela liberava todos os cursos em qualquer compra.
    }
};
