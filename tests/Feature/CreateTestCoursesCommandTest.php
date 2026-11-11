<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\StoreProduct;
use App\Models\Team;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class CreateTestCoursesCommandTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    private Video $video;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
        $this->video = Video::factory()->create(['company_id' => $this->company->id]);
    }

    private function runCommand(array $options): int
    {
        return Artisan::call('courses:create-test', ['--company' => $this->company->id, ...$options]);
    }

    public function test_team_per_course_links_each_product_only_to_its_own_team(): void
    {
        $this->assertSame(0, $this->runCommand(['--video' => $this->video->id, '--store' => true, '--team-per-course' => true]));

        $courses = Course::query()->forCompany($this->company->id)->where('sort_order', '>', 900)->get();
        $this->assertCount(6, $courses);

        foreach ($courses as $course) {
            $team = Team::query()->forCompany($this->company->id)->where('name', 'Turma '.$course->name)->firstOrFail();
            $product = StoreProduct::query()->forCompany($this->company->id)->where('course_id', $course->id)->firstOrFail();

            $this->assertSame([$course->id], $team->courses()->pluck('courses.id')->all());
            $this->assertSame([$team->id], $product->teams()->pluck('teams.id')->all());
        }
    }

    public function test_rerun_never_overwrites_edited_products_and_courses(): void
    {
        $this->runCommand(['--video' => $this->video->id, '--store' => true, '--team-per-course' => true]);

        $course = Course::query()->forCompany($this->company->id)->where('name', 'Day Trade do Zero')->firstOrFail();
        $product = StoreProduct::query()->forCompany($this->company->id)->where('course_id', $course->id)->firstOrFail();

        $course->update(['description' => 'Descrição editada pelo admin']);
        $product->update(['price' => 297, 'status' => 'active']);

        $this->runCommand(['--video' => $this->video->id, '--store' => true, '--team-per-course' => true]);

        $this->assertSame('Descrição editada pelo admin', $course->fresh()->description);
        $this->assertSame('297.00', $product->fresh()->price);
        $this->assertSame('active', $product->fresh()->status);
        $this->assertSame(6, Course::query()->forCompany($this->company->id)->where('sort_order', '>', 900)->count());
    }

    public function test_only_existing_detaches_general_teams_without_creating_content(): void
    {
        $course = Course::factory()->create(['company_id' => $this->company->id, 'name' => 'Day Trade do Zero']);
        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course->id,
            'slug' => 'day-trade-do-zero',
            'price' => 150,
        ]);

        $general = Team::factory()->create(['company_id' => $this->company->id, 'name' => 'Turma 2027']);
        $general->courses()->attach($course->id, ['company_id' => $this->company->id, 'status' => 'active']);
        $product->teams()->attach($general->id, ['company_id' => $this->company->id, 'status' => 'active']);

        $this->assertSame(0, $this->runCommand(['--team-per-course' => true, '--only-existing' => true]));

        // Só o curso que já existia é tratado; nenhum curso novo é criado.
        $this->assertSame(1, Course::query()->forCompany($this->company->id)->count());

        $team = Team::query()->forCompany($this->company->id)->where('name', 'Turma Day Trade do Zero')->firstOrFail();
        $this->assertSame([$team->id], $product->fresh()->teams()->pluck('teams.id')->all());
        $this->assertSame('150.00', $product->fresh()->price);
    }

    public function test_teams_and_team_per_course_are_mutually_exclusive(): void
    {
        $this->assertSame(1, $this->runCommand(['--video' => $this->video->id, '--teams' => true, '--team-per-course' => true]));
    }

    public function test_purge_refuses_when_students_are_enrolled(): void
    {
        $this->runCommand(['--video' => $this->video->id, '--store' => true, '--team-per-course' => true]);

        $team = Team::query()->forCompany($this->company->id)->where('name', 'Turma Day Trade do Zero')->firstOrFail();
        $team->users()->attach($this->student->id, ['company_id' => $this->company->id, 'status' => 'active']);

        $this->assertSame(1, $this->runCommand(['--purge' => true, '--force' => true]));
        $this->assertSame(6, Course::query()->forCompany($this->company->id)->where('sort_order', '>', 900)->count());
    }
}
