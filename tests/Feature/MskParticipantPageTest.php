<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MskParticipantPageTest extends TestCase
{
    public function test_legacy_msk_query_is_rejected(): void
    {
        $response = $this->get('/pemuridan/msk?page=msk_classes');

        $response->assertNotFound();
    }

    public function test_legacy_string_participant_route_is_rejected(): void
    {
        $this->createMskTables();
        $this->actingAsRecUser();

        $this->post('/pemuridan/msk/msk_legacy/sesi', [
            'session_numbers' => [1],
        ])->assertNotFound();
    }

    public function test_msk_page_renders_for_logged_in_branch_user(): void
    {
        $this->createMskTables();

        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/msk');

        $response->assertStatus(200);
        $response->assertSee('Kelas MSK');
    }

    public function test_msk_page_paginates_large_participant_lists(): void
    {
        $this->createMskTables();
        $rows = [];
        for ($i = 1; $i <= 120; $i++) {
            $rows[] = [
                'branch_id' => 1,
                'full_name' => sprintf('Peserta MSK %03d', $i),
                'batch_month' => '2026-06',
                'journey_bridge_status' => 'belum',
                'status' => 'active',
                'session_numbers' => json_encode([]),
                'photos' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('msk_participants')->insert($rows);
        $this->actingAsRecUser();

        $this->get('/pemuridan/msk?batch_month=all')
            ->assertOk()
            ->assertSee('Peserta MSK 001')
            ->assertSee('Peserta MSK 050')
            ->assertDontSee('Peserta MSK 051')
            ->assertSee('Halaman 1 dari 3');
    }

    public function test_store_msk_participant_persists_to_laravel_tables(): void
    {
        $this->createMskTables();

        $this->actingAsRecUser();

        $response = $this->post('/pemuridan/msk/peserta', [
            'action' => 'save_msk_participant',
            'full_name' => 'Peserta MSK Baru',
            'gender' => 'Laki-laki',
            'birth_date' => '2000-01-10',
            'birth_place' => 'Surabaya',
            'address' => 'Jl. Contoh',
            'email' => 'peserta@example.test',
            'whatsapp' => '081234567890',
            'batch_month' => '2026-06',
            'session_numbers' => ['1', '2'],
            'notes' => 'Catatan test',
        ]);

        $response->assertRedirect('/pemuridan/msk?batch_month=2026-06&saved=1');
        $this->assertDatabaseHas('msk_participants', [
            'branch_id' => 1,
            'full_name' => 'Peserta MSK Baru',
            'batch_month' => '2026-06',
        ]);

        $participantId = (int) DB::table('msk_participants')
            ->where('full_name', 'Peserta MSK Baru')
            ->value('id');

        $sessions = json_decode((string) DB::table('msk_participants')->where('id', $participantId)->value('session_numbers'), true);
        $this->assertSame([1, 2], $sessions);
    }

    public function test_branch_user_cannot_update_participant_from_another_branch(): void
    {
        $this->createMskTables();
        $participantId = DB::table('msk_participants')->insertGetId([
            'branch_id' => 2,
            'full_name' => 'Peserta GM',
            'batch_month' => '2026-06',
            'journey_bridge_status' => 'belum',
            'status' => 'active',
            'session_numbers' => json_encode([1]),
            'photos' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsRecUser();

        $this->post("/pemuridan/msk/{$participantId}/sesi", [
            'id' => $participantId,
            'session_numbers' => [1, 2, 3],
        ])->assertRedirect('/pemuridan/msk?error=invalid_msk_participant');

        $sessions = json_decode((string) DB::table('msk_participants')->where('id', $participantId)->value('session_numbers'), true);
        $this->assertSame([1], $sessions);
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
}
