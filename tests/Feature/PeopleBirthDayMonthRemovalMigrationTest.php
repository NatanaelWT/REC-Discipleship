<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PeopleBirthDayMonthRemovalMigrationTest extends TestCase
{
    public function test_birth_day_month_is_removed_from_people_table(): void
    {
        Schema::dropIfExists('orang');
        Schema::create('orang', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_day_month')->nullable();
        });

        DB::table('orang')->insert([
            'id' => 1,
            'full_name' => 'Peserta Test',
            'birth_date' => '1998-04-25',
            'birth_day_month' => '25-04',
        ]);

        $migration = require database_path('migrations/2026_07_07_000002_drop_birth_day_month_from_people.php');
        $migration->up();

        $this->assertFalse(Schema::hasColumn('orang', 'birth_day_month'));
        $this->assertDatabaseHas('orang', [
            'id' => 1,
            'full_name' => 'Peserta Test',
            'birth_date' => '1998-04-25',
        ]);

        $migration->down();

        $this->assertTrue(Schema::hasColumn('orang', 'birth_day_month'));
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('orang');

        parent::tearDown();
    }
}
