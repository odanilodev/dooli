<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyLogos = DB::table('company_settings')
            ->where('name', 'logo')
            ->orderBy('id')
            ->get();

        foreach ($legacyLogos as $legacy) {
            $hasLightLogo = DB::table('company_settings')
                ->where('company_id', $legacy->company_id)
                ->where('name', 'logo_light')
                ->exists();

            if ($hasLightLogo) {
                continue;
            }

            DB::table('company_settings')
                ->where('id', $legacy->id)
                ->update([
                    'name' => 'logo_light',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $lightLogos = DB::table('company_settings')
            ->where('name', 'logo_light')
            ->orderBy('id')
            ->get();

        foreach ($lightLogos as $light) {
            $hasLegacyLogo = DB::table('company_settings')
                ->where('company_id', $light->company_id)
                ->where('name', 'logo')
                ->exists();

            if ($hasLegacyLogo) {
                continue;
            }

            DB::table('company_settings')
                ->where('id', $light->id)
                ->update([
                    'name' => 'logo',
                    'updated_at' => now(),
                ]);
        }
    }
};
