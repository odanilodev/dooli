<?php

namespace Tests\Feature;

use App\Enums\AccessGrantSource;
use App\Enums\GeneralStatus;
use App\Models\AccessGrant;
use App\Models\Module;
use App\Models\StoreProduct;
use App\Models\Team;
use App\Services\AccessGrantService;
use App\Services\DripReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class DripReleaseTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    /** @var array{course: \App\Models\Course, module: Module, lesson: \App\Models\Lesson} */
    private array $data;

    private Module $module2;

    private Team $team;

    private StoreProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();

        $this->data = $this->buildCourse($this->company->id);
        $this->module2 = Module::factory()->create([
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
        ]);
        $this->data['course']->modules()->attach($this->module2->id, [
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
            'sort_order' => 1,
        ]);
        $this->buildModuleLesson($this->company->id, $this->module2);

        $this->team = $this->enroll($this->student, $this->data['course'], $this->company->id);
        // enroll() cria a matrícula direta; o acesso passa a vir só dos grants.
        $this->team->users()->detach($this->student->id);

        $this->product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $this->data['course']->id,
            'drip_enabled' => true,
        ]);
        $this->product->teams()->attach($this->team->id, [
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
        ]);
    }

    private function grantOrder(?StoreProduct $product = null, int $sourceId = 1): void
    {
        app(AccessGrantService::class)->grantForProduct(
            companyId: $this->company->id,
            userId: $this->student->id,
            product: $product ?? $this->product,
            sourceType: AccessGrantSource::Order->value,
            sourceId: $sourceId,
            startsAt: now(),
        );
    }

    private function releaseDates(): array
    {
        return app(DripReleaseService::class)->moduleReleaseDates(
            $this->student, $this->team->id, $this->data['course']->id,
        );
    }

    public function test_first_module_is_immediate_and_next_defaults_to_one_day(): void
    {
        $this->grantOrder();

        $dates = $this->releaseDates();

        $this->assertFalse($dates[$this->data['module']->id]->isFuture());
        $this->assertTrue($dates[$this->module2->id]->isFuture());
        $this->assertEqualsWithDelta(
            now()->addDay()->timestamp,
            $dates[$this->module2->id]->timestamp,
            5,
        );
    }

    public function test_custom_interval_is_used(): void
    {
        app(DripReleaseService::class)->syncIntervals($this->product, [
            ['module_id' => $this->module2->id, 'interval_days' => 4],
        ]);
        $this->grantOrder();

        $this->assertEqualsWithDelta(
            now()->addDays(4)->timestamp,
            $this->releaseDates()[$this->module2->id]->timestamp,
            5,
        );
    }

    public function test_product_without_drip_gives_full_access(): void
    {
        $this->product->update(['drip_enabled' => false]);
        $this->grantOrder();

        $this->assertNull(AccessGrant::query()->firstOrFail()->drip_product_id);
        $this->assertSame([], $this->releaseDates());
    }

    public function test_existing_grants_are_not_affected_when_drip_is_enabled_later(): void
    {
        $this->product->update(['drip_enabled' => false]);
        $this->grantOrder();
        $this->product->update(['drip_enabled' => true]);

        $this->assertSame([], $this->releaseDates());
    }

    public function test_manual_grant_overrides_drip(): void
    {
        $this->grantOrder();
        app(AccessGrantService::class)->grantManual($this->company->id, $this->student->id, $this->team->id);

        $this->assertSame([], $this->releaseDates());
    }

    public function test_student_cannot_start_lesson_of_locked_module(): void
    {
        $this->grantOrder();
        $lesson = $this->module2->lessons()->firstOrFail();

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/me/progress/start', [
                'lesson_id' => $lesson->id,
                'course_id' => $this->data['course']->id,
                'team_id' => $this->team->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business_rule');
    }

    public function test_locked_module_is_released_after_the_interval(): void
    {
        $this->grantOrder();

        $this->travel(2)->days();

        $this->assertFalse($this->releaseDates()[$this->module2->id]->isFuture());
    }

    public function test_admin_can_enable_drip_and_set_intervals(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/store-products/{$this->product->id}", [
                'drip_enabled' => true,
                'module_drips' => [['module_id' => $this->module2->id, 'interval_days' => 3]],
            ])
            ->assertOk()
            ->assertJsonPath('data.drip_enabled', true)
            ->assertJsonPath('data.drip_modules.0.interval_days', 0)
            ->assertJsonPath('data.drip_modules.1.interval_days', 3)
            ->assertJsonPath('data.drip_modules.1.release_day', 3);
    }
}
