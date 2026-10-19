<?php

use App\Support\CertificatePublicCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_users', function (Blueprint $table) {
            $table->string('public_code', 16)->nullable()->after('code');
        });

        $used = [];

        DB::table('certificate_users')
            ->select('id')
            ->chunkById(200, function ($rows) use (&$used) {
                foreach ($rows as $row) {
                    do {
                        $code = CertificatePublicCode::generate();
                    } while (isset($used[$code]));

                    $used[$code] = true;

                    DB::table('certificate_users')
                        ->where('id', $row->id)
                        ->update(['public_code' => $code]);
                }
            });

        Schema::table('certificate_users', function (Blueprint $table) {
            $table->unique('public_code', 'certificate_users_public_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_users', function (Blueprint $table) {
            $table->dropUnique('certificate_users_public_code_unique');
            $table->dropColumn('public_code');
        });
    }
};
