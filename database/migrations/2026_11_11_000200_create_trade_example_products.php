<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    private const DOMAIN = 'deltaclass.com.br';

    /**
     * Cria o produto da loja de cada curso de trade de exemplo e libera cursos e
     * produtos para todas as turmas da empresa do domínio deltaclass.com.br.
     * Os produtos nascem inativos (sem --publish): ative no painel depois de
     * conferir preço e capa. Sem empresa ou sem cursos, não faz nada.
     */
    public function up(): void
    {
        Artisan::call('courses:create-test', [
            '--domain' => self::DOMAIN,
            '--store' => true,
            '--teams' => true,
        ]);
    }

    public function down(): void
    {
        // Os produtos saem junto com os cursos no --purge; nada a reverter aqui
        // sem apagar os cursos criados pela migration anterior.
    }
};
