<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MaterialService
{
    public function __construct(private readonly StudentCourseService $studentCourses) {}

    /**
     * Streams a lesson document for a student enrolled in the course.
     */
    public function downloadDocument(User $user, int $courseId, int $documentId): Response
    {
        $this->studentCourses->assertAccess($user, $courseId);

        $document = Document::query()
            ->whereKey($documentId)
            ->whereHas('lessons.module.courses', fn ($q) => $q->where('courses.id', $courseId))
            ->first();

        abort_unless($document, 404, 'Documento não encontrado.');

        return $this->download($document->company_id, $document->path);
    }

    /**
     * Streams a lesson attachment for a student enrolled in the course.
     */
    public function downloadAttachment(User $user, int $courseId, int $attachmentId): Response
    {
        $this->studentCourses->assertAccess($user, $courseId);

        $attachment = Attachment::query()
            ->whereKey($attachmentId)
            ->whereHas('lessons.module.courses', fn ($q) => $q->where('courses.id', $courseId))
            ->first();

        abort_unless($attachment, 404, 'Anexo não encontrado.');

        return $this->download($attachment->company_id, $attachment->path);
    }

    private function download(int $companyId, string $path): Response
    {
        $relativePath = $companyId.'/'.$this->safePath($path);

        $disk = Storage::disk('materials');

        abort_unless($disk->exists($relativePath), 404, 'Arquivo não encontrado.');

        return $disk->download($relativePath);
    }

    /**
     * Normalises the stored path and blocks directory traversal so a stored
     * path can never escape the company folder.
     */
    private function safePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        abort_if($path === '' || str_contains($path, '..'), 404, 'Arquivo não encontrado.');

        return $path;
    }
}
