<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Garante o DeltaStats no catálogo de toda empresa existente, sem depender do
        // RobotSeeder (quem só roda `migrate` num banco antigo também passa a ter a ferramenta).
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $exists = DB::table('robots')->where('company_id', $companyId)->where('slug', 'delta-screener')->exists();

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
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Mantém os robôs: removê-los apagaria as liberações por turma.
    }
};
