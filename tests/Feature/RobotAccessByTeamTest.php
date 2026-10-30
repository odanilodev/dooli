<?php

namespace Tests\Feature;

use App\Enums\AccessGrantSource;
use App\Enums\GeneralStatus;
use App\Models\Robot;
use App\Models\StoreProduct;
use App\Models\Team;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class RobotAccessByTeamTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    private Robot $robot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();

        $this->robot = Robot::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'slug' => 'delta-screener',
            'name' => 'DeltaStats',
        ]);
    }

    private function robotSlugsFor($user): array
    {
        return collect($this->withToken($this->apiLogin($user))->getJson('/api/v1/me/robots')->assertOk()->json('data'))
            ->pluck('slug')->all();
    }

    private function productFor(Team $team, ?int $courseId): StoreProduct
    {
        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $courseId,
            'price' => 100,
            'sale' => 0,
        ]);
        $product->teams()->attach($team->id, ['company_id' => $this->company->id, 'status' => GeneralStatus::Active->value]);

        return $product;
    }

    public function test_buying_a_course_that_includes_the_tool_releases_it_and_refund_takes_it_back(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);
        $team = Team::factory()->create(['company_id' => $this->company->id, 'status' => GeneralStatus::Active->value]);
        $team->courses()->attach($course->id, ['company_id' => $this->company->id, 'status' => GeneralStatus::Active->value]);
        $team->robots()->attach($this->robot->id, ['company_id' => $this->company->id]);
        $product = $this->productFor($team, $course->id);

        $this->assertSame([], $this->robotSlugsFor($this->student));

        $enrollment = app(EnrollmentService::class);
        $enrollment->grantAccess($this->student->id, $product, now(), AccessGrantSource::Order->value, 501);

        $this->assertSame(['delta-screener'], $this->robotSlugsFor($this->student));

        $enrollment->revokeAccess($this->student->id, $product, AccessGrantSource::Order->value, 501);

        $this->assertSame([], $this->robotSlugsFor($this->student));
    }

    public function test_buying_only_the_tool_releases_it_without_any_course(): void
    {
        $team = Team::factory()->create(['company_id' => $this->company->id, 'status' => GeneralStatus::Active->value]);
        $team->robots()->attach($this->robot->id, ['company_id' => $this->company->id]);
        $product = $this->productFor($team, null);

        app(EnrollmentService::class)->grantAccess($this->student->id, $product, now(), AccessGrantSource::Order->value, 502);

        $this->assertSame(['delta-screener'], $this->robotSlugsFor($this->student));
        $this->assertSame(0, $team->courses()->count());
    }

    public function test_a_course_without_the_tool_does_not_release_it(): void
    {
        ['course' => $course] = $this->buildCourse($this->company->id);
        $this->enroll($this->student, $course, $this->company->id);

        $this->assertSame([], $this->robotSlugsFor($this->student));
    }

    public function test_admin_attaches_and_detaches_a_tool_on_a_team(): void
    {
        $team = Team::factory()->create(['company_id' => $this->company->id, 'status' => GeneralStatus::Active->value]);
        $token = $this->apiLogin($this->admin);

        $this->withToken($token)->getJson('/api/v1/admin/robots')
            ->assertOk()->assertJsonPath('data.0.slug', 'delta-screener');

        $this->withToken($token)->postJson("/api/v1/admin/teams/{$team->id}/robots", ['robot_id' => $this->robot->id])->assertOk();
        $this->withToken($token)->getJson("/api/v1/admin/teams/{$team->id}/robots")
            ->assertOk()->assertJsonPath('data.0.slug', 'delta-screener');

        $this->withToken($token)->deleteJson("/api/v1/admin/teams/{$team->id}/robots/{$this->robot->id}")->assertOk();
        $this->withToken($token)->getJson("/api/v1/admin/teams/{$team->id}/robots")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_student_cannot_manage_team_tools(): void
    {
        $team = Team::factory()->create(['company_id' => $this->company->id, 'status' => GeneralStatus::Active->value]);

        $this->withToken($this->apiLogin($this->student))
            ->postJson("/api/v1/admin/teams/{$team->id}/robots", ['robot_id' => $this->robot->id])
            ->assertForbidden();
    }

    public function test_store_product_without_course_is_accepted_when_the_team_has_a_tool(): void
    {
        $team = Team::factory()->create(['company_id' => $this->company->id, 'status' => GeneralStatus::Active->value]);
        $team->robots()->attach($this->robot->id, ['company_id' => $this->company->id]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/store-products', [
                'title' => 'DeltaStats mensal',
                'price' => 49.9,
                'status' => 'active',
                'team_ids' => [$team->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.course_id', null);
    }

    public function test_store_product_without_course_or_tool_is_rejected(): void
    {
        $team = Team::factory()->create(['company_id' => $this->company->id, 'status' => GeneralStatus::Active->value]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/store-products', [
                'title' => 'Vazio',
                'price' => 10,
                'status' => 'active',
                'team_ids' => [$team->id],
            ])
            ->assertUnprocessable();
    }
}
