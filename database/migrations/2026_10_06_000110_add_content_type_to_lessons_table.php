<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('content_type', 20)->nullable()->after('status');
        });

        DB::table('lessons')->whereNotNull('video_id')->update(['content_type' => 'video']);
        DB::table('lessons')->whereNull('video_id')->whereNotNull('document_id')->update(['content_type' => 'document']);
        DB::table('lessons')->whereNull('video_id')->whereNull('document_id')->whereNotNull('webinar_link')->update(['content_type' => 'live']);
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('content_type');
        });
    }
};
