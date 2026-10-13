<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AdminDocumentTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
        Storage::fake('materials');
    }

    public function test_admin_can_upload_a_document_file(): void
    {
        $response = $this->withToken($this->apiLogin($this->admin))
            ->post('/api/v1/admin/documents', [
                'file' => UploadedFile::fake()->create('apostila.pdf', 120, 'application/pdf'),
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.url', null);

        $document = Document::query()->firstOrFail();

        $this->assertSame($this->company->id, (int) $document->company_id);
        $this->assertNull($document->url);
        $this->assertStringStartsWith('documents/', $document->path);
        $this->assertStringContainsString('apostila.pdf', $document->path);
        Storage::disk('materials')->assertExists($this->company->id.'/'.$document->path);

        $this->assertSame($document->path, $response->json('data.path'));
    }

    public function test_admin_can_create_a_document_from_a_link(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->post('/api/v1/admin/documents', [
                'url' => 'https://example.com/apostila.pdf',
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.url', 'https://example.com/apostila.pdf')
            ->assertJsonPath('data.path', null);

        $this->assertDatabaseHas('documents', [
            'company_id' => $this->company->id,
            'url' => 'https://example.com/apostila.pdf',
            'path' => null,
        ]);
    }

    public function test_oversized_document_file_is_rejected_with_a_clear_message(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->post('/api/v1/admin/documents', [
                'file' => UploadedFile::fake()->create('apostila-grande.pdf', 21000, 'application/pdf'),
                'status' => 'active',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', 'O arquivo é muito grande. O tamanho máximo é de 20 MB.');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_document_requires_a_file_path_or_link(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/documents', ['status' => 'active'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['path', 'url']);
    }

    public function test_admin_can_search_documents_by_path_or_url(): void
    {
        Document::create(['company_id' => $this->company->id, 'path' => 'documents/alpha-handbook.pdf', 'status' => 'active']);
        Document::create(['company_id' => $this->company->id, 'url' => 'https://example.com/beta-guide.pdf', 'status' => 'active']);
        Document::create(['company_id' => $this->company->id, 'path' => 'documents/gamma.pdf', 'status' => 'active']);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/documents?search=handbook')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.path', 'documents/alpha-handbook.pdf');

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/documents?search=beta')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.url', 'https://example.com/beta-guide.pdf');
    }
}
