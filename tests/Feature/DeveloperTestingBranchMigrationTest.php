<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeveloperTestingBranchMigrationTest extends TestCase
{
    public function test_migration_creates_developer_only_testing_branch(): void
    {
        Schema::dropIfExists('cabang');
        Schema::create('cabang', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('camp_gap_participant_target')->default(10);
            $table->unsignedInteger('msk_completion_target')->default(10);
            $table->unsignedInteger('dg1_completion_target')->default(10);
            $table->unsignedInteger('dg2_completion_target')->default(10);
            $table->unsignedInteger('dg3_completion_target')->default(10);
            $table->timestamps();
        });

        DB::table('cabang')->insert([
            'label' => 'Kutisari',
            'is_active' => true,
            'camp_gap_participant_target' => 11,
            'msk_completion_target' => 12,
            'dg1_completion_target' => 13,
            'dg2_completion_target' => 14,
            'dg3_completion_target' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_07_09_000001_create_developer_testing_branch.php');
        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('cabang', 'is_developer_only'));
        $this->assertSame(1, DB::table('cabang')->where('label', 'Testing')->count());
        $this->assertDatabaseHas('cabang', [
            'label' => 'Testing',
            'is_active' => true,
            'is_developer_only' => true,
            'camp_gap_participant_target' => 50,
            'msk_completion_target' => 50,
            'dg1_completion_target' => 50,
            'dg2_completion_target' => 50,
            'dg3_completion_target' => 50,
        ]);
        $this->assertDatabaseHas('cabang', [
            'label' => 'Kutisari',
            'is_developer_only' => false,
            'camp_gap_participant_target' => 11,
        ]);
    }
}
