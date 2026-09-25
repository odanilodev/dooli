<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            UserTypeSeeder::class,
            MenuSeeder::class,
            ActionSeeder::class,
            TypeNotificationSeeder::class,
            CompanySettingSeeder::class,
            GatewaySeeder::class,
            VideoPlatformSeeder::class,
            BannerSeeder::class,
            DemoUserSeeder::class,
            StoreProductSeeder::class,
            DemoEnrollmentSeeder::class,
            CourseContentSeeder::class,
            CouponSeeder::class,
            CertificateSeeder::class,
            NotificationSeeder::class,
            OrderSeeder::class,
            EngagementSeeder::class,
            AccessTokenSeeder::class,
        ]);
    }
}
