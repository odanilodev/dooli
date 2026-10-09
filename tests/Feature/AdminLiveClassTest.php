<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LiveClass;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AdminLiveClassTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
        Storage::fake('public');
    }

    private function adminToken(): string
    {
        return $this->apiLogin($this->admin);
    }

    private function team(string $name = 'Turma A'): Team
    {
        return Team::factory()->create(['company_id' => $this->company->id, 'name' => $name]);
    }

    public function test_admin_can_list_live_classes(): void
    {
        $team = $this->team();
        $liveClass = LiveClass::factory()->create(['company_id' => $this->company->id]);
        $liveClass->teams()->attach($team->id, ['company_id' => $this->company->id, 'status' => 'active']);

        LiveClass::factory()->count(2)->create(['company_id' => $this->company->id]);

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/live-classes')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'link', 'starts_at', 'duration', 'status', 'teams']], 'meta']);
    }

    public function test_admin_can_create_a_live_class_locked_to_teams(): void
    {
        $teamA = $this->team('Turma A');
        $teamB = $this->team('Turma B');

        $response = $this->withToken($this->adminToken())
            ->post('/api/v1/admin/live-classes', [
                'name' => 'Mentoria ao vivo',
                'description' => 'Tira-dúvidas da semana',
                'link' => 'https://meet.example.com/abc',
                'starts_at' => now()->addDay()->toDateTimeString(),
                'duration' => 90,
                'team_ids' => [$teamA->id, $teamB->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Mentoria ao vivo')
            ->assertJsonPath('data.duration', 90);

        $liveClass = LiveClass::query()->firstOrFail();

        $this->assertSame($this->company->id, (int) $liveClass->company_id);
        $this->assertDatabaseHas('team_live_classes', ['team_id' => $teamA->id, 'live_class_id' => $liveClass->id, 'status' => 'active']);
        $this->assertDatabaseHas('team_live_classes', ['team_id' => $teamB->id, 'live_class_id' => $liveClass->id, 'status' => 'active']);
        $this->assertCount(2, $response->json('data.teams'));
    }

    public function test_create_requires_name_link_and_start(): void
    {
        $this->withToken($this->adminToken())
            ->postJson('/api/v1/admin/live-classes', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'link', 'starts_at']);
    }

    public function test_admin_can_upload_a_cover_image(): void
    {
        $response = $this->withToken($this->adminToken())
            ->post('/api/v1/admin/live-classes', [
                'name' => 'Aula com capa',
                'link' => 'https://meet.example.com/cover',
                'starts_at' => now()->addDay()->toDateTimeString(),
                'image' => UploadedFile::fake()->image('capa.png', 800, 450),
            ])
            ->assertCreated();

        $liveClass = LiveClass::query()->firstOrFail();

        $this->assertNotNull($liveClass->image);
        Storage::disk('public')->assertExists($liveClass->image);
        $this->assertSame(Storage::disk('public')->url($liveClass->image), $response->json('data.image_url'));
    }

    public function test_update_syncs_teams_and_replaces_the_image(): void
    {
        Storage::disk('public')->put('live-classes/1/old.jpg', 'old');

        $teamA = $this->team('Turma A');
        $teamB = $this->team('Turma B');

        $liveClass = LiveClass::factory()->create([
            'company_id' => $this->company->id,
            'image' => 'live-classes/1/old.jpg',
        ]);
        $liveClass->teams()->attach($teamA->id, ['company_id' => $this->company->id, 'status' => 'active']);

        $this->withToken($this->adminToken())
            ->post("/api/v1/admin/live-classes/{$liveClass->id}", [
                '_method' => 'PUT',
                'name' => 'Atualizada',
                'team_ids' => [$teamB->id],
                'image' => UploadedFile::fake()->image('nova.png', 400, 200),
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Atualizada');

        $liveClass->refresh();

        $this->assertNotSame('live-classes/1/old.jpg', $liveClass->image);
        Storage::disk('public')->assertMissing('live-classes/1/old.jpg');
        Storage::disk('public')->assertExists($liveClass->image);

        $this->assertDatabaseMissing('team_live_classes', ['team_id' => $teamA->id, 'live_class_id' => $liveClass->id]);
        $this->assertDatabaseHas('team_live_classes', ['team_id' => $teamB->id, 'live_class_id' => $liveClass->id]);
    }

    public function test_admin_can_delete_a_live_class(): void
    {
        Storage::disk('public')->put('live-classes/1/gone.jpg', 'x');

        $team = $this->team();

        $liveClass = LiveClass::factory()->create([
            'company_id' => $this->company->id,
            'image' => 'live-classes/1/gone.jpg',
        ]);
        $liveClass->teams()->attach($team->id, ['company_id' => $this->company->id, 'status' => 'active']);

        $this->withToken($this->adminToken())
            ->deleteJson("/api/v1/admin/live-classes/{$liveClass->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('live_classes', ['id' => $liveClass->id]);
        $this->assertDatabaseMissing('team_live_classes', ['live_class_id' => $liveClass->id]);
        Storage::disk('public')->assertMissing('live-classes/1/gone.jpg');
    }

    public function test_team_from_another_company_is_rejected(): void
    {
        $other = Company::factory()->create();
        $foreignTeam = Team::factory()->create(['company_id' => $other->id]);

        $this->withToken($this->adminToken())
            ->postJson('/api/v1/admin/live-classes', [
                'name' => 'Cross tenant',
                'link' => 'https://meet.example.com/x',
                'starts_at' => now()->addDay()->toDateTimeString(),
                'team_ids' => [$foreignTeam->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('team_ids.0');
    }

    public function test_students_cannot_manage_live_classes(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/live-classes')
            ->assertForbidden();
    }
}
