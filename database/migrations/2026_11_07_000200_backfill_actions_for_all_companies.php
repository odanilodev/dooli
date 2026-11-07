<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Empresas criadas pelo painel nasciam sem as ações de progresso (o seeder só criava para a
     * empresa 1), e a conclusão de aula falhava com "Ação de conclusão não configurada".
     */
    public function up(): void
    {
        $actions = ['started' => 'Iniciou', 'viewed' => 'Visualizou', 'completed' => 'Concluiu'];
        $now = now();

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach ($actions as $code => $name) {
                $exists = DB::table('actions')
                    ->where('company_id', $companyId)
                    ->where('code', $code)
                    ->exists();

                if (! $exists) {
                    DB::table('actions')->insert([
                        'company_id' => $companyId,
                        'code' => $code,
                        'name' => $name,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Dados de referência: não remove para não apagar ações já usadas no histórico.
    }
};
