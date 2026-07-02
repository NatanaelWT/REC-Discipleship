<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpiritualJourneyPageTest extends TestCase
{
    public function test_legacy_spiritual_journey_query_is_rejected(): void
    {
        $response = $this->get('/pemuridan/spiritual-journey?page=spiritual_journey');

        $response->assertNotFound();
    }

    public function test_spiritual_journey_page_renders_for_logged_in_branch_user(): void
    {
        $this->createMskTables();
        $this->seedParticipant();

        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/spiritual-journey');

        $response->assertStatus(200);
        $response->assertSee('Spiritual Journey');
        $response->assertSee('Peserta Journey');
        $response->assertSee('discipleship-page-header__stats', false);
        $response->assertSee('discipleship-page-header__stat', false);
        $response->assertSee('Lihat profil');
        $response->assertDontSee('Lihat riwayat pemuridan');
        $response->assertSee('Profil peserta');
        $response->assertSee('Kontak dan akses');
        $response->assertSee('Foto dan keterangan');
        $response->assertSee('MSK dan pemuridan aktif');
        $response->assertSee('Riwayat pemuridan');
        $response->assertSee('data-spiritual-journey-view-title>Profil Peserta', false);
    }

    public function test_spiritual_journey_lazy_loads_rows_and_searches_server_side(): void
    {
        $this->createMskTables();
        $now = now();
        $participants = [];
        for ($index = 1; $index <= 125; $index++) {
            $participants[] = [
                'branch_id' => 1,
                'discipleship_person_id' => null,
                'full_name' => sprintf('Peserta Journey %03d', $index),
                'whatsapp' => sprintf('08120000%04d', $index),
                'batch_month' => '2026-06',
                'completed_at' => null,
                'journey_bridge_status' => 'belum',
                'status' => 'active',
                'session_numbers' => json_encode([1, 2]),
                'photos' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('msk_participants')->insert($participants);
        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/spiritual-journey');

        $response->assertOk();
        $response->assertSee('Peserta Journey 001');
        $response->assertSee('Peserta Journey 050');
        $response->assertDontSee('Peserta Journey 051');
        $response->assertDontSee('Peserta Journey 125');
        $response->assertSee('data-spiritual-journey-list', false);
        $response->assertSee('data-next-page="2"', false);
        $response->assertSee('data-spiritual-journey-search-form', false);
        $response->assertSee('data-spiritual-journey-search-input', false);
        $response->assertSee('data-spiritual-journey-search-row', false);
        $response->assertDontSee('class="rec-pagination"', false);
        $response->assertDontSee('type="submit">Cari</button>', false);

        $pageTwo = $this->get('/pemuridan/spiritual-journey/rows?page=2');
        $pageTwo->assertOk()
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('next_page', 3);
        $this->assertStringContainsString('Peserta Journey 051', (string) $pageTwo->json('html'));
        $this->assertStringContainsString('Peserta Journey 100', (string) $pageTwo->json('html'));
        $this->assertStringNotContainsString('Peserta Journey 101', (string) $pageTwo->json('html'));

        $search = $this->get('/pemuridan/spiritual-journey/rows?q=Journey+125');
        $search->assertOk()
            ->assertJsonPath('has_more', false);
        $this->assertStringContainsString('Peserta Journey 125', (string) $search->json('html'));
        $this->assertStringNotContainsString('Peserta Journey 001', (string) $search->json('html'));
    }

    public function test_spiritual_journey_filters_dg_participants_without_kgap(): void
    {
        $this->createMskTables();
        $this->createDiscipleshipTables();

        $dgWithoutKgapPersonId = DB::table('discipleship_people')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Peserta DG Belum KGAP',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $dgWithKgapPersonId = DB::table('discipleship_people')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Peserta DG Sudah KGAP',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $withoutDgPersonId = DB::table('discipleship_people')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Peserta Belum DG',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $groupId = DB::table('discipleship_groups')->insertGetId([
            'branch_id' => 1,
            'name' => 'Kelompok DG Test',
            'status' => 'active',
            'start_stage' => 'DG 1',
            'current_stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_group_people')->insert([
            [
                'branch_id' => 1,
                'discipleship_group_id' => $groupId,
                'person_id' => $dgWithoutKgapPersonId,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'active',
                'started_on' => now()->toDateString(),
                'ended_on' => null,
                'end_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => $groupId,
                'person_id' => $dgWithKgapPersonId,
                'role' => 'member',
                'stage' => 'DG 2',
                'status' => 'completed',
                'started_on' => now()->subMonths(3)->toDateString(),
                'ended_on' => now()->subMonth()->toDateString(),
                'end_reason' => 'group_completed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('msk_participants')->insert([
            [
                'branch_id' => 1,
                'discipleship_person_id' => $dgWithoutKgapPersonId,
                'full_name' => 'Peserta DG Belum KGAP',
                'batch_month' => '2026-06',
                'completed_at' => null,
                'journey_bridge_status' => 'sudah_rg',
                'status' => 'active',
                'session_numbers' => json_encode([1, 2]),
                'photos' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_person_id' => $dgWithKgapPersonId,
                'full_name' => 'Peserta DG Sudah KGAP',
                'batch_month' => '2026-06',
                'completed_at' => null,
                'journey_bridge_status' => 'sudah_kgap',
                'status' => 'active',
                'session_numbers' => json_encode([1, 2]),
                'photos' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_person_id' => $withoutDgPersonId,
                'full_name' => 'Peserta Belum DG',
                'batch_month' => '2026-06',
                'completed_at' => null,
                'journey_bridge_status' => 'belum',
                'status' => 'active',
                'session_numbers' => json_encode([1]),
                'photos' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_person_id' => null,
                'full_name' => 'Peserta Belum Terhubung',
                'batch_month' => '2026-06',
                'completed_at' => null,
                'journey_bridge_status' => 'belum',
                'status' => 'active',
                'session_numbers' => json_encode([1]),
                'photos' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/spiritual-journey?journey_filter=dg_without_kgap');

        $response->assertOk();
        $response->assertSee('Minimal DG 1, Belum Kamp GAP');
        $response->assertSee('Peserta DG Belum KGAP');
        $response->assertDontSee('Peserta DG Sudah KGAP');
        $response->assertDontSee('Peserta Belum DG');
        $response->assertDontSee('Peserta Belum Terhubung');
    }

    public function test_bridge_status_update_persists_to_laravel_table(): void
    {
        $this->createMskTables();
        $participantId = $this->seedParticipant();

        $this->actingAsRecUser();

        $response = $this->post("/pemuridan/spiritual-journey/{$participantId}/bridge-status", [
            'action' => 'save_journey_bridge_status',
            'id' => $participantId,
            'journey_bridge_status' => 'sudah_kgap',
        ]);

        $response->assertRedirect('/pemuridan/spiritual-journey?saved=1');
        $this->assertDatabaseHas('msk_participants', [
            'branch_id' => 1,
            'id' => $participantId,
            'journey_bridge_status' => 'sudah_kgap',
        ]);
    }

    private function createMskTables(): void
    {
        Schema::dropIfExists('msk_participants');

        Schema::create('msk_participants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('discipleship_person_id')->nullable()->unique();
            $table->string('full_name')->nullable();
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_day_month')->nullable();
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
            $table->string('gender')->nullable();
            $table->string('status', 40)->default('active');
            $table->text('notes')->nullable();
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

    private function seedParticipant(): int
    {
        $participantId = DB::table('msk_participants')->insertGetId([
            'branch_id' => 1,
            'discipleship_person_id' => null,
            'full_name' => 'Peserta Journey',
            'batch_month' => '2026-06',
            'completed_at' => null,
            'journey_bridge_status' => 'belum',
            'status' => 'active',
            'session_numbers' => json_encode([1, 2]),
            'photos' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $participantId;
    }
}
