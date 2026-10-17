<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            AdminUserSeeder::class,
        ]);

        // Sample data (fake people with a known password) never goes into production
        // unless explicitly requested with SEED_DEMO=true.
        $demo = config('primeit.seed_demo');
        $demo = $demo === null ? ! app()->isProduction() : filter_var($demo, FILTER_VALIDATE_BOOLEAN);

        if ($demo) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
