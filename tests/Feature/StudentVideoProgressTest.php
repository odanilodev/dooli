<?php

namespace Tests\Feature;

use App\Enums\VideoPlayer;
use App\Models\Team;
use App\Models\Video;
use App\Models\VideoProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class StudentVideoProgressTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    /**
     * @return array{course: int, lesson: int, team: ?int, video: Video}
     */
    private function courseWithVideo(int $companyId, bool $enroll = true): array
    {
        $data = $this->buildCourse($companyId);

        $video = Video::factory()->create([
            'company_id' => $companyId,
            'player' => VideoPlayer::External->value,
            'link' => 'https://videos.example.com/lesson',
        ]);

        $data['lesson']->update(['video_id' => $video->id]);

        $team = $enroll ? $this->enroll($this->student, $data['course'], $companyId) : null;

        return [
            'course' => $data['course']->id,
            'lesson' => $data['lesson']->id,
            'team' => $team?->id,
            'video' => $video,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $data, int $position, ?int $duration = null): array
    {
        return [
            'lesson_id' => $data['lesson'],
            'course_id' => $data['course'],
            'team_id' => $data['team'],
            'position' => $position,
            'duration' => $duration,
        ];
    }

    public function test_student_saves_video_position(): void
    {
        $data = $this->courseWithVideo($this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/video-position', $this->payload($data, 120, 600))
            ->assertOk()
            ->assertJsonPath('data.position_seconds', 120)
            ->assertJsonPath('data.duration', 600);

        $this->assertDatabaseHas('video_progresses', [
            'user_id' => $this->student->id,
            'lesson_id' => $data['lesson'],
            'position_seconds' => 120,
        ]);
    }

    public function test_saving_position_twice_updates_the_same_row(): void
    {
        $data = $this->courseWithVideo($this->company->id);
        $token = $this->apiLogin($this->student);

        $this->withToken($token)->postJson('/api/v1/me/progress/video-position', $this->payload($data, 30))->assertOk();
        $this->withToken($token)->postJson('/api/v1/me/progress/video-position', $this->payload($data, 95))->assertOk();

        $this->assertSame(1, VideoProgress::query()
            ->where('user_id', $this->student->id)
            ->where('lesson_id', $data['lesson'])
            ->count());

        $this->assertDatabaseHas('video_progresses', [
            'user_id' => $this->student->id,
            'lesson_id' => $data['lesson'],
            'position_seconds' => 95,
        ]);
    }

    public function test_playback_returns_saved_resume_position(): void
    {
        $data = $this->courseWithVideo($this->company->id);
        $token = $this->apiLogin($this->student);
        $url = "/api/v1/me/courses/{$data['course']}/lessons/{$data['lesson']}/video";

        $this->withToken($token)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.resume_from', 0);

        $this->withToken($token)->postJson('/api/v1/me/progress/video-position', $this->payload($data, 200))->assertOk();

        $this->withToken($token)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.resume_from', 200);
    }

    public function test_saving_position_requires_enrollment(): void
    {
        $data = $this->courseWithVideo($this->company->id, enroll: false);
        $foreignTeam = Team::factory()->create(['company_id' => $this->company->id]);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/video-position', [
                'lesson_id' => $data['lesson'],
                'course_id' => $data['course'],
                'team_id' => $foreignTeam->id,
                'position' => 10,
            ])
            ->assertStatus(403);
    }

    public function test_position_must_not_be_negative(): void
    {
        $data = $this->courseWithVideo($this->company->id);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/video-position', $this->payload($data, -5))
            ->assertStatus(422)
            ->assertJsonValidationErrors('position');
    }
}
