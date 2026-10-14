<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class ModuleTreeTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_admin_can_list_all_modules_with_content(): void
    {
        $module = Module::factory()->create(['company_id' => $this->company->id]);

        $topic = Topic::factory()->create([
            'company_id' => $this->company->id,
            'module_id' => $module->id,
        ]);

        Lesson::factory()->create([
            'company_id' => $this->company->id,
            'module_id' => $module->id,
            'topic_id' => $topic->id,
        ]);

        Lesson::factory()->create([
            'company_id' => $this->company->id,
            'module_id' => $module->id,
            'topic_id' => null,
        ]);

        $course = Course::factory()->create(['company_id' => $this->company->id]);
        $module->courses()->attach($course->id, [
            'company_id' => $this->company->id,
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/modules/tree')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $module->id)
            ->assertJsonCount(1, 'data.0.topics')
            ->assertJsonPath('data.0.topics.0.id', $topic->id)
            ->assertJsonCount(1, 'data.0.lessons')
            ->assertJsonPath('data.0.lessons.0.topic_id', null)
            ->assertJsonPath('data.0.courses.0.id', $course->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.last_page', 1);
    }

    public function test_tree_search_matches_name_and_description(): void
    {
        Module::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Fundamentos',
            'description' => 'Base do curso',
        ]);

        Module::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Avançado',
            'description' => 'Aprofundamento',
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/modules/tree?search=Fundamentos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fundamentos');

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/modules/tree?search=Aprofundamento')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Avançado');
    }

    public function test_tree_filters_modules_by_usage(): void
    {
        $used = Module::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Em uso',
        ]);

        Module::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Sem curso',
        ]);

        $course = Course::factory()->create(['company_id' => $this->company->id]);
        $used->courses()->attach($course->id, [
            'company_id' => $this->company->id,
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/modules/tree?usage=used')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Em uso');

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/modules/tree?usage=unused')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sem curso');
    }

    public function test_tree_is_paginated(): void
    {
        foreach (range(1, 15) as $i) {
            Module::factory()->create([
                'company_id' => $this->company->id,
                'name' => sprintf('Módulo %02d', $i),
            ]);
        }

        $first = $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/modules/tree?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2);

        $second = $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/modules/tree?per_page=10&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);

        $first->assertJsonPath('data.0.name', 'Módulo 01');
        $second->assertJsonPath('data.0.name', 'Módulo 11');
    }

    public function test_tree_only_lists_modules_from_the_current_company(): void
    {
        Module::factory()->create(['company_id' => $this->company->id]);
        Module::factory()->create();

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/modules/tree')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_bulk_delete_modules(): void
    {
        $module = Module::factory()->create(['company_id' => $this->company->id, 'name' => 'Apagar']);
        $topic = Topic::factory()->create(['company_id' => $this->company->id, 'module_id' => $module->id]);
        $lesson = Lesson::factory()->create([
            'company_id' => $this->company->id,
            'module_id' => $module->id,
            'topic_id' => $topic->id,
        ]);

        $course = Course::factory()->create(['company_id' => $this->company->id]);
        $module->courses()->attach($course->id, [
            'company_id' => $this->company->id,
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $other = Module::factory()->create(['company_id' => $this->company->id, 'name' => 'Outro']);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/modules/bulk', [
                'action' => 'delete',
                'module_ids' => [$module->id, $other->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 2)
            ->assertJsonPath('data.failed', []);

        $this->assertDatabaseMissing('modules', ['id' => $module->id]);
        $this->assertDatabaseMissing('modules', ['id' => $other->id]);
        $this->assertDatabaseMissing('topics', ['id' => $topic->id]);
        $this->assertDatabaseMissing('lessons', ['id' => $lesson->id]);
        $this->assertDatabaseMissing('course_modules', ['module_id' => $module->id]);
    }

    public function test_admin_can_bulk_change_module_status(): void
    {
        $modules = Module::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);

        $ids = $modules->pluck('id')->all();

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/modules/bulk', ['action' => 'deactivate', 'module_ids' => $ids])
            ->assertOk()
            ->assertJsonPath('data.processed', 2);

        foreach ($modules as $module) {
            $this->assertSame('inactive', $module->fresh()->status);
        }

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/modules/bulk', ['action' => 'activate', 'module_ids' => $ids])
            ->assertOk();

        foreach ($modules as $module) {
            $this->assertSame('active', $module->fresh()->status);
        }
    }

    public function test_bulk_reports_modules_from_other_companies_as_failures(): void
    {
        $foreign = Module::factory()->create(['name' => 'Outra empresa']);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/modules/bulk', [
                'action' => 'delete',
                'module_ids' => [$foreign->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 0)
            ->assertJsonPath('data.failed.0.id', $foreign->id);

        $this->assertDatabaseHas('modules', ['id' => $foreign->id]);
    }

    public function test_bulk_requires_module_ids(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/modules/bulk', ['action' => 'delete', 'module_ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('module_ids');
    }

    public function test_student_cannot_bulk_edit_modules(): void
    {
        $module = Module::factory()->create(['company_id' => $this->company->id]);

        $this->withToken($this->apiLogin($this->student))
            ->postJson('/api/v1/admin/modules/bulk', [
                'action' => 'delete',
                'module_ids' => [$module->id],
            ])
            ->assertForbidden();
    }

    public function test_student_cannot_list_the_module_tree(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/modules/tree')
            ->assertForbidden();
    }

    public function test_guest_cannot_list_the_module_tree(): void
    {
        $this->getJson('/api/v1/admin/modules/tree')->assertUnauthorized();
    }
}
