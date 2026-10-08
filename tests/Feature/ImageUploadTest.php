<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Module;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
        Storage::fake('public');
    }

    public function test_admin_can_upload_a_module_image(): void
    {
        $response = $this->withToken($this->apiLogin($this->admin))
            ->post('/api/v1/admin/modules', [
                'name' => 'Módulo com imagem',
                'image_file' => UploadedFile::fake()->image('modulo.png', 640, 360),
            ])
            ->assertCreated();

        $module = Module::query()->firstOrFail();

        $this->assertNotNull($module->image);
        Storage::disk('public')->assertExists($module->image);
        $this->assertSame(Storage::disk('public')->url($module->image), $response->json('data.image_url'));
    }

    public function test_replacing_a_module_image_removes_the_old_file(): void
    {
        Storage::disk('public')->put('modules/1/old.jpg', 'old');

        $module = Module::factory()->create([
            'company_id' => $this->company->id,
            'image' => 'modules/1/old.jpg',
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->post("/api/v1/admin/modules/{$module->id}", [
                '_method' => 'PUT',
                'name' => 'Módulo atualizado',
                'image_file' => UploadedFile::fake()->image('novo.png', 640, 360),
            ])
            ->assertOk();

        $module->refresh();

        $this->assertNotSame('modules/1/old.jpg', $module->image);
        Storage::disk('public')->assertMissing('modules/1/old.jpg');
        Storage::disk('public')->assertExists($module->image);
    }

    public function test_admin_can_upload_a_video_image(): void
    {
        $response = $this->withToken($this->apiLogin($this->admin))
            ->post('/api/v1/admin/videos', [
                'title' => 'Vídeo com capa',
                'image_file' => UploadedFile::fake()->image('capa.png', 640, 360),
            ])
            ->assertCreated();

        $video = Video::query()->firstOrFail();

        $this->assertNotNull($video->image);
        Storage::disk('public')->assertExists($video->image);
        $this->assertSame(Storage::disk('public')->url($video->image), $response->json('data.image_url'));
    }

    public function test_admin_can_upload_a_document_image(): void
    {
        $document = Document::query()->create([
            'company_id' => $this->company->id,
            'url' => 'https://example.com/apostila.pdf',
            'status' => 'active',
        ]);

        $response = $this->withToken($this->apiLogin($this->admin))
            ->post("/api/v1/admin/documents/{$document->id}", [
                '_method' => 'PUT',
                'image_file' => UploadedFile::fake()->image('doc.png', 640, 360),
            ])
            ->assertOk();

        $document->refresh();

        $this->assertNotNull($document->image);
        Storage::disk('public')->assertExists($document->image);
        $this->assertSame(Storage::disk('public')->url($document->image), $response->json('data.image_url'));
    }

    public function test_image_file_must_be_a_valid_image(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->post('/api/v1/admin/modules', [
                'name' => 'Inválido',
                'image_file' => UploadedFile::fake()->create('arquivo.txt', 1, 'text/plain'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image_file');
    }
}
