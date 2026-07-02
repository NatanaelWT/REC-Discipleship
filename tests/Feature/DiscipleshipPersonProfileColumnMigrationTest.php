<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DiscipleshipPersonProfileColumnMigrationTest extends TestCase
{
    public function test_profile_columns_are_removed_when_present(): void
    {
        $this->createDiscipleshipPeopleTable(withProfileColumns: true);

        DB::table('discipleship_people')->insert([
            'id' => 7,
            'branch_id' => 2,
            'full_name' => 'Legacy Person',
            'phone' => '08123456789',
            'gender' => 'P',
            'status' => 'active',
            'notes' => 'Tetap ada',
            'campus' => 'Legacy Campus',
            'major' => 'Legacy Major',
            'occupation' => 'Legacy Occupation',
            'created_at' => '2026-07-02 09:00:00',
            'updated_at' => '2026-07-02 09:00:00',
        ]);

        $migration = require database_path('migrations/2026_07_02_000002_drop_discipleship_people_profile_columns.php');
        $migration->up();
        $migration->up();

        $this->assertFalse(Schema::hasColumn('discipleship_people', 'campus'));
        $this->assertFalse(Schema::hasColumn('discipleship_people', 'major'));
        $this->assertFalse(Schema::hasColumn('discipleship_people', 'occupation'));
        $this->assertDatabaseHas('discipleship_people', [
            'id' => 7,
            'branch_id' => 2,
            'full_name' => 'Legacy Person',
            'phone' => '08123456789',
            'gender' => 'P',
            'status' => 'active',
            'notes' => 'Tetap ada',
        ]);
    }

    public function test_profile_column_removal_is_safe_when_columns_are_missing(): void
    {
        $this->createDiscipleshipPeopleTable(withProfileColumns: false);

        $migration = require database_path('migrations/2026_07_02_000002_drop_discipleship_people_profile_columns.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('discipleship_people'));
        $this->assertFalse(Schema::hasColumn('discipleship_people', 'campus'));
        $this->assertFalse(Schema::hasColumn('discipleship_people', 'major'));
        $this->assertFalse(Schema::hasColumn('discipleship_people', 'occupation'));
    }

    private function createDiscipleshipPeopleTable(bool $withProfileColumns): void
    {
        Schema::dropIfExists('discipleship_people');

        Schema::create('discipleship_people', function (Blueprint $table) use ($withProfileColumns): void {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();

            if ($withProfileColumns) {
                $table->string('campus')->nullable();
                $table->string('major')->nullable();
                $table->string('occupation')->nullable();
            }

            $table->timestamps();
        });
    }
}
