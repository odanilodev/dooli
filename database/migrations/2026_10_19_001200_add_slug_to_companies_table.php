<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        $used = [];

        foreach (DB::table('companies')->orderBy('id')->get(['id', 'name']) as $company) {
            $base = Str::slug((string) $company->name) ?: 'empresa';
            $slug = $base;
            $suffix = 2;

            while (in_array($slug, $used, true) || DB::table('companies')->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$suffix;
                $suffix++;
            }

            $used[] = $slug;

            DB::table('companies')->where('id', $company->id)->update(['slug' => $slug]);
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
