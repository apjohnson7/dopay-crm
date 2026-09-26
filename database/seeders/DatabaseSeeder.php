<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data is always seeded. Demo users and records only when DOPAY_DEMO_PASSWORD is set,
     * so a production install starts clean: php artisan db:seed creates countries, roles and categories only.
     */
    public function run(): void
    {
        $this->call(ReferenceSeeder::class);

        if (env('DOPAY_DEMO_PASSWORD') && app()->environment('production')) {
            $this->command?->error('Demo data is never created in production. Set APP_ENV=local for a demo install.');
        } elseif (env('DOPAY_DEMO_PASSWORD')) {
            $this->call(DemoSeeder::class);
        } else {
            $this->command?->info('DOPAY_DEMO_PASSWORD is empty, so demo users and records were skipped. Create the first administrator with: php artisan dopay:admin');
        }
    }
}
