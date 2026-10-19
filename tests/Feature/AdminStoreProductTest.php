<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\StoreProduct;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AdminStoreProductTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
        Storage::fake('public');
    }

    public function test_admin_can_create_store_product_with_image_and_teams(): void
    {
        $course = Course::factory()->create(['company_id' => $this->company->id]);
        $team = Team::factory()->create(['company_id' => $this->company->id]);

        $response = $this->withToken($this->apiLogin($this->admin))
            ->post('/api/v1/admin/store-products', [
                'course_id' => $course->id,
                'title' => 'Curso de PHP',
                'description' => 'Acesso completo.',
                'price' => 199.90,
                'sale' => 149.90,
                'status' => 'active',
                'tags' => ['php', 'backend'],
                'team_ids' => [$team->id],
                'image_file' => UploadedFile::fake()->image('produto.png', 1280, 720),
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'curso-de-php')
            ->assertJsonPath('data.course_id', $course->id)
            ->assertJsonPath('data.teams.0.id', $team->id);

        $product = StoreProduct::query()->firstOrFail();

        $this->assertNotNull($product->image);
        Storage::disk('public')->assertExists($product->image);
        $this->assertSame(Storage::disk('public')->url($product->image), $response->json('data.image_url'));

        $this->assertDatabaseHas('store_product_teams', [
            'store_product_id' => $product->id,
            'team_id' => $team->id,
        ]);
    }

    public function test_admin_can_update_product_replace_image_and_sync_teams(): void
    {
        Storage::disk('public')->put('store-products/1/old.jpg', 'old');

        $course = Course::factory()->create(['company_id' => $this->company->id]);
        $oldTeam = Team::factory()->create(['company_id' => $this->company->id]);
        $newTeam = Team::factory()->create(['company_id' => $this->company->id]);
        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course->id,
            'image' => 'store-products/1/old.jpg',
            'slug' => 'produto-antigo',
        ]);
        $product->teams()->attach($oldTeam->id, [
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->post("/api/v1/admin/store-products/{$product->id}", [
                '_method' => 'PUT',
                'title' => 'Produto Novo',
                'price' => 250,
                'sale' => 0,
                'team_ids' => [$newTeam->id],
                'image_file' => UploadedFile::fake()->image('novo.png', 1280, 720),
            ])
            ->assertOk();

        $product->refresh();

        $this->assertSame('produto-novo', $product->slug);
        $this->assertNotSame('store-products/1/old.jpg', $product->image);
        Storage::disk('public')->assertMissing('store-products/1/old.jpg');
        Storage::disk('public')->assertExists($product->image);

        $this->assertDatabaseMissing('store_product_teams', [
            'store_product_id' => $product->id,
            'team_id' => $oldTeam->id,
        ]);
        $this->assertDatabaseHas('store_product_teams', [
            'store_product_id' => $product->id,
            'team_id' => $newTeam->id,
        ]);
    }

    public function test_admin_can_remove_product_image(): void
    {
        Storage::disk('public')->put('store-products/1/remove.jpg', 'old');

        $course = Course::factory()->create(['company_id' => $this->company->id]);
        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course->id,
            'image' => 'store-products/1/remove.jpg',
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/store-products/{$product->id}", [
                'image' => '',
            ])
            ->assertOk()
            ->assertJsonPath('data.image', null);

        $product->refresh();

        $this->assertNull($product->image);
        Storage::disk('public')->assertMissing('store-products/1/remove.jpg');
    }

    public function test_admin_can_delete_product_and_its_image_and_team_links(): void
    {
        Storage::disk('public')->put('store-products/1/delete.jpg', 'image');

        $course = Course::factory()->create(['company_id' => $this->company->id]);
        $team = Team::factory()->create(['company_id' => $this->company->id]);
        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course->id,
            'image' => 'store-products/1/delete.jpg',
        ]);
        $product->teams()->attach($team->id, [
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/store-products/{$product->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('store_products', ['id' => $product->id]);
        $this->assertDatabaseMissing('store_product_teams', ['store_product_id' => $product->id]);
        Storage::disk('public')->assertMissing('store-products/1/delete.jpg');
    }

    public function test_admin_can_filter_store_products(): void
    {
        $course = Course::factory()->create(['company_id' => $this->company->id]);

        StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course->id,
            'title' => 'Curso de PHP',
            'slug' => 'curso-de-php',
            'status' => 'active',
        ]);
        StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course->id,
            'title' => 'Curso de Java',
            'slug' => 'curso-de-java',
            'status' => 'inactive',
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/store-products?search=PHP&status=active')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'curso-de-php');
    }

    public function test_student_cannot_manage_store_products(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/store-products')
            ->assertForbidden();
    }
}
