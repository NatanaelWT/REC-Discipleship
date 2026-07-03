<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MskParticipantDuplicateCleanupMigrationTest extends TestCase
{
    public function test_placeholder_duplicate_is_merged_into_complete_msk_participant(): void
    {
        $this->createMskTable();

        DB::table('msk_participants')->insert([
            [
                'id' => 10,
                'branch_id' => 1,
                'discipleship_person_id' => null,
                'full_name' => 'Axel Christmas Eltho',
                'gender' => 'Laki-laki',
                'whatsapp' => '81326729382',
                'batch_month' => '2025-06',
                'notes' => 'Catatan MSK',
                'completed_at' => null,
                'journey_bridge_status' => 'belum',
                'status' => 'active',
                'session_numbers' => json_encode(range(1, 12)),
                'photos' => json_encode([['path' => 'uploads/msk/axel.jpg', 'name' => 'Foto Axel']]),
                'created_at' => '2026-07-01 10:00:00',
                'updated_at' => '2026-07-01 12:00:00',
            ],
            [
                'id' => 11,
                'branch_id' => 1,
                'discipleship_person_id' => 77,
                'full_name' => 'Axel  Christmas  Eltho',
                'gender' => null,
                'whatsapp' => '081326729382',
                'batch_month' => null,
                'notes' => 'Catatan pemuridan',
                'completed_at' => null,
                'journey_bridge_status' => 'belum',
                'status' => 'active',
                'session_numbers' => json_encode([]),
                'photos' => json_encode([]),
                'created_at' => '2026-06-30 09:00:00',
                'updated_at' => '2026-07-01 11:00:00',
            ],
        ]);

        $migration = require database_path('migrations/2026_07_02_000004_merge_duplicate_msk_participants.php');
        $migration->up();

        $this->assertDatabaseMissing('msk_participants', ['id' => 11]);
        $this->assertDatabaseHas('msk_participants', [
            'id' => 10,
            'discipleship_person_id' => 77,
            'full_name' => 'Axel Christmas Eltho',
            'batch_month' => '2025-06',
        ]);

        $participant = DB::table('msk_participants')->where('id', 10)->first();
        $this->assertSame(range(1, 12), json_decode((string) $participant->session_numbers, true));
        $this->assertStringContainsString('Catatan MSK', (string) $participant->notes);
        $this->assertStringContainsString('Catatan pemuridan', (string) $participant->notes);
        $this->assertSame('2026-06-30 09:00:00', (string) $participant->created_at);
        $this->assertSame('2026-07-01 12:00:00', (string) $participant->updated_at);
    }

    public function test_duplicate_participants_with_different_person_links_merge_discipleship_history(): void
    {
        $this->createMskTable();
        $this->createDiscipleshipTables();

        DB::table('discipleship_people')->insert([
            [
                'id' => 77,
                'branch_id' => 1,
                'status' => 'active',
                'notes' => 'Person canonical',
                'created_at' => '2026-07-01 08:00:00',
                'updated_at' => '2026-07-01 09:00:00',
            ],
            [
                'id' => 88,
                'branch_id' => 1,
                'status' => 'inactive',
                'notes' => 'Person duplikat',
                'created_at' => '2026-06-30 08:00:00',
                'updated_at' => '2026-07-01 10:00:00',
            ],
        ]);
        DB::table('msk_participants')->insert([
            [
                'id' => 10,
                'branch_id' => 1,
                'discipleship_person_id' => 77,
                'full_name' => 'Nama Sama',
                'whatsapp' => '081300000001',
                'batch_month' => '2025-06',
                'journey_bridge_status' => 'belum',
                'status' => 'active',
                'session_numbers' => json_encode(range(1, 12)),
                'photos' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'branch_id' => 1,
                'discipleship_person_id' => 88,
                'full_name' => 'Nama Sama',
                'whatsapp' => '81300000001',
                'batch_month' => null,
                'journey_bridge_status' => 'belum',
                'status' => 'active',
                'session_numbers' => json_encode([]),
                'photos' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('discipleship_group_people')->insert([
            'branch_id' => 1,
            'person_id' => 88,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_relationships')->insert([
            'branch_id' => 1,
            'mentor_person_id' => null,
            'disciple_person_id' => 88,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_groups')->insert([
            'branch_id' => 1,
            'initiated_by_person_id' => 88,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_meeting_reports')->insert([
            'branch_id' => 1,
            'leader_person_id' => 88,
            'absences' => json_encode([['person_id' => 88, 'person_name_snapshot' => 'Nama Sama']]),
            'meditation_sharers' => json_encode([['person_id' => 88, 'person_name_snapshot' => 'Nama Sama']]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_feedbacks')->insert([
            'branch_id' => 1,
            'leader_person_id' => 88,
            'respondent_person_id' => 88,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_manual_journey_records')->insert([
            [
                'branch_id' => 1,
                'person_id' => 77,
                'stage' => 'DG 1',
                'completed_on' => '2026-01-10',
                'notes' => 'Manual canonical',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'person_id' => 88,
                'stage' => 'DG 1',
                'completed_on' => '2026-01-05',
                'notes' => 'Manual duplikat',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'person_id' => 88,
                'stage' => 'DG 2',
                'completed_on' => '2026-02-01',
                'notes' => 'Manual stage lain',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration = require database_path('migrations/2026_07_02_000004_merge_duplicate_msk_participants.php');
        $migration->up();

        $this->assertDatabaseMissing('msk_participants', ['id' => 11]);
        $this->assertDatabaseMissing('discipleship_people', ['id' => 88]);
        $this->assertDatabaseHas('msk_participants', [
            'id' => 10,
            'discipleship_person_id' => 77,
            'batch_month' => '2025-06',
        ]);
        $this->assertDatabaseHas('discipleship_people', [
            'id' => 77,
            'status' => 'active',
        ]);
        $this->assertSame('Person canonical'."\n\n".'Person duplikat', DB::table('discipleship_people')->where('id', 77)->value('notes'));
        $this->assertDatabaseHas('discipleship_group_people', ['person_id' => 77]);
        $this->assertDatabaseHas('discipleship_relationships', ['disciple_person_id' => 77]);
        $this->assertDatabaseHas('discipleship_groups', ['initiated_by_person_id' => 77]);
        $this->assertDatabaseHas('discipleship_meeting_reports', ['leader_person_id' => 77]);
        $this->assertDatabaseHas('discipleship_feedbacks', ['leader_person_id' => 77, 'respondent_person_id' => 77]);

        $report = DB::table('discipleship_meeting_reports')->first();
        $this->assertSame(77, json_decode((string) $report->absences, true)[0]['person_id']);
        $this->assertSame(77, json_decode((string) $report->meditation_sharers, true)[0]['person_id']);
        $this->assertSame(2, DB::table('discipleship_manual_journey_records')->where('person_id', 77)->count());
        $this->assertDatabaseHas('discipleship_manual_journey_records', [
            'person_id' => 77,
            'stage' => 'DG 1',
            'completed_on' => '2026-01-05',
        ]);
    }

    private function createMskTable(): void
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
        foreach ([
            'discipleship_manual_journey_records',
            'discipleship_feedbacks',
            'discipleship_meeting_reports',
            'discipleship_groups',
            'discipleship_relationships',
            'discipleship_group_people',
            'discipleship_people',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('discipleship_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('discipleship_group_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->timestamps();
        });
        Schema::create('discipleship_relationships', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('mentor_person_id')->nullable();
            $table->unsignedBigInteger('disciple_person_id')->nullable();
            $table->timestamps();
        });
        Schema::create('discipleship_groups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('initiated_by_person_id')->nullable();
            $table->timestamps();
        });
        Schema::create('discipleship_meeting_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->json('absences')->nullable();
            $table->json('meditation_sharers')->nullable();
            $table->timestamps();
        });
        Schema::create('discipleship_feedbacks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->unsignedBigInteger('respondent_person_id')->nullable();
            $table->timestamps();
        });
        Schema::create('discipleship_manual_journey_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('person_id');
            $table->string('stage');
            $table->date('completed_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'person_id', 'stage'], 'manual_journey_branch_person_stage_unique');
        });
    }
}
