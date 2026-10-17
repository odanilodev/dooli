<?php

namespace App\Console\Commands;

use App\Models\CertificateUser;
use App\Services\DatalogService;
use App\Support\CompanyContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreRecalculatedCertificates extends Command
{
    /**
     * Reason written by the removed automatic recalculation. It is exclusive
     * to that behaviour, so it safely identifies certificates that were
     * invalidated simply because new lessons were added after completion.
     */
    public const AUTOMATIC_REASON = 'Novas aulas adicionadas ao curso após a conclusão.';

    protected $signature = 'certificates:restore-recalculated {--dry-run : List the affected certificates without changing anything}';

    protected $description = 'Restore certificates invalidated automatically when lessons were added after completion.';

    public function handle(DatalogService $audit, CompanyContext $context): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = CertificateUser::query()
            ->with('certificate')
            ->whereNotNull('invalidated_at')
            ->where('invalidation_reason', self::AUTOMATIC_REASON)
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Nenhum certificado revogado pelo recálculo automático foi encontrado.');

            return self::SUCCESS;
        }

        $validKeys = $this->validKeys();
        $restored = 0;
        $skipped = 0;

        foreach ($candidates as $candidate) {
            $courseId = $candidate->certificate?->course_id;
            $key = $candidate->user_id.':'.$courseId.':'.$candidate->team_id;

            if ($courseId === null || isset($validKeys[$key])) {
                $skipped++;
                $this->line("[ignorado] certificado {$candidate->id} (user {$candidate->user_id} já possui certificado válido).");

                continue;
            }

            $this->line(($dryRun ? '[dry-run] ' : '')."[restaurar] certificado {$candidate->id} (user {$candidate->user_id}, curso {$courseId}).");

            if ($dryRun) {
                $restored++;

                continue;
            }

            $context->set((int) $candidate->company_id);

            $candidate->forceFill([
                'invalidated_at' => null,
                'invalidation_reason' => null,
            ])->save();

            $audit->log(
                'certificate',
                'restore',
                'certificate_user',
                "Certificado {$candidate->id} restaurado após revogação automática indevida.",
                $candidate->user_id,
            );

            $context->forget();

            $validKeys[$key] = true;
            $restored++;
        }

        $this->info(sprintf(
            '%s%d certificado(s) %s, %d ignorado(s).',
            $dryRun ? '[dry-run] ' : '',
            $restored,
            $dryRun ? 'a restaurar' : 'restaurado(s)',
            $skipped,
        ));

        return self::SUCCESS;
    }

    /**
     * Existing valid certificates keyed as "userId:courseId:teamId".
     *
     * @return array<string, true>
     */
    private function validKeys(): array
    {
        return DB::table('certificate_users')
            ->join('certificates', 'certificates.id', '=', 'certificate_users.certificate_id')
            ->whereNull('certificate_users.invalidated_at')
            ->select('certificate_users.user_id', 'certificate_users.team_id', 'certificates.course_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->user_id.':'.$row->course_id.':'.$row->team_id => true])
            ->all();
    }
}
