<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LiveClass;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class StudentLiveClassTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    private function team(string $name = 'Turma A'): Team
    {
        return Team::factory()->create(['company_id' => $this->company->id, 'name' => $name]);
    }

    private function attach(Team $team, LiveClass $liveClass, string $status = 'active'): void
    {
        $team->liveClasses()->attach($liveClass->id, [
            'company_id' => $this->company->id,
            'status' => $status,
        ]);
    }

    private function enroll(Team $team, ?string $status = 'active', mixed $expiredAt = null): void
    {
        $team->users()->attach($this->student->id, [
            'company_id' => $this->company->id,
            'status' => $status,
            'expired_at' => $expiredAt,
        ]);
    }

    public function test_student_sees_live_classes_from_their_active_teams(): void
    {
        $team = $this->team();
        $liveClass = LiveClass::factory()->create(['company_id' => $this->company->id, 'name' => 'Mentoria']);

        $this->attach($team, $liveClass);
        $this->enroll($team);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/live-classes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mentoria')
            ->assertJsonPath('data.0.link', $liveClass->link);
    }

    public function test_student_does_not_see_live_classes_they_are_not_allowed_to(): void
    {
        $allowed = $this->team('Permitida');
        $visible = LiveClass::factory()->create(['company_id' => $this->company->id, 'name' => 'Visível']);
        $this->attach($allowed, $visible);
        $this->enroll($allowed);

        $inactiveLive = LiveClass::factory()->create(['company_id' => $this->company->id, 'name' => 'Inativa', 'status' => 'inactive']);
        $this->attach($allowed, $inactiveLive);

        $unlinkedTeam = $this->team('Sem aula');
        $this->enroll($unlinkedTeam);

        $noCourseTeam = $this->team('Pivot inativo');
        $inactivePivot = LiveClass::factory()->create(['company_id' => $this->company->id, 'name' => 'Pivot off']);
        $this->attach($noCourseTeam, $inactivePivot, 'inactive');
        $this->enroll($noCourseTeam);

        $expiredTeam = $this->team('Expirada');
        $expiredLive = LiveClass::factory()->create(['company_id' => $this->company->id, 'name' => 'Expirada']);
        $this->attach($expiredTeam, $expiredLive);
        $this->enroll($expiredTeam, 'active', now()->subDay());

        $inactiveTeam = Team::factory()->create(['company_id' => $this->company->id, 'status' => 'inactive']);
        $teamLive = LiveClass::factory()->create(['company_id' => $this->company->id, 'name' => 'Time off']);
        $this->attach($inactiveTeam, $teamLive);
        $this->enroll($inactiveTeam);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/live-classes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Visível');
    }

    public function test_live_classes_from_another_company_are_not_visible(): void
    {
        $other = Company::factory()->create();
        $otherTeam = Team::factory()->create(['company_id' => $other->id]);
        $otherLive = LiveClass::factory()->create(['company_id' => $other->id, 'name' => 'Outra empresa']);
        $otherTeam->liveClasses()->attach($otherLive->id, ['company_id' => $other->id, 'status' => 'active']);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/live-classes')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_live_class_already_in_progress_is_visible(): void
    {
        $team = $this->team();
        $liveClass = LiveClass::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Em andamento',
            'starts_at' => now()->subMinutes(30),
            'duration' => 60,
        ]);

        $this->attach($team, $liveClass);
        $this->enroll($team);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/live-classes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Em andamento');
    }

    public function test_past_live_classes_are_not_visible(): void
    {
        $team = $this->team();
        $liveClass = LiveClass::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Encerrada',
            'starts_at' => now()->subHours(2),
            'duration' => 60,
        ]);

        $this->attach($team, $liveClass);
        $this->enroll($team);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/live-classes')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_live_classes_are_limited_to_ten(): void
    {
        $team = $this->team();

        LiveClass::factory()->count(12)->create([
            'company_id' => $this->company->id,
            'starts_at' => now()->addDay(),
        ])->each(fn (LiveClass $liveClass) => $this->attach($team, $liveClass));

        $this->enroll($team);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/live-classes')
            ->assertOk()
            ->assertJsonCount(10, 'data');
    }

    public function test_guest_cannot_list_live_classes(): void
    {
        $this->getJson('/api/v1/me/live-classes')->assertStatus(401);
    }
}
