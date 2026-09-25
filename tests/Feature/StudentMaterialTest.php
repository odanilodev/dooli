<?php

namespace Tests\Feature;

use App\Enums\GeneralStatus;
use App\Models\Attachment;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class StudentMaterialTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
        Storage::fake('materials');
    }

    /**
     * @return array{course: int, document: Document, attachment: Attachment}
     */
    private function courseWithMaterials(int $companyId): array
    {
        $course = $this->buildCourse($companyId);
        $this->enroll($this->student, $course['course'], $companyId);

        $document = Document::query()->create([
            'company_id' => $companyId,
            'path' => 'documents/apostila.pdf',
            'status' => GeneralStatus::Active->value,
        ]);

        $attachment = Attachment::query()->create([
            'company_id' => $companyId,
            'path' => 'attachments/exercicio.zip',
            'status' => GeneralStatus::Active->value,
        ]);

        $course['lesson']->update(['document_id' => $document->id]);
        $course['lesson']->attachments()->attach($attachment->id, [
            'company_id' => $companyId,
            'status' => GeneralStatus::Active->value,
            'sort_order' => 1,
        ]);

        Storage::disk('materials')->put($companyId.'/documents/apostila.pdf', 'pdf-content');
        Storage::disk('materials')->put($companyId.'/attachments/exercicio.zip', 'zip-content');

        return [
            'course' => $course['course']->id,
            'document' => $document,
            'attachment' => $attachment,
        ];
    }

    public function test_enrolled_student_can_download_a_document(): void
    {
        $data = $this->courseWithMaterials($this->company->id);

        $response = $this->withToken($this->apiLogin($this->student))
            ->get("/api/v1/me/courses/{$data['course']}/documents/{$data['document']->id}");

        $response->assertOk();
        $this->assertSame('pdf-content', $response->streamedContent());
    }

    public function test_enrolled_student_can_download_an_attachment(): void
    {
        $data = $this->courseWithMaterials($this->company->id);

        $response = $this->withToken($this->apiLogin($this->student))
            ->get("/api/v1/me/courses/{$data['course']}/attachments/{$data['attachment']->id}");

        $response->assertOk();
        $this->assertSame('zip-content', $response->streamedContent());
    }

    public function test_student_cannot_download_material_without_enrollment(): void
    {
        $data = $this->courseWithMaterials($this->company->id);

        $other = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);

        $this->withToken($this->apiLogin($other))
            ->get("/api/v1/me/courses/{$data['course']}/documents/{$data['document']->id}")
            ->assertStatus(403);
    }

    public function test_material_from_another_course_is_not_found(): void
    {
        $first = $this->courseWithMaterials($this->company->id);

        $second = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $second['course'], $this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->get("/api/v1/me/courses/{$second['course']->id}/documents/{$first['document']->id}")
            ->assertStatus(404);
    }

    public function test_missing_file_returns_not_found(): void
    {
        $data = $this->courseWithMaterials($this->company->id);

        Storage::disk('materials')->delete($this->company->id.'/documents/apostila.pdf');

        $this->withToken($this->apiLogin($this->student))
            ->get("/api/v1/me/courses/{$data['course']}/documents/{$data['document']->id}")
            ->assertStatus(404);
    }
}
