<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('menus')->where('link', '/robos/delta-screener')->exists()) {
            return;
        }

        // Só mexe em instalações que já têm o menu cadastrado; as novas recebem pelo MenuSeeder.
        if (! DB::table('menus')->where('link', '/inicio')->exists()) {
            return;
        }

        $analysis = DB::table('menus')->where('name', 'Análise')->where('type', 'section')->first();
        $analysisId = $analysis?->id;

        if (! $analysisId) {
            $analysisId = DB::table('menus')->insertGetId([
                'name' => 'Análise',
                'type' => 'section',
                'link' => null,
                'icon' => null,
                'sort_order' => 0,
                'sub' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Entra logo depois do Dashboard e empurra o restante.
            $position = 1;

            foreach (DB::table('menus')->whereNull('sub')->where('id', '!=', $analysisId)->orderBy('sort_order')->orderBy('id')->get() as $menu) {
                DB::table('menus')->where('id', $menu->id)->update(['sort_order' => $position++, 'updated_at' => now()]);

                if ($menu->link === '/inicio') {
                    DB::table('menus')->where('id', $analysisId)->update(['sort_order' => $position++]);
                }
            }
        }

        DB::table('menus')->insert([
            'name' => 'DeltaStats',
            'type' => 'link',
            'link' => '/robos/delta-screener',
            'icon' => 'robots',
            'sort_order' => 1,
            'sub' => (string) $analysisId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('link', '/robos/delta-screener')->delete();
        DB::table('menus')->where('name', 'Análise')->where('type', 'section')
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('menus as c')->whereColumn('c.sub', 'menus.id'))
            ->delete();
    }
};
