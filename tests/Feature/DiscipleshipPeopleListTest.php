<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DiscipleshipPeopleListTest extends TestCase
{
    public function test_legacy_people_list_query_is_rejected(): void
    {
        $response = $this->get('/pemuridan/anggota?page=people_list');

        $response->assertNotFound();
    }

    public function test_people_list_page_renders_for_logged_in_branch_user(): void
    {
        $this->createDiscipleshipTables();
        DB::table('discipleship_people')->insert([
            [
                'branch_id' => 1,
                'full_name' => 'Anggota Kutisari',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 2,
                'full_name' => 'Anggota GM Rahasia',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/anggota');

        $response->assertStatus(200);
        $response->assertSee('Daftar Anggota DG');
        $response->assertSee('Anggota Kutisari');
        $response->assertDontSee('Anggota GM Rahasia');
        $response->assertDontSee('Selesai Camp GAP');
        $response->assertDontSee('Selesai RG');
        $response->assertDontSee('<th>Kontak</th>', false);
        $response->assertDontSee('<th>Jumlah Binaan</th>', false);
        $response->assertDontSee('people-contact-cell', false);
        $response->assertDontSee('people-child-count', false);
    }

    public function test_people_list_shows_dg_only_progress_track_and_ignores_legacy_bridge_filter(): void
    {
        $this->createDiscipleshipTables();
        $personId = DB::table('discipleship_people')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Peserta Progres DG',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_group_people')->insert([
            [
                'branch_id' => 1,
                'discipleship_group_id' => 1,
                'person_id' => $personId,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'closed',
                'started_on' => '2026-01-01',
                'ended_on' => '2026-03-01',
                'end_reason' => 'continued_to_child_group',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => 2,
                'person_id' => $personId,
                'role' => 'member',
                'stage' => 'DG 2',
                'status' => 'active',
                'started_on' => '2026-03-02',
                'ended_on' => null,
                'end_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $this->actingAsRecUser();

        $this->get('/pemuridan/anggota?progress=kgap_complete')
            ->assertOk()
            ->assertSee('Peserta Progres DG')
            ->assertSee('<option value="all" selected>Semua Peserta</option>', false)
            ->assertSee('people-progress-track', false)
            ->assertSee('people-progress-step is-complete', false)
            ->assertSee('people-progress-step is-current', false)
            ->assertSee('Sedang menjalani DG 2')
            ->assertDontSee('Selesai Camp GAP')
            ->assertDontSee('Selesai RG');
    }

    public function test_people_list_limits_large_datasets_and_keeps_query_count_constant(): void
    {
        $this->createDiscipleshipTables();
        $rows = [];
        for ($i = 1; $i <= 1000; $i++) {
            $rows[] = [
                'branch_id' => 1,
                'full_name' => sprintf('Peserta %04d', $i),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('discipleship_people')->insert($chunk);
        }
        $this->actingAsRecUser();
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $response = $this->get('/pemuridan/anggota');

        $response->assertOk()
            ->assertSee('Peserta 0001')
            ->assertSee('Peserta 0050')
            ->assertDontSee('Peserta 0051')
            ->assertSee('Halaman 1 dari 20');
        $this->assertLessThanOrEqual(10, $queries);
        $this->assertLessThan(250 * 1024, strlen((string) $response->getContent()));

        $this->get('/pemuridan/anggota?per_page=500')
            ->assertOk()
            ->assertSee('Peserta 0100')
            ->assertDontSee('Peserta 0101')
            ->assertSee('Halaman 1 dari 10');
    }

    private function createDiscipleshipTables(): void
    {
        Schema::dropIfExists('discipleship_group_people');
        Schema::dropIfExists('discipleship_relationships');
        Schema::dropIfExists('discipleship_groups');
        Schema::dropIfExists('discipleship_people');

        Schema::create('discipleship_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('status', 40)->default('active');
            $table->timestamps();
        });

        Schema::create('discipleship_groups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('name')->nullable();
            $table->string('status', 40)->default('active');
            $table->string('start_stage', 40)->nullable();
            $table->string('current_stage', 40)->nullable();
            $table->unsignedBigInteger('parent_group_id')->nullable();
            $table->unsignedBigInteger('source_group_id')->nullable();
            $table->unsignedBigInteger('initiated_by_person_id')->nullable();
            $table->date('multiplied_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_relationships', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('mentor_person_id')->nullable();
            $table->unsignedBigInteger('disciple_person_id')->nullable();
            $table->unsignedBigInteger('context_group_id')->nullable();
            $table->string('relation_type')->nullable();
            $table->string('stage_at_start')->nullable();
            $table->string('status', 40)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('reason_end')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_group_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('role')->nullable();
            $table->string('stage')->nullable();
            $table->string('status', 40)->default('active');
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->string('end_reason')->nullable();
            $table->timestamps();
        });
    }
}
