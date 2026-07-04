<?php

namespace Tests\Feature;

use App\Services\DiscipleshipTargets\DiscipleshipTargetReader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DiscipleshipTargetCacheTest extends TestCase
{
    public function test_target_cache_is_reused_and_invalidated_after_save(): void
    {
        Schema::create('cabang', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('camp_gap_participant_target')->default(50);
            $table->unsignedInteger('msk_completion_target')->default(50);
            $table->unsignedInteger('dg1_completion_target')->default(50);
            $table->unsignedInteger('dg2_completion_target')->default(50);
            $table->unsignedInteger('dg3_completion_target')->default(50);
            $table->timestamps();
        });
        DB::table('cabang')->insert([
            'id' => 1,
            'label' => 'Kutisari',
            'is_active' => true,
            'camp_gap_participant_target' => 10,
            'msk_completion_target' => 10,
            'dg1_completion_target' => 10,
            'dg2_completion_target' => 10,
            'dg3_completion_target' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reader = app(DiscipleshipTargetReader::class);
        $this->assertSame(10, $reader->formValuesForBranches(['kutisari'])['kutisari']['dg_total_people']);

        $targetQueries = 0;
        DB::listen(static function ($query) use (&$targetQueries): void {
            if (str_contains($query->sql, 'cabang')) {
                $targetQueries++;
            }
        });
        $this->assertSame(10, $reader->formValuesForBranches(['kutisari'])['kutisari']['dg_total_people']);
        $this->assertSame(0, $targetQueries);

        $reader->saveBranch('kutisari', [
            'camp_gap_participant_target' => 30,
            'msk_completion_target' => 30,
            'dg1_completion_target' => 30,
            'dg2_completion_target' => 30,
            'dg3_completion_target' => 30,
        ]);

        $this->assertSame(30, $reader->formValuesForBranches(['kutisari'])['kutisari']['dg_total_people']);
    }
}
