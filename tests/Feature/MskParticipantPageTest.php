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

    public function test_msk_page_renders_for_logged_in_branch_user(): void
    {
        $this->createMskTables();

        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/msk');

        $response->assertStatus(200);
        $response->assertSee('Kelas MSK');
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

        $this->assertDatabaseHas('msk_participant_sessions', [
            'msk_participant_id' => $participantId,
            'session_number' => 1,
        ]);
        $this->assertDatabaseHas('msk_participant_sessions', [
            'msk_participant_id' => $participantId,
            'session_number' => 2,
        ]);
    }

    private function createMskTables(): void
    {
        Schema::dropIfExists('msk_participant_photos');
        Schema::dropIfExists('msk_participant_sessions');
        Schema::dropIfExists('msk_participants');

        Schema::create('msk_participants', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id');
            $table->unsignedBigInteger('branch_id');
            $table->string('member_public_id')->nullable();
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
            $table->timestamps();
        });

        Schema::create('msk_participant_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('msk_participant_id');
            $table->unsignedTinyInteger('session_number');
            $table->timestamps();
        });

        Schema::create('msk_participant_photos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('msk_participant_id');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->timestamps();
        });
    }
}
