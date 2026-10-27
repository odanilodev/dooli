<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('lessons')
            ->where('content_type', 'live')
            ->update(['content_type' => null]);
    }

    public function down(): void
    {
        // A null content type cannot be safely identified as a former live lesson.
    }
};
