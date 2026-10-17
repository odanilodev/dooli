<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addTimelineUniqueness();
        $this->addCertificateActiveUniqueness();
    }

    public function down(): void
    {
        Schema::table('timelines', function (Blueprint $table) {
            $table->dropUnique('timelines_completion_unique');
        });

        Schema::table('certificate_users', function (Blueprint $table) {
            $table->dropUnique('certificate_users_active_unique');
            $table->dropColumn('active_flag');
        });
    }

    /**
     * A lesson can only be completed once per user/course/team/action. The
     * service already checks this, but a DB unique makes it race-proof.
     */
    private function addTimelineUniqueness(): void
    {
        $duplicates = DB::table('timelines')
            ->selectRaw('MIN(id) as keep_id, user_id, course_id, lesson_id, action_id, team_id')
            ->groupBy('user_id', 'course_id', 'lesson_id', 'action_id', 'team_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('timelines')
                ->where('user_id', $duplicate->user_id)
                ->where('course_id', $duplicate->course_id)
                ->where('lesson_id', $duplicate->lesson_id)
                ->where('action_id', $duplicate->action_id)
                ->where('team_id', $duplicate->team_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('timelines', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'course_id', 'lesson_id', 'action_id', 'team_id'],
                'timelines_completion_unique',
            );
        });
    }

    /**
     * At most one valid certificate per user/team/template. A nullable
     * "active_flag" set to 1 only while the certificate is valid lets MySQL
     * treat invalidated rows as distinct (NULL), preserving history.
     */
    private function addCertificateActiveUniqueness(): void
    {
        if (! Schema::hasColumn('certificate_users', 'active_flag')) {
            Schema::table('certificate_users', function (Blueprint $table) {
                $table->unsignedTinyInteger('active_flag')->nullable()->after('invalidation_reason');
            });
        }

        DB::table('certificate_users')->whereNull('invalidated_at')->update(['active_flag' => 1]);

        $duplicates = DB::table('certificate_users')
            ->selectRaw('MAX(id) as keep_id, user_id, team_id, certificate_id')
            ->whereNull('invalidated_at')
            ->groupBy('user_id', 'team_id', 'certificate_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('certificate_users')
                ->where('user_id', $duplicate->user_id)
                ->where('team_id', $duplicate->team_id)
                ->where('certificate_id', $duplicate->certificate_id)
                ->whereNull('invalidated_at')
                ->where('id', '!=', $duplicate->keep_id)
                ->update([
                    'invalidated_at' => now(),
                    'invalidation_reason' => 'Duplicidade de emissão corrigida automaticamente.',
                    'active_flag' => null,
                ]);
        }

        Schema::table('certificate_users', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'team_id', 'certificate_id', 'active_flag'],
                'certificate_users_active_unique',
            );
        });
    }
};
