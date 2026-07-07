<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DropDiscipleshipRelationshipsMigrationTest extends TestCase
{
    public function test_migration_drops_relationship_tables_and_rolls_back_empty_schema(): void
    {
        $this->createRelationshipTables();

        $migration = require database_path('migrations/2026_07_07_000005_drop_discipleship_relationships_table.php');
        $migration->up();

        $this->assertFalse(Schema::hasTable('relasi_dg'));
        $this->assertFalse(Schema::hasTable('discipleship_relationships'));

        $migration->down();

        $this->assertTrue(Schema::hasTable('relasi_dg'));
        $this->assertSame(0, DB::table('relasi_dg')->count());
        foreach (['branch_id', 'mentor_person_id', 'disciple_person_id', 'context_group_id', 'status'] as $column) {
            $this->assertTrue(Schema::hasColumn('relasi_dg', $column));
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('relasi_dg');
        Schema::dropIfExists('discipleship_relationships');

        parent::tearDown();
    }

    private function createRelationshipTables(): void
    {
        Schema::dropIfExists('relasi_dg');
        Schema::dropIfExists('discipleship_relationships');

        Schema::create('relasi_dg', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('mentor_person_id')->nullable();
            $table->unsignedBigInteger('disciple_person_id')->nullable();
            $table->unsignedBigInteger('context_group_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('discipleship_relationships', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mentor_person_id')->nullable();
            $table->unsignedBigInteger('disciple_person_id')->nullable();
            $table->timestamps();
        });

        DB::table('relasi_dg')->insert([
            'branch_id' => 1,
            'mentor_person_id' => 10,
            'disciple_person_id' => 11,
            'context_group_id' => 20,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
