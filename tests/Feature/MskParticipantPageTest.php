<?php

namespace Tests\Feature;

use App\Services\MskParticipants\MskParticipantExportService;
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

    public function test_central_and_developer_see_export_without_import_controls(): void
    {
        $this->createMskTables();

        $this->actingAsRecUser('central_reader', null, 'pemuridan_pusat');
        $this->get('/pemuridan/msk?branch_id=all')
            ->assertOk()
            ->assertSee('Export MSK')
            ->assertDontSee('Import MSK')
            ->assertDontSee('data-msk-create-open', false);

        $this->actingAsRecUser('developer', null, 'developer');
        $this->get('/pemuridan/msk?branch_id=all')
            ->assertOk()
            ->assertSee('Export MSK')
            ->assertDontSee('Import MSK')
            ->assertDontSee('data-msk-create-open', false);
    }

    public function test_central_can_export_but_cannot_import_msk_data(): void
    {
        $this->createMskTables();
        $this->mock(MskParticipantExportService::class)
            ->shouldReceive('export')
            ->once()
            ->andReturn(redirect('/pemuridan/msk?exported=1'));
        $this->actingAsRecUser('central_reader', null, 'pemuridan_pusat');

        $this->post('/pemuridan/msk/ekspor', [
            'action' => 'export_pemuridan_excel',
            'batch_month' => 'all',
        ])->assertRedirect('/pemuridan/msk?exported=1');

        $this->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
        ])->assertRedirect('/pemuridan/dashboard?error=access_denied');
    }

    public function test_developer_can_export_but_cannot_import_msk_data(): void
    {
        $this->createMskTables();
        $this->mock(MskParticipantExportService::class)
            ->shouldReceive('export')
            ->once()
            ->andReturn(redirect('/pemuridan/msk?exported=1'));
        $this->actingAsRecUser('developer', null, 'developer');

        $this->post('/pemuridan/msk/ekspor', [
            'action' => 'export_pemuridan_excel',
            'batch_month' => 'all',
        ])->assertRedirect('/pemuridan/msk?exported=1');

        $this->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
        ])->assertRedirect('/developer?error=access_denied');
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

    public function test_batch_filter_lists_all_batches_not_only_the_active_batch(): void
    {
        $this->createMskTables();
        DB::table('msk_participants')->insert([
            $this->participantRow('Peserta Batch Terbaru', '2026-06'),
            $this->participantRow('Peserta Batch Menengah', '2025-01'),
            $this->participantRow('Peserta Batch Lama', '2024-11'),
        ]);
        $this->actingAsRecUser();

        $this->get('/pemuridan/msk')
            ->assertOk()
            ->assertSee('Semua Batch (3)')
            ->assertSee('<option value="2026-06" selected>Juni 2026 (1)</option>', false)
            ->assertSee('<option value="2025-01" >Januari 2025 (1)</option>', false)
            ->assertSee('<option value="2024-11" >November 2024 (1)</option>', false);

        $this->get('/pemuridan/msk?batch_month=2024-11')
            ->assertOk()
            ->assertSee('Peserta Batch Lama')
            ->assertDontSee('Peserta Batch Terbaru')
            ->assertSee('<option value="2026-06" >Juni 2026 (1)</option>', false)
            ->assertSee('<option value="2024-11" selected>November 2024 (1)</option>', false);
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

    public function test_update_msk_participant_persists_selected_batch_month_and_other_fields(): void
    {
        $this->createMskTables();
        $participantId = DB::table('msk_participants')->insertGetId($this->participantRow('Peserta Lama', '2026-06'));
        $this->actingAsRecUser();

        $this->get('/pemuridan/msk?batch_month=all')
            ->assertOk()
            ->assertSee('name="batch_month" value="2026-06" required', false)
            ->assertSee('name="return_batch_month" value="all"', false)
            ->assertDontSee('name="msk_month"', false);

        $response = $this->post('/pemuridan/msk/peserta', [
            'action' => 'save_msk_participant',
            'id' => $participantId,
            'return_batch_month' => 'all',
            'full_name' => 'Peserta Lama Diedit',
            'gender' => 'Perempuan',
            'birth_date' => '1998-04-25',
            'birth_place' => 'Malang',
            'address' => 'Jl. Baru',
            'email' => 'PESERTA.EDIT@example.test',
            'whatsapp' => '089999111222',
            'batch_month' => '2024-11',
            'session_numbers' => ['3', '1', '12', '3'],
            'notes' => 'Catatan edit',
        ]);

        $response->assertRedirect('/pemuridan/msk?batch_month=2024-11&saved=1');
        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('data-msk-edit-auto-open=""', false);
        $this->assertDatabaseHas('msk_participants', [
            'id' => $participantId,
            'full_name' => 'Peserta Lama Diedit',
            'gender' => 'Perempuan',
            'birth_date' => '1998-04-25',
            'birth_day_month' => '25-04',
            'birth_place' => 'Malang',
            'address' => 'Jl. Baru',
            'email' => 'peserta.edit@example.test',
            'whatsapp' => '089999111222',
            'batch_month' => '2024-11',
            'notes' => 'Catatan edit',
        ]);

        $sessions = json_decode((string) DB::table('msk_participants')->where('id', $participantId)->value('session_numbers'), true);
        $this->assertSame([1, 3, 12], $sessions);
    }

    public function test_update_msk_participant_rejects_invalid_batch_or_birth_date_without_current_month_fallback(): void
    {
        $this->createMskTables();
        $participantId = DB::table('msk_participants')->insertGetId($this->participantRow('Peserta Aman', '2025-01'));
        $this->actingAsRecUser();

        $this->post('/pemuridan/msk/peserta', [
            'action' => 'save_msk_participant',
            'id' => $participantId,
            'return_batch_month' => 'all',
            'full_name' => 'Peserta Aman',
            'batch_month' => 'all',
            'session_numbers' => ['1'],
        ])->assertRedirect('/pemuridan/msk?edit='.$participantId.'&batch_month=all&error=invalid_msk_batch_month');

        $this->assertDatabaseHas('msk_participants', [
            'id' => $participantId,
            'batch_month' => '2025-01',
        ]);

        $this->post('/pemuridan/msk/peserta', [
            'action' => 'save_msk_participant',
            'id' => $participantId,
            'return_batch_month' => 'all',
            'full_name' => 'Peserta Aman',
            'birth_date' => '2026-02-31',
            'batch_month' => '2024-11',
            'session_numbers' => ['1'],
        ])->assertRedirect('/pemuridan/msk?edit='.$participantId.'&batch_month=all&error=invalid_msk_birth_date');

        $this->assertDatabaseHas('msk_participants', [
            'id' => $participantId,
            'batch_month' => '2025-01',
            'birth_date' => null,
        ]);
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

    /** @return array<string, mixed> */
    private function participantRow(string $name, string $batchMonth): array
    {
        return [
            'branch_id' => 1,
            'full_name' => $name,
            'batch_month' => $batchMonth,
            'journey_bridge_status' => 'belum',
            'status' => 'active',
            'session_numbers' => json_encode([]),
            'photos' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
