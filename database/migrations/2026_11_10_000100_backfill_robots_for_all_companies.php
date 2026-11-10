<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A migration `register_delta_screener_robot` só atendeu as empresas que existiam na hora em que
     * rodou. Empresas criadas depois (ou em que o robô foi apagado) ficaram sem o DeltaStats no
     * catálogo: o menu aparecia, mas a página dava 404 e o admin não tinha o que liberar para a turma.
     * Esta migration garante o robô em toda empresa e é segura para rodar de novo.
     */
    public function up(): void
    {
        $now = now();

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $exists = DB::table('robots')
                ->where('company_id', $companyId)
                ->where('slug', 'delta-screener')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('robots')->insert([
                'company_id' => $companyId,
                'slug' => 'delta-screener',
                'name' => 'DeltaStats',
                'description' => 'Triagem estatística de setups de entrada por limiar fixo na B3, mercado americano e cripto.',
                'market' => 'b3,us,crypto',
                'icon' => 'chart',
                'status' => 'active',
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Mantém os robôs: removê-los apagaria as liberações por turma.
    }
};
