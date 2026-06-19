<?php

namespace Database\Seeders;

use App\Services\Developer\DeveloperUserService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CurrentDatabaseSnapshotSeeder::class);

        if (trim((string) env('DEVELOPER_PASSWORD', '')) !== '') {
            app(DeveloperUserService::class)->ensureDeveloperUserFromEnvironment();
        }
    }
}
