<?php

namespace Tests\Feature;

use App\Services\Auth\CurrentUserContext;
use App\Services\Branches\BranchCatalog;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Services\Discipleship\DgProgressStateResolver;
use App\Services\DiscipleshipTargets\DiscipleshipTargetReader;
use App\Services\MskParticipants\MskParticipantHistoryData;
use App\Services\MskParticipants\MskParticipantPageData;
use App\Services\MskParticipants\MskParticipantProfileData;
use App\Services\SpiritualJourney\SpiritualJourneyPageData;
use App\Support\RuntimeBootstrap;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PageDataQueryEfficiencyTest extends TestCase
{
    public function test_msk_summary_query_count_is_constant_for_large_result_set(): void
    {
        RuntimeBootstrap::load();
        $this->createCanonicalReadTables();
        $this->seedParticipants(1000);
        $this->actingAsRecUser();
        app(BranchCatalog::class)->options();

        $history = Mockery::mock(MskParticipantHistoryData::class);
        $history->shouldNotReceive('forParticipants');
        $profiles = Mockery::mock(MskParticipantProfileData::class);
        $profiles->shouldNotReceive('forParticipants');
        $service = new MskParticipantPageData($history, $profiles);
        $queries = $this->captureQueries(fn (): array => $service->paginatedRowsForCurrentContext(
            Request::create('/pemuridan/msk', 'GET', ['batch_month' => 'all']),
        ));

        $this->assertSame(1000, $queries['result']['totalParticipantsFiltered']);
        $this->assertCount(50, $queries['result']['mskClasses']);
        $this->assertLessThanOrEqual(3, count($queries['sql']));
        $this->assertFalse(collect($queries['sql'])->contains(
            static fn (string $sql): bool => str_contains(strtolower($sql), 'select "session_numbers"'),
        ));
    }

    public function test_spiritual_journey_uses_database_aggregates_without_schema_queries(): void
    {
        RuntimeBootstrap::load();
        $this->createCanonicalReadTables();
        $this->seedParticipants(1000);
        $this->actingAsRecUser();
        $branches = app(BranchCatalog::class);
        $branches->options();
        $request = Request::create('/pemuridan/spiritual-journey', 'GET');
        $scope = new CurrentDiscipleshipScope($request, app(CurrentUserContext::class), $branches);
        $history = Mockery::mock(MskParticipantHistoryData::class);
        $history->shouldNotReceive('forParticipants');
        $profiles = Mockery::mock(MskParticipantProfileData::class);
        $profiles->shouldNotReceive('forParticipants');
        $targets = Mockery::mock(DiscipleshipTargetReader::class);
        $targets->shouldReceive('formValuesForBranches')->once()->andReturn([]);
        $service = new SpiritualJourneyPageData(
            $targets,
            $scope,
            new DgProgressStateResolver,
            $history,
            $profiles,
        );
        $queries = $this->captureQueries(fn (): array => $service->paginatedRowsForCurrentContext($request));

        $this->assertSame(1000, $queries['result']['spiritualJourneyStats']['total']);
        $this->assertCount(50, $queries['result']['spiritualJourneyRows']);
        $this->assertLessThanOrEqual(7, count($queries['sql']));
        $this->assertFalse(collect($queries['sql'])->contains(
            static fn (string $sql): bool => str_contains(strtolower($sql), 'sqlite_master')
                || str_contains(strtolower($sql), 'information_schema'),
        ));
    }

    /** @return array{result:array<string,mixed>,sql:array<int,string>} */
    private function captureQueries(callable $callback): array
    {
        $sql = [];
        DB::listen(static function ($query) use (&$sql): void {
            $sql[] = $query->sql;
        });

        return ['result' => $callback(), 'sql' => $sql];
    }

    private function createCanonicalReadTables(): void
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(BranchCatalog::class)->clearCache();

        Schema::create('orang', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name')->nullable();
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('batch_month')->nullable();
            $table->text('notes')->nullable();
            $table->string('completed_at')->nullable();
            $table->string('journey_bridge_status')->default('belum');
            $table->string('status')->default('active');
            $table->json('session_numbers')->nullable();
            $table->json('photos')->nullable();
            $table->timestamps();
        });
        Schema::create('keanggotaan_kelompok_dg', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->unsignedBigInteger('person_id');
            $table->string('role')->default('member');
            $table->string('stage')->nullable();
            $table->string('status')->default('active');
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->string('end_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('dg_manual', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('person_id');
            $table->string('stage');
            $table->date('completed_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    private function seedParticipants(int $count): void
    {
        $now = now();
        $rows = [];
        for ($index = 1; $index <= $count; $index++) {
            $sessionCount = $index % 13;
            $rows[] = [
                'branch_id' => 1,
                'full_name' => sprintf('Peserta %04d', $index),
                'batch_month' => '2026-06',
                'journey_bridge_status' => 'belum',
                'status' => 'active',
                'session_numbers' => json_encode($sessionCount > 0 ? range(1, $sessionCount) : []),
                'photos' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($rows) === 200) {
                DB::table('orang')->insert($rows);
                $rows = [];
            }
        }
        if ($rows !== []) {
            DB::table('orang')->insert($rows);
        }
    }
}
