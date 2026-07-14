<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonNameNormalizationMigrationTest extends TestCase
{
    public function test_it_normalizes_existing_names_without_changing_other_data_or_timestamps(): void
    {
        Schema::dropIfExists('orang');
        Schema::create('orang', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        DB::table('orang')->insert([
            [
                'id' => 10,
                'branch_id' => 3,
                'full_name' => '  WILLY   K S ',
                'status' => 'inactive',
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-02-02 00:00:00',
            ],
            [
                'id' => 11,
                'branch_id' => 1,
                'full_name' => 'Nama Sudah Benar',
                'status' => 'active',
                'created_at' => '2026-03-03 00:00:00',
                'updated_at' => '2026-04-04 00:00:00',
            ],
        ]);

        $migration = require database_path('migrations/2026_07_14_000001_normalize_person_full_names.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseCount('orang', 2);
        $this->assertDatabaseHas('orang', [
            'id' => 10,
            'branch_id' => 3,
            'full_name' => 'Willy K S',
            'status' => 'inactive',
            'updated_at' => '2026-02-02 00:00:00',
        ]);
        $this->assertDatabaseHas('orang', [
            'id' => 11,
            'full_name' => 'Nama Sudah Benar',
            'updated_at' => '2026-04-04 00:00:00',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('orang');

        parent::tearDown();
    }
}
