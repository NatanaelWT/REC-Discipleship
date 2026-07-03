<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PeopleMergeMigrationTest extends TestCase
{
    public function test_disciple_profiles_are_merged_into_people_and_references_are_remapped(): void
    {
        $this->createLegacyTables();

        DB::table('discipleship_people')->insert([
            [
                'id' => 10,
                'branch_id' => 1,
                'full_name' => 'Linked DG',
                'phone' => '0811111111',
                'gender' => 'Perempuan',
                'status' => 'inactive',
                'notes' => 'Catatan DG',
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-02 00:00:00',
            ],
            [
                'id' => 11,
                'branch_id' => 1,
                'full_name' => 'Only DG',
                'phone' => '0822222222',
                'gender' => 'Laki-laki',
                'status' => 'active',
                'notes' => 'Only DG notes',
                'created_at' => '2026-02-01 00:00:00',
                'updated_at' => '2026-02-02 00:00:00',
            ],
        ]);

        DB::table('msk_participants')->insert([
            [
                'id' => 100,
                'branch_id' => 1,
                'discipleship_person_id' => 10,
                'full_name' => 'Linked MSK',
                'gender' => null,
                'whatsapp' => null,
                'batch_month' => '2026-06',
                'notes' => 'Catatan MSK',
                'completed_at' => null,
                'journey_bridge_status' => 'belum',
                'status' => 'active',
                'session_numbers' => json_encode([1, 2]),
                'photos' => json_encode([]),
                'created_at' => '2026-01-03 00:00:00',
                'updated_at' => '2026-01-04 00:00:00',
            ],
            [
                'id' => 101,
                'branch_id' => 1,
                'discipleship_person_id' => null,
                'full_name' => 'MSK Only',
                'gender' => 'Perempuan',
                'whatsapp' => '0833333333',
                'batch_month' => '2026-06',
                'notes' => null,
                'completed_at' => null,
                'journey_bridge_status' => 'belum',
                'status' => 'active',
                'session_numbers' => json_encode([1]),
                'photos' => json_encode([]),
                'created_at' => '2026-03-01 00:00:00',
                'updated_at' => '2026-03-02 00:00:00',
            ],
        ]);

        $this->seedLegacyReferences();

        $migration = require database_path('migrations/2026_07_03_000001_merge_discipleship_people_into_people.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('people'));
        $this->assertFalse(Schema::hasTable('discipleship_people'));
        $this->assertFalse(Schema::hasTable('msk_participants'));
        $this->assertFalse(Schema::hasColumn('people', 'discipleship_person_id'));

        $this->assertDatabaseHas('people', [
            'id' => 100,
            'full_name' => 'Linked MSK',
            'gender' => 'Perempuan',
            'whatsapp' => '0811111111',
            'status' => 'inactive',
            'notes' => "Catatan MSK\n\nCatatan pemuridan:\nCatatan DG",
        ]);
        $this->assertDatabaseHas('people', [
            'id' => 101,
            'full_name' => 'MSK Only',
        ]);

        $placeholderId = (int) DB::table('people')->where('full_name', 'Only DG')->value('id');
        $this->assertGreaterThan(0, $placeholderId);
        $this->assertNotSame(11, $placeholderId);

        $this->assertDatabaseHas('discipleship_group_people', ['person_id' => 100]);
        $this->assertDatabaseHas('discipleship_group_people', ['person_id' => $placeholderId]);
        $this->assertDatabaseHas('discipleship_relationships', [
            'mentor_person_id' => 100,
            'disciple_person_id' => $placeholderId,
        ]);
        $this->assertDatabaseHas('discipleship_groups', ['initiated_by_person_id' => 100]);
        $this->assertDatabaseHas('discipleship_group_multiplications', ['initiated_by_person_id' => $placeholderId]);
        $this->assertDatabaseHas('discipleship_feedbacks', [
            'leader_person_id' => 100,
            'respondent_person_id' => $placeholderId,
        ]);
        $this->assertDatabaseHas('discipleship_manual_journey_records', ['person_id' => $placeholderId]);
        $this->assertDatabaseHas('discipleship_meeting_report_absences', ['person_id' => 100]);
        $this->assertDatabaseHas('discipleship_meeting_report_meditation_sharers', ['person_id' => $placeholderId]);

        $report = DB::table('discipleship_meeting_reports')->where('id', 1)->first();
        $this->assertSame(100, (int) $report->leader_person_id);
        $this->assertSame(
            [['person_id' => 100], ['person_id' => $placeholderId]],
            json_decode((string) $report->absences, true),
        );
        $this->assertSame(
            [['person_id' => $placeholderId]],
            json_decode((string) $report->meditation_sharers, true),
        );
    }

    public function test_duplicate_discipleship_person_link_blocks_merge(): void
    {
        $this->createLegacyTables();

        DB::table('discipleship_people')->insert([
            'id' => 10,
            'branch_id' => 1,
            'full_name' => 'Duplicate Link',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('msk_participants')->insert([
            [
                'id' => 100,
                'branch_id' => 1,
                'discipleship_person_id' => 10,
                'full_name' => 'Duplicate Link A',
                'status' => 'active',
                'session_numbers' => json_encode([]),
                'photos' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 101,
                'branch_id' => 1,
                'discipleship_person_id' => 10,
                'full_name' => 'Duplicate Link B',
                'status' => 'active',
                'session_numbers' => json_encode([]),
                'photos' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('linked to multiple MSK participants');

        $migration = require database_path('migrations/2026_07_03_000001_merge_discipleship_people_into_people.php');
        $migration->up();
    }

    private function createLegacyTables(): void
    {
        foreach ([
            'people',
            'msk_participant_photos',
            'msk_participant_sessions',
            'msk_participants',
            'discipleship_meeting_report_meditation_sharers',
            'discipleship_meeting_report_absences',
            'discipleship_manual_journey_records',
            'discipleship_feedbacks',
            'discipleship_meeting_reports',
            'discipleship_group_multiplications',
            'discipleship_groups',
            'discipleship_relationships',
            'discipleship_group_leaderships',
            'discipleship_group_memberships',
            'discipleship_group_people',
            'discipleship_people',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('discipleship_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();
            $table->string('status', 40)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('msk_participants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('discipleship_person_id')->nullable();
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
            $table->string('status', 40)->default('active');
            $table->json('session_numbers')->nullable();
            $table->json('photos')->nullable();
            $table->timestamps();
        });

        foreach ([
            'discipleship_group_people' => ['person_id'],
            'discipleship_group_memberships' => ['person_id'],
            'discipleship_group_leaderships' => ['person_id'],
            'discipleship_groups' => ['initiated_by_person_id'],
            'discipleship_group_multiplications' => ['initiated_by_person_id'],
            'discipleship_feedbacks' => ['leader_person_id', 'respondent_person_id'],
            'discipleship_manual_journey_records' => ['branch_id', 'person_id', 'stage'],
            'discipleship_meeting_report_absences' => ['person_id'],
            'discipleship_meeting_report_meditation_sharers' => ['person_id'],
        ] as $tableName => $columns) {
            Schema::create($tableName, function (Blueprint $table) use ($columns): void {
                $table->id();
                foreach ($columns as $column) {
                    if ($column === 'stage') {
                        $table->string($column)->nullable();
                    } else {
                        $table->unsignedBigInteger($column)->nullable();
                    }
                }
                $table->timestamps();
            });
        }

        Schema::create('discipleship_relationships', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mentor_person_id')->nullable();
            $table->unsignedBigInteger('disciple_person_id')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_meeting_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->json('absences')->nullable();
            $table->json('meditation_sharers')->nullable();
            $table->timestamps();
        });
    }

    private function seedLegacyReferences(): void
    {
        DB::table('discipleship_group_people')->insert([
            ['person_id' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['person_id' => 11, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('discipleship_relationships')->insert([
            'mentor_person_id' => 10,
            'disciple_person_id' => 11,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_groups')->insert([
            'initiated_by_person_id' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_group_multiplications')->insert([
            'initiated_by_person_id' => 11,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_feedbacks')->insert([
            'leader_person_id' => 10,
            'respondent_person_id' => 11,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_manual_journey_records')->insert([
            'branch_id' => 1,
            'person_id' => 11,
            'stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_meeting_report_absences')->insert([
            'person_id' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_meeting_report_meditation_sharers')->insert([
            'person_id' => 11,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_meeting_reports')->insert([
            'id' => 1,
            'leader_person_id' => 10,
            'absences' => json_encode([['person_id' => 10], ['person_id' => 11]]),
            'meditation_sharers' => json_encode([['person_id' => 11]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
