<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class CourseManagementTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
        Storage::fake('public');
    }

    public function test_admin_can_create_a_course_with_an_uploaded_image(): void
    {
        $response = $this->withToken($this->apiLogin($this->admin))
            ->post('/api/v1/admin/courses', [
                'name' => 'Curso com capa',
                'image_file' => UploadedFile::fake()->image('capa.png', 800, 450),
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Curso com capa');

        $course = Course::query()->firstOrFail();

        $this->assertNotNull($course->image);
        Storage::disk('public')->assertExists($course->image);
        $this->assertSame(Storage::disk('public')->url($course->image), $response->json('data.image_url'));
    }

    public function test_admin_can_replace_the_course_image_and_the_old_file_is_removed(): void
    {
        Storage::disk('public')->put("courses/{$this->company->id}/old.jpg", 'old');

        $course = Course::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Curso',
            'image' => "courses/{$this->company->id}/old.jpg",
            'status' => 'active',
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->post("/api/v1/admin/courses/{$course->id}", [
                '_method' => 'PUT',
                'image_file' => UploadedFile::fake()->image('new.png', 640, 360),
            ])
            ->assertOk();

        $course->refresh();

        $this->assertNotSame("courses/{$this->company->id}/old.jpg", $course->image);
        Storage::disk('public')->assertMissing("courses/{$this->company->id}/old.jpg");
        Storage::disk('public')->assertExists($course->image);
    }

    public function test_course_image_file_must_be_a_valid_image(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->post('/api/v1/admin/courses', [
                'name' => 'Inválido',
                'image_file' => UploadedFile::fake()->create('arquivo.txt', 1, 'text/plain'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image_file');
    }

    public function test_deleting_a_course_removes_the_image_file(): void
    {
        Storage::disk('public')->put("courses/{$this->company->id}/gone.jpg", 'x');

        $course = Course::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Curso',
            'image' => "courses/{$this->company->id}/gone.jpg",
            'status' => 'active',
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/courses/{$course->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
        Storage::disk('public')->assertMissing("courses/{$this->company->id}/gone.jpg");
    }

    public function test_external_course_image_url_is_returned_as_is(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/courses', [
                'name' => 'Curso externo',
                'image' => 'https://cdn.example.com/capa.jpg',
            ])
            ->assertCreated()
            ->assertJsonPath('data.image_url', 'https://cdn.example.com/capa.jpg');
    }

    public function test_admin_can_create_a_course(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/courses', ['name' => 'Curso de Laravel'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Curso de Laravel');
    }

    public function test_admin_can_publish_and_unpublish(): void
    {
        $token = $this->apiLogin($this->admin);

        $id = $this->withToken($token)
            ->postJson('/api/v1/admin/courses', ['name' => 'Curso'])
            ->json('data.id');

        $this->withToken($token)
            ->postJson("/api/v1/admin/courses/{$id}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->withToken($token)
            ->postJson("/api/v1/admin/courses/{$id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_admin_can_update_and_delete_a_course(): void
    {
        $token = $this->apiLogin($this->admin);

        $id = $this->withToken($token)
            ->postJson('/api/v1/admin/courses', ['name' => 'Antigo'])
            ->json('data.id');

        $this->withToken($token)
            ->putJson("/api/v1/admin/courses/{$id}", ['name' => 'Novo'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Novo');

        $this->withToken($token)
            ->deleteJson("/api/v1/admin/courses/{$id}")
            ->assertNoContent();
    }

    public function test_store_requires_valid_payload(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/courses', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_admin_can_attach_materials_to_a_lesson(): void
    {
        $data = $this->buildCourse($this->company->id);

        $attachment = Attachment::query()->create([
            'company_id' => $this->company->id,
            'path' => 'materiais/aula.pdf',
        ]);

        $token = $this->apiLogin($this->admin);

        $lessonId = $this->withToken($token)
            ->postJson('/api/v1/admin/lessons', [
                'name' => 'Aula com anexo',
                'topic_id' => $data['topic']->id,
                'attachment_ids' => [$attachment->id],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('lesson_attachments', [
            'company_id' => $this->company->id,
            'lesson_id' => $lessonId,
            'attachment_id' => $attachment->id,
            'status' => 'active',
        ]);

        $this->withToken($token)
            ->putJson("/api/v1/admin/lessons/{$lessonId}", ['attachment_ids' => []])
            ->assertOk();

        $this->assertDatabaseMissing('lesson_attachments', [
            'lesson_id' => $lessonId,
            'attachment_id' => $attachment->id,
        ]);
    }
}
