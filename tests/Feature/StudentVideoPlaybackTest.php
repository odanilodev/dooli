<?php

namespace Tests\Feature;

use App\Enums\VideoPlayer;
use App\Models\Video;
use App\Models\VideoPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class StudentVideoPlaybackTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    /**
     * @return array{course: int, lesson: int, video: Video}
     */
    private function courseWithVideo(int $companyId, bool $enroll = true): array
    {
        $data = $this->buildCourse($companyId);

        $video = Video::factory()->create([
            'company_id' => $companyId,
            'player' => VideoPlayer::VdoCipher->value,
            'code' => 'VD-ABC',
            'link' => 'https://cdn.example.com/raw.m3u8',
        ]);

        $data['lesson']->update(['video_id' => $video->id]);

        if ($enroll) {
            $this->enroll($this->student, $data['course'], $companyId);
        }

        return [
            'course' => $data['course']->id,
            'lesson' => $data['lesson']->id,
            'video' => $video,
        ];
    }

    public function test_enrolled_student_receives_signed_vdocipher_playback(): void
    {
        VideoPlatform::factory()->create([
            'company_id' => $this->company->id,
            'code' => VideoPlayer::VdoCipher->value,
            'credentials' => ['api_secret' => 'secret-123'],
            'settings' => ['ttl' => 3600],
        ]);

        Http::fake([
            'dev.vdocipher.com/*' => Http::response(['otp' => 'OTP-123', 'playbackInfo' => 'PB-INFO']),
        ]);

        $data = $this->courseWithVideo($this->company->id);

        $response = $this->withToken($this->apiLogin($this->student))
            ->getJson("/api/v1/me/courses/{$data['course']}/lessons/{$data['lesson']}/video")
            ->assertOk()
            ->assertJsonPath('data.platform', 'vdocipher')
            ->assertJsonPath('data.video_code', 'VD-ABC');

        $embed = $response->json('data.embed_url');

        $this->assertStringContainsString('otp=OTP-123', $embed);
        $this->assertStringContainsString('playbackInfo=PB-INFO', $embed);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'dev.vdocipher.com/api/videos/VD-ABC/otp')
            && $request->hasHeader('Authorization', 'Apisecret secret-123'));
    }

    public function test_each_playback_request_generates_a_fresh_otp(): void
    {
        VideoPlatform::factory()->create([
            'company_id' => $this->company->id,
            'code' => VideoPlayer::VdoCipher->value,
            'credentials' => ['api_secret' => 'secret-123'],
        ]);

        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return Http::response(['otp' => 'OTP-'.$calls, 'playbackInfo' => 'PB-'.$calls]);
        });

        $data = $this->courseWithVideo($this->company->id);
        $token = $this->apiLogin($this->student);
        $url = "/api/v1/me/courses/{$data['course']}/lessons/{$data['lesson']}/video";

        $first = $this->withToken($token)->getJson($url)->assertOk()->json('data.embed_url');
        $second = $this->withToken($token)->getJson($url)->assertOk()->json('data.embed_url');

        $this->assertStringContainsString('otp=OTP-1', $first);
        $this->assertStringContainsString('otp=OTP-2', $second);
        $this->assertNotSame($first, $second);
        Http::assertSentCount(2);
    }

    public function test_playback_requires_enrollment(): void
    {
        VideoPlatform::factory()->create([
            'company_id' => $this->company->id,
            'code' => VideoPlayer::VdoCipher->value,
            'credentials' => ['api_secret' => 'secret-123'],
        ]);

        $data = $this->courseWithVideo($this->company->id, enroll: false);

        $this->withToken($this->apiLogin($this->student))
            ->getJson("/api/v1/me/courses/{$data['course']}/lessons/{$data['lesson']}/video")
            ->assertStatus(403);
    }

    public function test_lesson_without_video_returns_not_found(): void
    {
        $data = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $data['course'], $this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->getJson("/api/v1/me/courses/{$data['course']->id}/lessons/{$data['lesson']->id}/video")
            ->assertStatus(404);
    }

    public function test_missing_platform_config_falls_back_to_direct_link(): void
    {
        Http::fake();

        $data = $this->courseWithVideo($this->company->id);

        $response = $this->withToken($this->apiLogin($this->student))
            ->getJson("/api/v1/me/courses/{$data['course']}/lessons/{$data['lesson']}/video")
            ->assertOk()
            ->assertJsonPath('data.platform', 'vdocipher');

        $this->assertSame('https://cdn.example.com/raw.m3u8', $response->json('data.embed_url'));
        $this->assertNotEmpty($response->json('data.warning'));
        Http::assertNothingSent();
    }
}
