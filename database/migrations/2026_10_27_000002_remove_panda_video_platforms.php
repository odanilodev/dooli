<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('video_platforms')->where('code', 'panda')->delete();
    }

    public function down(): void
    {
        // The removed Panda credentials cannot be restored.
    }
};
