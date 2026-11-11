<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    private const DOMAIN = 'deltaclass.com.br';

    /**
     * Cria os cursos de trade de exemplo na empresa do domínio deltaclass.com.br,
     * reaproveitando o primeiro vídeo ativo dela. Os cursos ficam sem produto na
     * loja e sem turma, então nenhum aluno os enxerga até serem liberados.
     * Se a empresa ou o vídeo não existirem (local, testes), não faz nada.
     */
    public function up(): void
    {
        Artisan::call('courses:create-test', ['--domain' => self::DOMAIN]);
    }

    public function down(): void
    {
        // Remove só os cursos deste catálogo; --force evita a confirmação interativa.
        Artisan::call('courses:create-test', ['--domain' => self::DOMAIN, '--purge' => true, '--force' => true]);
    }
};
