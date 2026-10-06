<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MaterialService
{
    public function __construct(private readonly StudentCourseService $studentCourses) {}

    /**
     * Stores an uploaded document on the private materials disk and returns the
     * path relative to the company folder (as expected by the download flow).
     */
    public function storeDocumentFile(UploadedFile $file, int $companyId): string
    {
        $name = Str::random(12).'-'.$this->safeName($file->getClientOriginalName());
        $relative = 'documents/'.$name;

        Storage::disk('materials')->putFileAs((string) $companyId, $file, $relative);

        return $relative;
    }

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

        if (! empty($document->url)) {
            return new RedirectResponse($document->url);
        }

        return $this->download($document->company_id, (string) $document->path);
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

    /**
     * Keeps only a safe file name (no folders, no traversal) for stored uploads.
     */
    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^A-Za-z0-9._-]/', '-', $name) ?? 'arquivo';

        return ltrim($name, '.') ?: 'arquivo';
    }
}
