<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    private const DOMAIN_TABLES = [
        'companies',
        'user_types',
        'users',
        'menus',
        'actions',
        'company_settings',
        'gateways',
        'banners',
        'courses',
        'teams',
        'modules',
        'videos',
        'documents',
        'attachments',
        'topics',
        'lessons',
        'course_modules',
        'lesson_attachments',
        'team_courses',
        'team_users',
        'store_products',
        'store_product_teams',
        'coupons',
        'coupon_store_products',
        'certificates',
        'certificate_users',
        'type_notifications',
        'notifications',
        'notification_users',
        'orders',
        'order_items',
        'payments',
        'payment_events',
        'refunds',
        'comments',
        'ratings',
        'timelines',
        'datalogs',
        'personal_access_tokens',
        'password_reset_tokens',
    ];

    public function test_every_domain_table_has_at_least_one_record(): void
    {
        $this->seed();

        foreach (self::DOMAIN_TABLES as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Table [{$table}] does not exist.",
            );

            $this->assertGreaterThan(
                0,
                DB::table($table)->count(),
                "Table [{$table}] has no records after seeding.",
            );
        }
    }

    public function test_seeding_is_idempotent(): void
    {
        $this->seed();

        $counts = $this->counts();

        $this->seed();

        $this->assertSame($counts, $this->counts(), 'Re-seeding changed the row counts.');
    }

    private function counts(): array
    {
        return collect(self::DOMAIN_TABLES)
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])
            ->all();
    }
}
