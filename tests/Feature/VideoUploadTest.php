<?php

namespace Tests\Feature;

use App\Enums\VideoPlayer;
use App\Jobs\SyncVideoProviderStatusJob;
use App\Models\Video;
use App\Models\VideoPlatform;
use App\Services\VideoService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class VideoUploadTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    private function vdocipherPlatform(): VideoPlatform
    {
        return VideoPlatform::factory()->create([
            'company_id' => $this->company->id,
            'code' => VideoPlayer::VdoCipher->value,
            'credentials' => ['api_secret' => 'secret-123'],
        ]);
    }

    public function test_admin_can_import_video_from_url(): void
    {
        $this->vdocipherPlatform();

        Queue::fake();

        Http::fake([
            'dev.vdocipher.com/*' => Http::response(['videoId' => 'VD-IMPORT', 'status' => 'Queued']),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/videos/import-url', [
                'title' => 'Aula importada',
                'url' => 'https://cdn.example.com/lesson.mp4',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'VD-IMPORT')
            ->assertJsonPath('data.player', 'vdocipher')
            ->assertJsonPath('data.provider_status', 'Queued');

        $this->assertDatabaseHas('videos', [
            'company_id' => $this->company->id,
            'code' => 'VD-IMPORT',
            'provider_status' => 'Queued',
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/videos/importUrl')
            && $request->hasHeader('Authorization', 'Apisecret secret-123'));

        Queue::assertPushed(SyncVideoProviderStatusJob::class);
    }

    public function test_admin_can_request_upload_credentials(): void
    {
        $this->vdocipherPlatform();

        Http::fake([
            'dev.vdocipher.com/*' => Http::response([
                'videoId' => 'VD-UPLOAD',
                'clientPayload' => [
                    'uploadLink' => 'https://s3.example.com/bucket',
                    'policy' => 'POLICY',
                    'key' => 'KEY',
                ],
            ]),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/videos/upload-credentials', ['title' => 'Aula local'])
            ->assertCreated()
            ->assertJsonPath('data.video.code', 'VD-UPLOAD')
            ->assertJsonPath('data.video.provider_status', 'Pre-Upload')
            ->assertJsonPath('data.upload.uploadLink', 'https://s3.example.com/bucket');

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), '/api/videos?title=Aula+local')
            && $request->hasHeader('Authorization', 'Apisecret secret-123'));

        $this->assertDatabaseHas('videos', [
            'company_id' => $this->company->id,
            'code' => 'VD-UPLOAD',
            'provider_status' => 'Pre-Upload',
        ]);
    }

    public function test_upload_credentials_honors_status(): void
    {
        $this->vdocipherPlatform();

        Http::fake([
            'dev.vdocipher.com/*' => Http::response([
                'videoId' => 'VD-DRAFT',
                'clientPayload' => [
                    'uploadLink' => 'https://s3.example.com/bucket',
                    'policy' => 'POLICY',
                    'key' => 'KEY',
                ],
            ]),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/videos/upload-credentials', [
                'title' => 'Aula rascunho',
                'status' => 'inactive',
            ])
            ->assertCreated()
            ->assertJsonPath('data.video.status', 'inactive');

        $this->assertDatabaseHas('videos', ['code' => 'VD-DRAFT', 'status' => 'inactive']);
    }

    public function test_import_url_honors_status(): void
    {
        $this->vdocipherPlatform();

        Queue::fake();

        Http::fake([
            'dev.vdocipher.com/*' => Http::response(['videoId' => 'VD-DRAFT-2', 'status' => 'Queued']),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/videos/import-url', [
                'title' => 'Importada rascunho',
                'url' => 'https://cdn.example.com/x.mp4',
                'status' => 'inactive',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('videos', ['code' => 'VD-DRAFT-2', 'status' => 'inactive']);
    }

    public function test_sync_fills_duration_and_poster_when_ready(): void
    {
        $this->vdocipherPlatform();

        $video = Video::factory()->create([
            'company_id' => $this->company->id,
            'player' => VideoPlayer::VdoCipher->value,
            'code' => 'VD-READY',
            'time' => 0,
        ]);

        Http::fake([
            'dev.vdocipher.com/*' => Http::response([
                'status' => 'ready',
                'length' => 125,
                'posters' => [['posterUrl' => 'https://cdn.example.com/poster.jpg']],
            ]),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson("/api/v1/admin/videos/{$video->id}/sync")
            ->assertOk()
            ->assertJsonPath('data.provider_status', 'ready')
            ->assertJsonPath('data.time', 125)
            ->assertJsonPath('data.image', 'https://cdn.example.com/poster.jpg');

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'provider_status' => 'ready',
            'time' => 125,
        ]);
    }

    public function test_import_requires_configured_secret(): void
    {
        Http::fake();

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/videos/import-url', [
                'title' => 'Sem credencial',
                'url' => 'https://cdn.example.com/lesson.mp4',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business_rule');

        Http::assertNothingSent();
        $this->assertDatabaseCount('videos', 0);
    }

    public function test_sync_job_updates_video_without_request_context(): void
    {
        $this->vdocipherPlatform();

        $video = Video::factory()->create([
            'company_id' => $this->company->id,
            'player' => VideoPlayer::VdoCipher->value,
            'code' => 'VD-JOB',
            'time' => 0,
        ]);

        app(CompanyContext::class)->forget();

        Http::fake([
            'dev.vdocipher.com/*' => Http::response([
                'status' => 'ready',
                'length' => 90,
                'posters' => [['posterUrl' => 'https://cdn.example.com/job.jpg']],
            ]),
        ]);

        (new SyncVideoProviderStatusJob($video->id))->handle(app(VideoService::class));

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'provider_status' => 'ready',
            'time' => 90,
        ]);
    }

    public function test_provider_quota_error_is_surfaced(): void
    {
        $this->vdocipherPlatform();

        Http::fake([
            'dev.vdocipher.com/*' => Http::response([
                'message' => "You have reached the trial limit of 4 videos.\n        Either remove the previously uploaded videos or subscribe.",
            ], 403),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/videos/upload-credentials', ['title' => 'Aula'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business_rule')
            ->assertJsonPath('message', 'VdoCipher: You have reached the trial limit of 4 videos. Either remove the previously uploaded videos or subscribe.');

        $this->assertDatabaseCount('videos', 0);
    }

    public function test_invalid_secret_reports_friendly_message(): void
    {
        $this->vdocipherPlatform();

        Http::fake([
            'dev.vdocipher.com/*' => Http::response(['message' => 'unauthorized'], 401),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/videos/upload-credentials', ['title' => 'Aula'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'api_secret do VdoCipher inválido. Verifique em Plataformas de vídeo.');
    }

    public function test_deleting_video_also_deletes_it_on_provider(): void
    {
        $this->vdocipherPlatform();

        $video = Video::factory()->create([
            'company_id' => $this->company->id,
            'player' => VideoPlayer::VdoCipher->value,
            'code' => 'VD-DEL',
        ]);

        Http::fake([
            'dev.vdocipher.com/*' => Http::response('', 200),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/videos/{$video->id}")
            ->assertNoContent();

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), 'videos=VD-DEL'));

        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
    }

    public function test_provider_delete_failure_still_deletes_locally(): void
    {
        $this->vdocipherPlatform();

        $video = Video::factory()->create([
            'company_id' => $this->company->id,
            'player' => VideoPlayer::VdoCipher->value,
            'code' => 'VD-FAIL',
        ]);

        Http::fake([
            'dev.vdocipher.com/*' => Http::response(['message' => 'boom'], 500),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/videos/{$video->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
    }

    public function test_bulk_sync_updates_pending_videos(): void
    {
        $this->vdocipherPlatform();

        $ready = Video::factory()->create([
            'company_id' => $this->company->id,
            'player' => VideoPlayer::VdoCipher->value,
            'code' => 'VD-A',
            'provider_status' => null,
            'time' => 0,
        ]);

        $processing = Video::factory()->create([
            'company_id' => $this->company->id,
            'player' => VideoPlayer::VdoCipher->value,
            'code' => 'VD-B',
            'provider_status' => 'PRE-Upload',
        ]);

        $external = Video::factory()->create([
            'company_id' => $this->company->id,
            'player' => 'external',
            'code' => 'EXT-1',
        ]);

        Http::fake([
            '*VD-A*' => Http::response([
                'status' => 'Ready',
                'length' => 100,
                'posters' => [['posterUrl' => 'https://cdn.example.com/p.jpg']],
            ]),
            '*VD-B*' => Http::response(['status' => 'Processing']),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/videos/sync')
            ->assertOk()
            ->assertJsonPath('data.checked', 2)
            ->assertJsonPath('data.ready', 1)
            ->assertJsonPath('data.pending', 1)
            ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('videos', [
            'id' => $ready->id,
            'provider_status' => 'ready',
            'time' => 100,
            'image' => 'https://cdn.example.com/p.jpg',
        ]);

        $this->assertDatabaseHas('videos', [
            'id' => $processing->id,
            'provider_status' => 'processing',
        ]);

        $this->assertDatabaseHas('videos', ['id' => $external->id, 'provider_status' => null]);
    }

    public function test_bulk_sync_continues_when_provider_fails(): void
    {
        $this->vdocipherPlatform();

        Video::factory()->create([
            'company_id' => $this->company->id,
            'player' => VideoPlayer::VdoCipher->value,
            'code' => 'VD-X',
        ]);

        Http::fake([
            '*VD-X*' => Http::response(['message' => 'boom'], 500),
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/videos/sync')
            ->assertOk()
            ->assertJsonPath('data.checked', 1)
            ->assertJsonPath('data.failed', 1);
    }

    public function test_student_cannot_bulk_sync(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/admin/videos/sync')
            ->assertStatus(403);
    }

    public function test_student_cannot_upload_videos(): void
    {
        $this->vdocipherPlatform();

        Http::fake();

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/admin/videos/import-url', [
                'title' => 'Aula',
                'url' => 'https://cdn.example.com/lesson.mp4',
            ])
            ->assertStatus(403);

        Http::assertNothingSent();
    }
}
