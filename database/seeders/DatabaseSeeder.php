<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database: reference data first, then the
     * local demo accounts and sample data.
     */
    public function run(): void
    {
        $this->call([
            TypeSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
