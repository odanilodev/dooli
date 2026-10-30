<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const TOKEN_LENGTH = 8;

    public function up(): void
    {
        $used = DB::table('sellers')
            ->pluck('share_token')
            ->filter()
            ->mapWithKeys(fn (string $token) => [Str::lower($token) => true])
            ->all();

        DB::table('sellers')
            ->select('id')
            ->chunkById(200, function ($sellers) use (&$used): void {
                foreach ($sellers as $seller) {
                    do {
                        $token = Str::random(self::TOKEN_LENGTH);
                    } while (isset($used[Str::lower($token)]));

                    $used[Str::lower($token)] = true;

                    DB::table('sellers')
                        ->where('id', $seller->id)
                        ->update([
                            'share_token' => $token,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
