<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpiritualJourneyPageTest extends TestCase
{
    public function test_legacy_spiritual_journey_query_redirects_to_clean_route(): void
    {
        $response = $this->get('/pemuridan/spiritual-journey?page=spiritual_journey');

        $response->assertRedirect('/pemuridan/spiritual-journey');
    }

    public function test_spiritual_journey_page_renders_for_logged_in_branch_user(): void
    {
        $this->createMskTables();
        $this->seedParticipant();

        $previousSession = $this->signInAsBranchUser();

        $response = $this->get('/pemuridan/spiritual-journey');

        $response->assertStatus(200);
        $response->assertSee('Spiritual Journey');
        $response->assertSee('Peserta Journey');

        $this->restoreSession($previousSession);
    }

    public function test_bridge_status_update_persists_to_laravel_table(): void
    {
        $this->createMskTables();
        $this->seedParticipant();

        $previousSession = $this->signInAsBranchUser();

        $response = $this->post('/pemuridan/spiritual-journey/msk-journey-1/bridge-status', [
            'action' => 'save_journey_bridge_status',
            'id' => 'msk-journey-1',
            'journey_bridge_status' => 'sudah_kgap',
        ]);

        $response->assertRedirect('/pemuridan/spiritual-journey?saved=1');
        $this->assertDatabaseHas('msk_participants', [
            'branch_code' => 'kutisari',
            'public_id' => 'msk-journey-1',
            'journey_bridge_status' => 'sudah_kgap',
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
            session_id('spiritual-journey-test-' . str_replace('.', '', uniqid('', true)));
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

    private function seedParticipant(): void
    {
        $participantId = DB::table('msk_participants')->insertGetId([
            'public_id' => 'msk-journey-1',
            'branch_code' => 'kutisari',
            'member_public_id' => null,
            'full_name' => 'Peserta Journey',
            'batch_month' => '2026-06',
            'completed_at' => null,
            'journey_bridge_status' => 'belum',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('msk_participant_sessions')->insert([
            [
                'msk_participant_id' => $participantId,
                'session_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'msk_participant_id' => $participantId,
                'session_number' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
