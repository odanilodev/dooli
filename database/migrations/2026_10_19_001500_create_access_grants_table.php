<?php

use App\Enums\AccessGrantSource;
use App\Enums\GeneralStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('team_id')->constrained('teams');
            $table->string('source_type', 20)->index();
            $table->unsignedBigInteger('source_id')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('status', 20)->default(GeneralStatus::Active->value)->index();
            $table->timestamps();

            $table->unique(
                ['company_id', 'user_id', 'team_id', 'source_type', 'source_id'],
                'access_grants_source_unique',
            );
        });

        DB::table('team_users')
            ->where('status', GeneralStatus::Active->value)
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                $now = now();

                $grants = [];

                foreach ($rows as $row) {
                    $grants[] = [
                        'company_id' => $row->company_id,
                        'user_id' => $row->user_id,
                        'team_id' => $row->team_id,
                        'source_type' => AccessGrantSource::Manual->value,
                        'source_id' => 0,
                        'starts_at' => $row->created_at ?? $now,
                        'expires_at' => $row->expired_at,
                        'status' => GeneralStatus::Active->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($grants !== []) {
                    DB::table('access_grants')->insertOrIgnore($grants);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_grants');
    }
};
