<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Robot;
use App\Services\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyRobotProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_company_is_born_with_the_delta_stats_robot(): void
    {
        ['company' => $company] = app(CompanyService::class)->create([
            'name' => 'Escola Nova',
            'admin_name' => 'Admin',
            'admin_username' => 'admin-nova',
            'admin_email' => 'admin@escola-nova.test',
            'admin_password' => 'senha-segura-123',
        ]);

        $robot = Robot::withoutGlobalScopes()->where('company_id', $company->id)->where('slug', 'delta-screener')->first();

        $this->assertNotNull($robot);
        $this->assertSame('DeltaStats', $robot->name);
        $this->assertSame('active', $robot->status);
    }

    public function test_backfill_migration_creates_the_robot_only_where_it_is_missing(): void
    {
        $withRobot = Company::factory()->create();
        $withoutRobot = Company::factory()->create();

        Robot::withoutGlobalScopes()->create([
            'company_id' => $withRobot->id,
            'slug' => 'delta-screener',
            'name' => 'Nome personalizado',
            'status' => 'inactive',
        ]);

        $migration = require database_path('migrations/2026_11_10_000100_backfill_robots_for_all_companies.php');
        $migration->up();
        $migration->up(); // idempotente

        $this->assertSame(1, DB::table('robots')->where('company_id', $withoutRobot->id)->where('slug', 'delta-screener')->count());

        // Quem já tinha o robô (mesmo desativado e renomeado) não é sobrescrito.
        $kept = DB::table('robots')->where('company_id', $withRobot->id)->where('slug', 'delta-screener')->first();
        $this->assertSame('Nome personalizado', $kept->name);
        $this->assertSame('inactive', $kept->status);
    }
}
