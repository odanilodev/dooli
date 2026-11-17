<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Apaga os acessos concedidos (access_grants) e os certificados emitidos
     * (certificate_users) da turma "Turma 2027" (#1), para ela poder ser excluída.
     *
     * A turma em si NÃO é excluída aqui (exclua pela tela). Só age se o id e o nome
     * baterem, para não apagar nada de outra turma caso os ids sejam diferentes em
     * outro ambiente. É irreversível: faça backup antes.
     */
    private const TEAM_ID = 1;

    private const TEAM_NAME = 'Turma 2027';

    public function up(): void
    {
        $team = DB::table('teams')->where('id', self::TEAM_ID)->first();

        if (! $team || $team->name !== self::TEAM_NAME) {
            return;
        }

        DB::transaction(function () {
            DB::table('access_grants')->where('team_id', self::TEAM_ID)->delete();
            DB::table('certificate_users')->where('team_id', self::TEAM_ID)->delete();
        });
    }

    public function down(): void
    {
        // Irreversível: os registros apagados não são recriados.
    }
};
