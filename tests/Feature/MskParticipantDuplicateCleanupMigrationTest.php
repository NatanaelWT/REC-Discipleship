<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
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

    public function test_duplicate_participants_with_different_person_links_stop_cleanup(): void
    {
        $this->createMskTable();

        DB::table('msk_participants')->insert([
            [
                'branch_id' => 1,
                'discipleship_person_id' => 77,
                'full_name' => 'Nama Sama',
                'whatsapp' => '081300000001',
                'journey_bridge_status' => 'belum',
                'status' => 'active',
                'session_numbers' => json_encode([]),
                'photos' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_person_id' => 88,
                'full_name' => 'Nama Sama',
                'whatsapp' => '81300000001',
                'journey_bridge_status' => 'belum',
                'status' => 'active',
                'session_numbers' => json_encode([]),
                'photos' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->expectException(RuntimeException::class);

        $migration = require database_path('migrations/2026_07_02_000004_merge_duplicate_msk_participants.php');
        $migration->up();
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
}
