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
