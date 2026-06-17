<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MskParticipantPageTest extends TestCase
{
    public function test_legacy_msk_query_redirects_to_clean_route(): void
    {
        $response = $this->get('/pemuridan/msk?page=msk_classes');

        $response->assertRedirect('/pemuridan/msk');
    }

    public function test_msk_page_renders_for_logged_in_branch_user(): void
    {
        $this->createMskTables();

        $previousSession = $this->signInAsBranchUser();

        $response = $this->get('/pemuridan/msk');

        $response->assertStatus(200);
        $response->assertSee('Kelas MSK');

        $this->restoreSession($previousSession);
    }

    public function test_store_msk_participant_persists_to_laravel_tables(): void
    {
        $this->createMskTables();

        $previousSession = $this->signInAsBranchUser();

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
            'branch_code' => 'kutisari',
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

        $this->restoreSession($previousSession);
    }

    /**
     * @return array<string, mixed>
     */
    private function signInAsBranchUser(): array
    {
        $previousSession = $_SESSION ?? [];
        if (session_status() === PHP_SESSION_NONE) {
            session_save_path(storage_path('framework/sessions'));
            session_id('msk-page-test-' . str_replace('.', '', uniqid('', true)));
            session_start();
        }
        $_SESSION['user'] = 'tester';
        $_SESSION['cabang'] = 'kutisari';
        $_SESSION['access_scope'] = 'branch';

        return $previousSession;
    }

    /**
     * @param array<string, mixed> $previousSession
     */
    private function restoreSession(array $previousSession): void
    {
        $_SESSION = $previousSession;
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    private function createMskTables(): void
    {
        Schema::dropIfExists('msk_participant_photos');
        Schema::dropIfExists('msk_participant_sessions');
        Schema::dropIfExists('msk_participants');

        Schema::create('msk_participants', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id');
            $table->string('branch_code', 40);
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
