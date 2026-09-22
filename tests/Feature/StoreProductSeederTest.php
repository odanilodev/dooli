<?php

namespace Tests\Feature;

use Database\Seeders\CompanySeeder;
use Database\Seeders\DemoEnrollmentSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\StoreProductSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreProductSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CompanySeeder::class);
        $this->seed(UserTypeSeeder::class);
        $this->seed(DemoUserSeeder::class);
    }

    public function test_store_product_seeder_is_idempotent(): void
    {
        $this->seed(StoreProductSeeder::class);
        $this->seed(StoreProductSeeder::class);

        $this->assertDatabaseCount('courses', 1);
        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseCount('store_products', 3);
        $this->assertDatabaseCount('store_product_teams', 3);

        $this->assertDatabaseHas('store_products', [
            'company_id' => 1,
            'slug' => 'curso-de-php',
            'status' => 'active',
        ]);
    }

    public function test_demo_enrollment_seeder_is_idempotent(): void
    {
        $this->seed(StoreProductSeeder::class);
        $this->seed(DemoEnrollmentSeeder::class);
        $this->seed(DemoEnrollmentSeeder::class);

        $this->assertDatabaseCount('team_courses', 1);
        $this->assertDatabaseCount('team_users', 1);
    }
}
