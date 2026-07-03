<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PeopleCanonicalDuplicateCleanupMigrationTest extends TestCase
{
    public function test_duplicate_people_with_same_normalized_whatsapp_merge_into_discipleship_person(): void
    {
        $this->createTables();

        DB::table('people')->insert([
            [
                'id' => 10,
                'branch_id' => 1,
                'full_name' => 'Marliana',
                'batch_month' => null,
                'completed_at' => null,
                'whatsapp' => '082233698003',
                'status' => 'active',
                'notes' => 'Catatan pemuridan',
                'session_numbers' => json_encode([]),
                'photos' => json_encode([]),
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-02 00:00:00',
            ],
            [
                'id' => 20,
                'branch_id' => 1,
                'full_name' => 'Marliana',
                'whatsapp' => '+6282233698003',
                'batch_month' => '2024-01',
                'completed_at' => '2026-04-01T16:08:27+07:00',
                'status' => 'active',
                'notes' => 'Catatan MSK',
                'session_numbers' => json_encode(range(1, 12)),
                'photos' => json_encode([['path' => 'uploads/msk/marliana.jpg', 'name' => 'Marliana.jpg']]),
                'created_at' => '2026-02-01 00:00:00',
                'updated_at' => '2026-02-02 00:00:00',
            ],
            [
                'id' => 30,
                'branch_id' => 1,
                'full_name' => 'Marliana',
                'batch_month' => null,
                'completed_at' => null,
                'whatsapp' => null,
                'notes' => null,
                'status' => 'active',
                'session_numbers' => json_encode([]),
                'photos' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('discipleship_group_people')->insert([
            'person_id' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_relationships')->insert([
            'mentor_person_id' => 10,
            'disciple_person_id' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_meeting_reports')->insert([
            'id' => 1,
            'leader_person_id' => 10,
            'absences' => json_encode([['person_id' => 20]]),
            'meditation_sharers' => json_encode([['person_id' => 20]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_07_03_000002_merge_duplicate_canonical_people.php');
        $migration->up();

        $this->assertDatabaseHas('people', [
            'id' => 10,
            'full_name' => 'Marliana',
            'whatsapp' => '082233698003',
            'batch_month' => '2024-01',
            'completed_at' => '2026-04-01T16:08:27+07:00',
        ]);
        $this->assertDatabaseMissing('people', ['id' => 20]);
        $this->assertDatabaseHas('people', ['id' => 30]);

        $person = DB::table('people')->where('id', 10)->first();
        $this->assertSame(range(1, 12), json_decode((string) $person->session_numbers, true));
        $this->assertSame([['path' => 'uploads/msk/marliana.jpg', 'name' => 'Marliana.jpg']], json_decode((string) $person->photos, true));
        $this->assertStringContainsString('Catatan pemuridan', (string) $person->notes);
        $this->assertStringContainsString('Catatan MSK', (string) $person->notes);

        $report = DB::table('discipleship_meeting_reports')->where('id', 1)->first();
        $this->assertSame(10, (int) $report->leader_person_id);
        $this->assertSame([['person_id' => 10]], json_decode((string) $report->absences, true));
        $this->assertSame([['person_id' => 10]], json_decode((string) $report->meditation_sharers, true));
    }

    private function createTables(): void
    {
        foreach ([
            'discipleship_feedbacks',
            'discipleship_meeting_reports',
            'discipleship_relationships',
            'discipleship_group_people',
            'people',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
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

        Schema::create('discipleship_group_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_relationships', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mentor_person_id')->nullable();
            $table->unsignedBigInteger('disciple_person_id')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_feedbacks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->unsignedBigInteger('respondent_person_id')->nullable();
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
}
