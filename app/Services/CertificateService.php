<?php

namespace App\Services;

use App\Enums\ActionCode;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\CertificateNotAvailableException;
use App\Exceptions\ResourceNotFoundException;
use App\Models\Certificate;
use App\Models\CertificateUser;
use App\Models\User;
use App\Repositories\ActionRepository;
use App\Repositories\CertificateRepository;
use App\Repositories\CertificateUserRepository;
use App\Repositories\CourseCompletionRepository;
use App\Repositories\LessonRepository;
use App\Repositories\ModuleRepository;
use App\Repositories\TimelineRepository;
use App\Support\CompanyContext;
use App\Support\ImageStorage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CertificateService
{
    /**
     * @var list<string>
     */
    private const LAYOUT_FIELDS = [
        'eyebrow', 'title', 'body', 'modules', 'signature', 'signature_line',
        'signatory_name', 'signatory_title', 'code', 'qr',
    ];

    /**
     * @var list<string>
     */
    private const LAYOUT_ALIGNMENTS = ['left', 'center', 'right'];

    /**
     * @var list<string>
     */
    private const MODULES_PLACEMENTS = ['none', 'front', 'extra', 'back'];

    /**
     * @var list<string>
     */
    private const MODULES_LAYOUT_FIELDS = ['heading', 'list', 'qr'];

    private const QR_PLACEMENTS = ['front', 'modules', 'both'];

    /**
     * @var list<string>
     */
    private const MODULES_LIST_FIELDS = ['modules', 'list'];

    /**
     * @var list<string>
     */
    private const MODULES_FORMATS = ['numbered', 'bulleted', 'plain', 'inline'];

    /**
     * @var list<string>
     */
    private const MODULES_SEPARATORS = ['bullet', 'comma', 'pipe', 'dash'];

    public function __construct(
        private readonly CertificateRepository $certificates,
        private readonly CertificateUserRepository $certificateUsers,
        private readonly LessonRepository $lessons,
        private readonly ModuleRepository $modules,
        private readonly TimelineRepository $timeline,
        private readonly ActionRepository $actions,
        private readonly CourseCompletionRepository $completions,
        private readonly NotificationService $notifications,
        private readonly DatalogService $audit,
        private readonly ImageStorage $images,
        private readonly CompanyContext $context,
    ) {}

    /**
     * Creates a certificate template, storing any uploaded images.
     */
    public function createTemplate(
        array $data,
        ?UploadedFile $background = null,
        ?UploadedFile $signature = null,
        ?UploadedFile $modulesBackground = null,
        bool $removeModulesBackground = false,
        bool $removeBackground = false,
        bool $removeSignature = false,
    ): Certificate {
        $companyId = (int) ($data['company_id'] ?? $this->context->id() ?? 0);
        $copyFrom = isset($data['copy_from']) ? (int) $data['copy_from'] : null;
        $source = $copyFrom ? $this->certificates->query()->find($copyFrom) : null;

        unset($data['copy_from']);

        if ($background) {
            $data['background'] = $this->images->store($background, 'certificates', $companyId);
        } elseif ($source && ! $removeBackground) {
            $data['background'] = $this->images->copy($source->background, 'certificates', $companyId);
        }

        if ($signature) {
            $data['signature'] = $this->images->store($signature, 'certificates', $companyId);
        } elseif ($source && ! $removeSignature) {
            $data['signature'] = $this->images->copy($source->signature, 'certificates', $companyId);
        }

        if ($modulesBackground) {
            $data['modules_background'] = $this->images->store($modulesBackground, 'certificates', $companyId);
        } elseif ($source && ! $removeModulesBackground) {
            $data['modules_background'] = $this->images->copy($source->modules_background, 'certificates', $companyId);
        }

        if (array_key_exists('layout', $data)) {
            $data['layout'] = is_array($data['layout']) ? $this->normalizeLayout($data['layout']) : null;
        } elseif ($source?->layout) {
            $data['layout'] = $source->layout;
        }

        if (array_key_exists('modules_layout', $data)) {
            $data['modules_layout'] = is_array($data['modules_layout'])
                ? $this->normalizeModulesLayout($data['modules_layout'])
                : null;
        } elseif ($source?->modules_layout) {
            $data['modules_layout'] = $source->modules_layout;
        }

        $data['modules_placement'] = $this->normalizeModulesPlacement(
            $data['modules_placement'] ?? $source?->modules_placement,
        );

        if (array_key_exists('modules_title', $data)) {
            $data['modules_title'] = $this->normalizeModulesTitle($data['modules_title']);
        } elseif ($source) {
            $data['modules_title'] = $source->modules_title;
        }

        if (array_key_exists('qr_enabled', $data)) {
            $data['qr_enabled'] = filter_var($data['qr_enabled'], FILTER_VALIDATE_BOOLEAN);
        } elseif ($source) {
            $data['qr_enabled'] = (bool) $source->qr_enabled;
        }

        $data['qr_placement'] = $this->normalizeQrPlacement(
            $data['qr_placement'] ?? $source?->qr_placement,
        );

        $certificate = $this->certificates->create($data);

        $this->audit->log('certificate', 'create', 'certificate', "Certificado criado: {$certificate->name}");

        return $certificate;
    }

    /**
     * Updates a certificate template, replacing any uploaded images.
     */
    public function updateTemplate(
        Certificate $certificate,
        array $data,
        ?UploadedFile $background = null,
        ?UploadedFile $signature = null,
        ?UploadedFile $modulesBackground = null,
        bool $removeModulesBackground = false,
        bool $removeBackground = false,
        bool $removeSignature = false,
    ): Certificate {
        if ($certificate->certificateUsers()->exists()) {
            throw new BusinessRuleException(
                'Modelos com certificados emitidos não podem ser editados. Crie uma nova versão e transfira os alunos.',
            );
        }

        if ($background) {
            $this->images->delete($certificate->background);
            $data['background'] = $this->images->store($background, 'certificates', (int) $certificate->company_id);
        } elseif ($removeBackground) {
            $this->images->delete($certificate->background);
            $data['background'] = null;
        }

        if ($signature) {
            $this->images->delete($certificate->signature);
            $data['signature'] = $this->images->store($signature, 'certificates', (int) $certificate->company_id);
        } elseif ($removeSignature) {
            $this->images->delete($certificate->signature);
            $data['signature'] = null;
        }

        if ($modulesBackground) {
            $this->images->delete($certificate->modules_background);
            $data['modules_background'] = $this->images->store($modulesBackground, 'certificates', (int) $certificate->company_id);
        } elseif ($removeModulesBackground) {
            $this->images->delete($certificate->modules_background);
            $data['modules_background'] = null;
        }

        if (array_key_exists('layout', $data)) {
            $data['layout'] = is_array($data['layout']) ? $this->normalizeLayout($data['layout']) : null;
        }

        if (array_key_exists('modules_layout', $data)) {
            $data['modules_layout'] = is_array($data['modules_layout'])
                ? $this->normalizeModulesLayout($data['modules_layout'])
                : null;
        }

        if (array_key_exists('modules_placement', $data)) {
            $data['modules_placement'] = $this->normalizeModulesPlacement($data['modules_placement']);
        }

        if (array_key_exists('modules_title', $data)) {
            $data['modules_title'] = $this->normalizeModulesTitle($data['modules_title']);
        }

        if (array_key_exists('qr_enabled', $data)) {
            $data['qr_enabled'] = filter_var($data['qr_enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('qr_placement', $data)) {
            $data['qr_placement'] = $this->normalizeQrPlacement($data['qr_placement']);
        }

        $certificate = $this->certificates->update($certificate, $data);

        $this->audit->log('certificate', 'update', 'certificate', "Certificado atualizado: {$certificate->name}");

        return $certificate;
    }

    /**
     * Deletes a certificate template and its uploaded images.
     */
    public function deleteTemplate(Certificate $certificate): void
    {
        if ($certificate->certificateUsers()->exists()) {
            throw new BusinessRuleException('Não é possível excluir um certificado já emitido para alunos.');
        }

        $this->images->delete($certificate->background);
        $this->images->delete($certificate->signature);
        $this->images->delete($certificate->modules_background);

        $this->certificates->delete($certificate);

        $this->audit->log('certificate', 'delete', 'certificate', "Certificado excluído: {$certificate->name}");
    }

    /**
     * Moves every valid certificate from one template to another template of
     * the same course. Code, issue date, lesson count and module snapshot are
     * preserved; only the template reference changes. Historical rows
     * (replaced/revoked) are intentionally left untouched.
     *
     * @return array{source_certificate_id: int, target_certificate_id: int, transferred: int, skipped: int}
     */
    public function transferIssued(Certificate $source, Certificate $target): array
    {
        if ($source->is($target)) {
            throw new BusinessRuleException('Escolha um modelo de destino diferente do modelo de origem.');
        }

        if ((int) $source->course_id !== (int) $target->course_id) {
            throw new BusinessRuleException('A transferência só é permitida entre modelos do mesmo curso.');
        }

        $result = DB::transaction(function () use ($source, $target) {
            $targetKeys = $this->certificateUsers->validUserTeamKeys((int) $target->id);
            $ids = [];
            $skipped = 0;

            foreach ($this->certificateUsers->validForCertificate((int) $source->id, true) as $certificate) {
                $key = $certificate->user_id.':'.$certificate->team_id;

                if (isset($targetKeys[$key])) {
                    $skipped++;

                    continue;
                }

                $ids[] = (int) $certificate->id;
            }

            return [
                'transferred' => $this->certificateUsers->moveToCertificate($ids, (int) $target->id),
                'skipped' => $skipped,
            ];
        });

        $this->audit->log(
            'certificate',
            'transfer_issued',
            'certificate',
            "Certificados transferidos: modelo {$source->id} -> {$target->id} "
                ."({$result['transferred']} transferidos, {$result['skipped']} ignorados).",
        );

        return [
            'source_certificate_id' => (int) $source->id,
            'target_certificate_id' => (int) $target->id,
            'transferred' => $result['transferred'],
            'skipped' => $result['skipped'],
        ];
    }

    /**
     * Issues the first certificate when the student finishes the course, and
     * replaces it when new lessons were added and the student finished them.
     * A manually revoked certificate is never reissued automatically. Returns
     * null when no certificate should be issued.
     */
    public function issueIfEligible(User $user, int $courseId, int $teamId): ?CertificateUser
    {
        $latest = $this->certificateUsers->findForUserCourseTeam($user->id, $courseId, $teamId);

        if ($latest?->isInvalidated()) {
            return null;
        }

        $certificate = $this->certificates->findForCourse($courseId);

        if (! $certificate) {
            return null;
        }

        if ($latest !== null) {
            $total = $this->lessons->countPublishedByCourse($courseId);

            if ($total === 0 || $total <= $latest->lessons_count) {
                return $latest;
            }

            $completedLessonIds = $this->completedLessonIds($user, $courseId, $teamId);

            if (count($completedLessonIds) < $total) {
                return $latest;
            }

            return $this->replace($user, $courseId, $teamId, $certificate, $latest, $total, $completedLessonIds);
        }

        if (! $this->isCompleted($user->id, $courseId, $teamId)) {
            return null;
        }

        $completion = $this->completions->findForUserCourseTeam($user->id, $courseId, $teamId);
        $total = (int) ($completion?->required_lessons ?? $this->lessons->countPublishedByCourse($courseId));
        $lessonIds = (array) ($completion?->lesson_ids ?? $this->completedLessonIds($user, $courseId, $teamId));

        return $this->issue($user, $courseId, $teamId, $certificate, $total, $lessonIds);
    }

    /**
     * Issues the first certificate to every already-completed student of a
     * course that has no certificate yet. Used when a template becomes
     * available so historical completions are certified retroactively.
     * Students whose certificate was revoked (manually) are never re-issued.
     */
    public function issuePendingForCourse(int $courseId, int $companyId): int
    {
        $certificate = $this->certificates->findForCourse($courseId);

        if (! $certificate) {
            return 0;
        }

        $issued = 0;

        foreach ($this->completions->forCourse($courseId, $companyId) as $completion) {
            $userId = (int) $completion->user_id;
            $teamId = (int) $completion->team_id;

            if ($this->certificateUsers->findForUserCourseTeam($userId, $courseId, $teamId)) {
                continue;
            }

            $this->certificateUsers->create([
                'code' => (string) Str::uuid(),
                'company_id' => $companyId,
                'user_id' => $userId,
                'team_id' => $teamId,
                'certificate_id' => $certificate->id,
                'lessons_count' => (int) $completion->required_lessons,
                'modules' => $this->modules->snapshotForCourse(
                    $courseId,
                    (array) ($completion->lesson_ids ?? []),
                ),
            ]);

            $issued++;
        }

        if ($issued > 0) {
            $this->audit->log(
                'certificate',
                'issue_pending',
                'course',
                "{$issued} certificado(s) emitido(s) retroativamente para o curso {$courseId}.",
            );
        }

        return $issued;
    }

    /**
     * True when the student completed the course (historical record) or the
     * current lesson counts already satisfy the requirement.
     */
    private function isCompleted(int $userId, int $courseId, int $teamId): bool
    {
        if ($this->completions->findForUserCourseTeam($userId, $courseId, $teamId) !== null) {
            return true;
        }

        [$completed, $total] = $this->completionCounts($userId, $courseId, $teamId);

        return $total > 0 && $completed >= $total;
    }

    private function issue(
        User $user,
        int $courseId,
        int $teamId,
        Certificate $certificate,
        int $total,
        array $lessonIds,
    ): CertificateUser {
        $issued = $this->certificateUsers->create([
            'code' => (string) Str::uuid(),
            'user_id' => $user->id,
            'team_id' => $teamId,
            'certificate_id' => $certificate->id,
            'lessons_count' => $total,
            'modules' => $this->modules->snapshotForCourse($courseId, $lessonIds),
        ]);

        $this->audit->log('certificate', 'issue', 'certificate_user', "Certificado emitido para user {$user->id}", $user->id);

        return $issued;
    }

    /**
     * Replaces the current valid certificate with a new milestone. The old row
     * stays in history as invalidated; only the new one remains valid.
     *
     * @param  array<int, int>  $lessonIds
     */
    private function replace(
        User $user,
        int $courseId,
        int $teamId,
        Certificate $certificate,
        CertificateUser $current,
        int $total,
        array $lessonIds,
    ): CertificateUser {
        try {
            $replacement = DB::transaction(function () use ($user, $courseId, $teamId, $certificate, $current, $total, $lessonIds) {
                $locked = $this->certificateUsers->findValidForUserCourseTeam($user->id, $courseId, $teamId, true);

                if (! $locked) {
                    return $this->certificateUsers->findValidForUserCourseTeam($user->id, $courseId, $teamId) ?? $current;
                }

                if ($locked->lessons_count >= $total) {
                    return $locked;
                }

                $this->certificateUsers->invalidateForReplacement($user->id, $courseId, $teamId);

                $issued = $this->certificateUsers->create([
                    'code' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'team_id' => $teamId,
                    'certificate_id' => $certificate->id,
                    'lessons_count' => $total,
                    'modules' => $this->modules->snapshotForCourse($courseId, $lessonIds),
                ]);

                $this->completions->markCompleted(
                    (int) $user->id,
                    $teamId,
                    $courseId,
                    (int) $user->company_id,
                    $total,
                    $lessonIds,
                );

                return $issued;
            });
        } catch (QueryException) {
            $replacement = $this->certificateUsers->findValidForUserCourseTeam($user->id, $courseId, $teamId) ?? $current;
        }

        if ($replacement->wasRecentlyCreated) {
            $this->audit->log(
                'certificate',
                'replace',
                'certificate_user',
                "Certificado substituído: {$current->id} -> {$replacement->id}",
                $user->id,
            );
        }

        return $replacement;
    }

    /**
     * @return array<int, int>
     */
    private function completedLessonIds(User $user, int $courseId, int $teamId): array
    {
        $completedAction = $this->actions->findByCode(ActionCode::Completed->value);

        if (! $completedAction) {
            return [];
        }

        return array_map(
            'intval',
            $this->timeline->completedLessonIds($user->id, $courseId, $teamId, $completedAction->id),
        );
    }

    /**
     * Explicit generation endpoint. Fails when criteria are not met.
     */
    public function generate(User $user, int $courseId, int $teamId): CertificateUser
    {
        $issued = $this->issueIfEligible($user, $courseId, $teamId);

        if (! $issued) {
            throw new CertificateNotAvailableException;
        }

        return $issued->load(['certificate.course', 'team']);
    }

    /**
     * @return Collection<int, CertificateUser>
     */
    public function listForUser(User $user): Collection
    {
        return $this->certificateUsers->forUser($user->id);
    }

    /**
     * Invalidates an issued certificate (admin action).
     */
    public function revoke(CertificateUser $certificateUser, ?string $reason = null): CertificateUser
    {
        $revoked = $this->certificateUsers->update($certificateUser, [
            'invalidated_at' => now(),
            'invalidation_reason' => $reason,
        ]);

        $this->audit->log(
            'certificate',
            'revoke',
            'certificate_user',
            "Certificado invalidado: {$certificateUser->id}",
            $certificateUser->user_id,
        );

        return $revoked->load(['certificate.course', 'user', 'team']);
    }

    public function verify(string $code): CertificateUser
    {
        $certificate = $this->certificateUsers->findValidByCode($code);

        if (! $certificate) {
            throw new ResourceNotFoundException('Certificado não encontrado ou inválido.');
        }

        return $certificate;
    }

    public function findPublicCertificate(string $code): ?CertificateUser
    {
        return $this->certificateUsers->findByCode($code);
    }

    /**
     * Current valid certificate for the same enrollment, used to redirect
     * links/QR codes printed on automatically replaced certificates.
     */
    public function findCurrentReplacement(CertificateUser $certificate): ?CertificateUser
    {
        $courseId = (int) ($certificate->certificate?->course_id ?? 0);

        if (! $courseId) {
            return null;
        }

        return $this->certificateUsers->findValidForUserCourseTeam(
            (int) $certificate->user_id,
            $courseId,
            (int) $certificate->team_id,
        );
    }

    /**
     * @return array{0: int, 1: int} [completed, total]
     */
    public function completionCounts(int $userId, int $courseId, int $teamId): array
    {
        $total = $this->lessons->countPublishedByCourse($courseId);

        $completedAction = $this->actions->findByCode(ActionCode::Completed->value);

        $completed = $completedAction
            ? count($this->timeline->completedLessonIds($userId, $courseId, $teamId, $completedAction->id))
            : 0;

        return [$completed, $total];
    }

    /**
     * Keeps only the known certificate layout fields and clamps/sanitizes their values.
     *
     * @param  array<string, mixed>  $layout
     * @return array<string, array<string, mixed>>
     */
    private function normalizeLayout(array $layout): array
    {
        return $this->normalizeFields(
            $layout,
            self::LAYOUT_FIELDS,
            [
                'title' => [20, 90, 80.0],
                'body' => [20, 90, 80.0],
                'modules' => [20, 90, 80.0],
                'signature_line' => [5, 90, 18.0],
            ],
            ['qr' => 90.0],
        );
    }

    /**
     * Keeps only the known modules-page fields and clamps/sanitizes their values.
     *
     * @param  array<string, mixed>  $layout
     * @return array<string, array<string, mixed>>
     */
    private function normalizeModulesLayout(array $layout): array
    {
        return $this->normalizeFields(
            $layout,
            self::MODULES_LAYOUT_FIELDS,
            [
                'heading' => [20, 90, 80.0],
                'list' => [20, 90, 86.0],
            ],
            [
                'heading' => 28.0,
                'list' => 10.0,
                'qr' => 90.0,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $layout
     * @param  list<string>  $fields
     * @param  array<string, array{0: int, 1: int, 2: float}>  $widths
     * @param  array<string, float>  $sizeDefaults
     * @return array<string, array<string, mixed>>
     */
    private function normalizeFields(array $layout, array $fields, array $widths, array $sizeDefaults = []): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            $entry = $layout[$field] ?? null;

            if (! is_array($entry)) {
                continue;
            }

            $x = is_numeric($entry['x'] ?? null) ? (float) $entry['x'] : 50.0;
            $y = is_numeric($entry['y'] ?? null) ? (float) $entry['y'] : 50.0;
            $width = is_numeric($entry['width'] ?? null) ? (float) $entry['width'] : null;
            $size = is_numeric($entry['size'] ?? null)
                ? round((float) $entry['size'], 1)
                : ($sizeDefaults[$field] ?? 12.0);
            $widthRule = $widths[$field] ?? null;

            if ($widthRule !== null) {
                $width = $width === null
                    ? $widthRule[2]
                    : max($widthRule[0], min($widthRule[1], $width));
            } else {
                $width = null;
            }

            $color = is_string($entry['color'] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $entry['color']) === 1
                ? strtolower($entry['color'])
                : null;
            $font = is_string($entry['font'] ?? null) ? trim($entry['font']) : '';
            $align = in_array($entry['align'] ?? null, self::LAYOUT_ALIGNMENTS, true) ? $entry['align'] : 'center';

            $normalized[$field] = [
                'x' => round(max(0, min(100, $x)), 2),
                'y' => round(max(0, min(100, $y)), 2),
                'width' => $width === null ? null : round($width, 2),
                'color' => $color,
                'font' => $font !== '' ? mb_substr($font, 0, 80) : null,
                'size' => max(4, min(160, $size)),
                'align' => $align,
                'bold' => filter_var($entry['bold'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'italic' => filter_var($entry['italic'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];

            if (in_array($field, self::MODULES_LIST_FIELDS, true)) {
                $format = is_string($entry['format'] ?? null)
                    && in_array($entry['format'], self::MODULES_FORMATS, true)
                    ? $entry['format']
                    : 'numbered';
                $columns = is_numeric($entry['columns'] ?? null)
                    ? (int) round((float) $entry['columns'])
                    : 2;
                $separator = is_string($entry['separator'] ?? null)
                    && in_array($entry['separator'], self::MODULES_SEPARATORS, true)
                    ? $entry['separator']
                    : 'bullet';

                $normalized[$field]['format'] = $format;
                $normalized[$field]['columns'] = max(1, min(4, $columns));
                $normalized[$field]['separator'] = $separator;
            }
        }

        return $normalized;
    }

    private function normalizeModulesPlacement(mixed $placement): string
    {
        return is_string($placement) && in_array($placement, self::MODULES_PLACEMENTS, true)
            ? $placement
            : 'front';
    }

    private function normalizeQrPlacement(mixed $placement): string
    {
        return is_string($placement) && in_array($placement, self::QR_PLACEMENTS, true)
            ? $placement
            : 'front';
    }

    private function normalizeModulesTitle(mixed $title): ?string
    {
        if (! is_string($title)) {
            return null;
        }

        $title = trim($title);

        return $title !== '' ? mb_substr($title, 0, 255) : null;
    }
}
