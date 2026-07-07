<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SimplifyDiscipleshipGroupStageColumnsMigrationTest extends TestCase
{
    public function test_migration_replaces_group_name_and_stage_columns(): void
    {
        $this->createLegacyTable();

        $migration = require database_path('migrations/2026_07_07_000004_simplify_discipleship_group_stage_columns.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('kelompok_dg'));
        $this->assertFalse(Schema::hasColumn('kelompok_dg', 'name'));
        $this->assertFalse(Schema::hasColumn('kelompok_dg', 'start_stage'));
        $this->assertFalse(Schema::hasColumn('kelompok_dg', 'current_stage'));
        $this->assertTrue(Schema::hasColumn('kelompok_dg', 'stage'));
        $this->assertSame('DG 2', DB::table('kelompok_dg')->where('id', 1)->value('stage'));
        $this->assertSame('DG 1', DB::table('kelompok_dg')->where('id', 2)->value('stage'));

        $migration->down();

        $this->assertTrue(Schema::hasColumn('kelompok_dg', 'name'));
        $this->assertTrue(Schema::hasColumn('kelompok_dg', 'start_stage'));
        $this->assertTrue(Schema::hasColumn('kelompok_dg', 'current_stage'));
        $this->assertFalse(Schema::hasColumn('kelompok_dg', 'stage'));
        $this->assertSame('Kelompok', DB::table('kelompok_dg')->where('id', 1)->value('name'));
        $this->assertSame('DG 2', DB::table('kelompok_dg')->where('id', 1)->value('current_stage'));
        $this->assertSame('DG 2', DB::table('kelompok_dg')->where('id', 1)->value('start_stage'));
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kelompok_dg');

        parent::tearDown();
    }

    private function createLegacyTable(): void
    {
        Schema::dropIfExists('kelompok_dg');
        Schema::create('kelompok_dg', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('name')->default('Kelompok');
            $table->string('status')->default('active');
            $table->string('start_stage')->nullable();
            $table->string('current_stage')->nullable();
            $table->timestamps();

            $table->index('current_stage', 'discipleship_groups_current_stage_index');
            $table->index(['branch_id', 'status', 'name', 'id'], 'dg_branch_status_name_id_lazy_idx');
        });

        DB::table('kelompok_dg')->insert([
            [
                'id' => 1,
                'branch_id' => 1,
                'name' => 'Nama Lama',
                'status' => 'active',
                'start_stage' => 'DG 1',
                'current_stage' => 'DG 2',
                'created_at' => '2026-07-07 10:00:00',
                'updated_at' => '2026-07-07 10:00:00',
            ],
            [
                'id' => 2,
                'branch_id' => 1,
                'name' => 'Nama Lama 2',
                'status' => 'active',
                'start_stage' => 'DG 1',
                'current_stage' => null,
                'created_at' => '2026-07-07 10:00:00',
                'updated_at' => '2026-07-07 10:00:00',
            ],
        ]);
    }
}
