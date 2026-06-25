<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DiscipleshipGroupListPerformanceTest extends TestCase
{
    public function test_group_list_only_loads_the_current_page(): void
    {
        $this->createTables();
        $people = [];
        for ($index = 1; $index <= 600; $index++) {
            $people[] = ['branch_id' => 1, 'full_name' => sprintf('Orang %04d', $index), 'status' => 'active'];
        }
        DB::table('discipleship_people')->insert($people);
        $groups = [];
        for ($index = 1; $index <= 300; $index++) {
            $groups[] = ['branch_id' => 1, 'name' => sprintf('Kelompok %04d', $index), 'status' => 'active', 'current_stage' => 'DG 1'];
        }
        DB::table('discipleship_groups')->insert($groups);
        $links = [];
        for ($index = 1; $index <= 300; $index++) {
            $links[] = ['branch_id' => 1, 'discipleship_group_id' => $index, 'person_id' => $index, 'role' => 'leader', 'stage' => null, 'status' => 'active'];
            $links[] = ['branch_id' => 1, 'discipleship_group_id' => $index, 'person_id' => 300 + $index, 'role' => 'member', 'stage' => 'DG 1', 'status' => 'active'];
        }
        DB::table('discipleship_group_people')->insert($links);
        $this->actingAsRecUser();
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $response = $this->get('/pemuridan/kelompok');

        $response->assertOk()
            ->assertSee('Kelompok 0001')
            ->assertSee('Kelompok 0050')
            ->assertDontSee('Kelompok 0051')
            ->assertSee('Halaman 1 dari 6');
        $this->assertLessThanOrEqual(8, $queries);
        $this->assertLessThan(250 * 1024, strlen((string) $response->getContent()));
    }

    public function test_inactive_group_shows_its_last_leader_and_members(): void
    {
        $this->createTables();
        DB::table('discipleship_people')->insert([
            ['id' => 1, 'branch_id' => 1, 'full_name' => 'Pemimpin Historis', 'status' => 'active'],
            ['id' => 2, 'branch_id' => 1, 'full_name' => 'Pendamping Historis', 'status' => 'active'],
            ['id' => 3, 'branch_id' => 1, 'full_name' => 'Anggota Historis', 'status' => 'active'],
        ]);
        DB::table('discipleship_groups')->insert([
            'id' => 1,
            'branch_id' => 1,
            'name' => 'Kelompok Selesai',
            'status' => 'completed',
            'current_stage' => 'DG 2',
        ]);
        DB::table('discipleship_group_people')->insert([
            ['branch_id' => 1, 'discipleship_group_id' => 1, 'person_id' => 1, 'role' => 'leader', 'stage' => null, 'status' => 'closed', 'ended_on' => '2026-05-01'],
            ['branch_id' => 1, 'discipleship_group_id' => 1, 'person_id' => 2, 'role' => 'co_leader', 'stage' => null, 'status' => 'closed', 'ended_on' => '2026-05-01'],
            ['branch_id' => 1, 'discipleship_group_id' => 1, 'person_id' => 3, 'role' => 'member', 'stage' => 'DG 2', 'status' => 'closed', 'ended_on' => '2026-05-01'],
        ]);
        $this->actingAsRecUser();

        $this->get('/pemuridan/kelompok?status=inactive')
            ->assertOk()
            ->assertSee('Pemimpin Historis')
            ->assertSee('Riwayat pendamping: Pendamping Historis')
            ->assertSee('Anggota Historis')
            ->assertSee('1 peserta tercatat')
            ->assertDontSee('Belum ada peserta')
            ->assertDontSee('Tanpa pendamping');
    }

    public function test_group_without_any_people_history_is_not_listed_or_counted(): void
    {
        $this->createTables();
        DB::table('discipleship_groups')->insert([
            'branch_id' => 1,
            'name' => 'Kelompok Yatim',
            'status' => 'completed',
            'current_stage' => 'DG 1',
        ]);
        $this->actingAsRecUser();

        $this->get('/pemuridan/kelompok')
            ->assertOk()
            ->assertDontSee('Kelompok Yatim')
            ->assertSee('data-groups-stat="total">0', false)
            ->assertSee('Belum ada kelompok.');
    }

    private function createTables(): void
    {
        Schema::dropIfExists('discipleship_group_people');
        Schema::dropIfExists('discipleship_groups');
        Schema::dropIfExists('discipleship_people');
        Schema::create('discipleship_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name');
            $table->string('status')->default('active');
        });
        Schema::create('discipleship_groups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('name');
            $table->string('status')->default('active');
            $table->string('start_stage')->nullable();
            $table->string('current_stage')->nullable();
            $table->timestamps();
        });
        Schema::create('discipleship_group_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('discipleship_group_id');
            $table->unsignedBigInteger('person_id');
            $table->string('role');
            $table->string('stage')->nullable();
            $table->string('status')->default('active');
            $table->date('ended_on')->nullable();
        });
    }
}
