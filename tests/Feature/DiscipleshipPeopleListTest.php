<?php

namespace Tests\Feature;

use App\Services\Activity\ActivityRecorder;
use App\Services\DiscipleshipPeople\DiscipleshipPeopleExportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class DiscipleshipPeopleListTest extends TestCase
{
    public function test_legacy_people_list_query_is_rejected(): void
    {
        $response = $this->get('/pemuridan/anggota?page=people_list');

        $response->assertNotFound();
    }

    public function test_people_list_page_renders_for_logged_in_branch_user(): void
    {
        $this->createDiscipleshipTables();
        DB::table('discipleship_people')->insert([
            [
                'id' => 1,
                'branch_id' => 1,
                'full_name' => 'Anggota Kutisari',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'branch_id' => 2,
                'full_name' => 'Anggota GM Rahasia',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('discipleship_group_people')->insert([
            [
                'branch_id' => 1,
                'discipleship_group_id' => 1,
                'person_id' => 1,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'active',
                'started_on' => '2026-01-01',
                'ended_on' => null,
                'end_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 2,
                'discipleship_group_id' => 2,
                'person_id' => 2,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'active',
                'started_on' => '2026-01-01',
                'ended_on' => null,
                'end_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/anggota');

        $response->assertStatus(200);
        $response->assertSee('Daftar Anggota DG');
        $response->assertSee('Anggota Kutisari');
        $response->assertDontSee('Anggota GM Rahasia');
        $response->assertDontSee('Selesai Camp GAP');
        $response->assertDontSee('Selesai RG');
        $response->assertDontSee('<th>Kontak</th>', false);
        $response->assertDontSee('<th>Jumlah Binaan</th>', false);
        $response->assertDontSee('people-contact-cell', false);
        $response->assertDontSee('people-child-count', false);
        $response->assertSee('>Export</span>', false);
        $response->assertDontSee('Export Excel');
        $response->assertSee(route('discipleship.people-list.export'), false);
        $response->assertSee('people-hero-stats discipleship-hero-stats', false);
        $response->assertSee('people-hero-stat discipleship-hero-stat', false);
        $response->assertSee('people-export-button', false);
        $response->assertSee(icon_svg('download'), false);
    }

    public function test_people_list_hides_archived_duplicate_identity(): void
    {
        $this->createDiscipleshipTables();
        DB::table('discipleship_people')->insert([
            [
                'id' => 1,
                'branch_id' => 1,
                'full_name' => 'Identitas DG Sama',
                'phone' => '081234567890',
                'status' => 'inactive',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
            [
                'id' => 2,
                'branch_id' => 1,
                'full_name' => 'Identitas DG Sama',
                'phone' => '081234567890',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('discipleship_group_people')->insert([
            [
                'branch_id' => 1,
                'discipleship_group_id' => 1,
                'person_id' => 1,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'closed',
                'started_on' => '2025-01-01',
                'ended_on' => '2025-12-31',
                'end_reason' => 'person_archived',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => 2,
                'person_id' => 2,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'active',
                'started_on' => '2026-01-01',
                'ended_on' => null,
                'end_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $this->actingAsRecUser();

        $content = $this->get('/pemuridan/anggota')->assertOk()->getContent();

        $this->assertSame(1, substr_count($content, '<div class="people-name-main">Identitas DG Sama</div>'));
    }

    public function test_people_list_shows_dg_only_progress_track_and_ignores_legacy_bridge_filter(): void
    {
        $this->createDiscipleshipTables();
        $personId = DB::table('discipleship_people')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Peserta Progres DG',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipleship_group_people')->insert([
            [
                'branch_id' => 1,
                'discipleship_group_id' => 1,
                'person_id' => $personId,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'closed',
                'started_on' => '2026-01-01',
                'ended_on' => '2026-03-01',
                'end_reason' => 'continued_to_child_group',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => 2,
                'person_id' => $personId,
                'role' => 'member',
                'stage' => 'DG 2',
                'status' => 'active',
                'started_on' => '2026-03-02',
                'ended_on' => null,
                'end_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $this->actingAsRecUser();

        $this->get('/pemuridan/anggota?progress=kgap_complete')
            ->assertOk()
            ->assertSee('Peserta Progres DG')
            ->assertSee('<option value="all" selected>Semua Peserta</option>', false)
            ->assertSee('people-progress-track', false)
            ->assertSee('people-progress-step is-complete', false)
            ->assertSee('people-progress-step is-current', false)
            ->assertSee('Sedang menjalani DG 2')
            ->assertDontSee('Selesai Camp GAP')
            ->assertDontSee('Selesai RG');
    }

    public function test_people_list_counts_active_and_historical_participants_by_their_last_stage(): void
    {
        $this->createDiscipleshipTables();
        DB::table('discipleship_people')->insert([
            [
                'id' => 1,
                'branch_id' => 1,
                'full_name' => 'Peserta Aktif DG 1',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'branch_id' => 1,
                'full_name' => 'Alumni DG 1',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'branch_id' => 1,
                'full_name' => 'Peserta Terakhir DG 2',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'branch_id' => 1,
                'full_name' => 'Identitas Arsip DG',
                'status' => 'inactive',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'branch_id' => 1,
                'full_name' => 'Belum Pernah DG',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('discipleship_group_people')->insert([
            [
                'branch_id' => 1,
                'discipleship_group_id' => 1,
                'person_id' => 1,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'active',
                'started_on' => '2026-01-01',
                'ended_on' => null,
                'end_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => 2,
                'person_id' => 2,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'closed',
                'started_on' => '2025-01-01',
                'ended_on' => '2025-12-31',
                'end_reason' => 'group_completed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => 3,
                'person_id' => 3,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'closed',
                'started_on' => '2025-01-01',
                'ended_on' => '2025-06-30',
                'end_reason' => 'continued_to_child_group',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => 4,
                'person_id' => 3,
                'role' => 'member',
                'stage' => 'DG 2',
                'status' => 'closed',
                'started_on' => '2025-07-01',
                'ended_on' => '2026-01-31',
                'end_reason' => 'group_completed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => 5,
                'person_id' => 4,
                'role' => 'member',
                'stage' => 'DG 3',
                'status' => 'closed',
                'started_on' => '2025-01-01',
                'ended_on' => '2025-12-31',
                'end_reason' => 'person_archived',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/anggota');

        $response->assertOk()
            ->assertSee('Peserta Aktif DG 1')
            ->assertSee('Alumni DG 1')
            ->assertSee('Peserta Terakhir DG 2')
            ->assertDontSee('Identitas Arsip DG')
            ->assertDontSee('Belum Pernah DG')
            ->assertSee('data-people-stat="total">3</strong>', false)
            ->assertSee('data-people-stat="dg1">2</strong>', false)
            ->assertSee('data-people-stat="dg2">1</strong>', false)
            ->assertSee('data-people-stat="dg3">0</strong>', false)
            ->assertSee('Terakhir menyelesaikan DG 1')
            ->assertSee('Terakhir menyelesaikan DG 2');
    }

    public function test_people_list_renders_all_rows_without_pagination_and_uses_live_search(): void
    {
        $this->createDiscipleshipTables();
        $rows = [];
        for ($i = 1; $i <= 1000; $i++) {
            $rows[] = [
                'branch_id' => 1,
                'full_name' => sprintf('Peserta %04d', $i),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('discipleship_people')->insert($chunk);
        }
        $participantRows = DB::table('discipleship_people')
            ->where('branch_id', 1)
            ->orderBy('id')
            ->get(['id'])
            ->map(static fn ($person): array => [
                'branch_id' => 1,
                'discipleship_group_id' => 1,
                'person_id' => (int) $person->id,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'active',
                'started_on' => '2026-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();
        foreach (array_chunk($participantRows, 200) as $chunk) {
            DB::table('discipleship_group_people')->insert($chunk);
        }
        $this->actingAsRecUser();
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $response = $this->get('/pemuridan/anggota?q=Peserta+1000');

        $response->assertOk()
            ->assertSee('Peserta 0001')
            ->assertSee('Peserta 0500')
            ->assertSee('Peserta 1000')
            ->assertSee('value="peserta 1000"', false)
            ->assertSee('data-discipleship-people-search-form', false)
            ->assertSee('data-discipleship-people-search-input', false)
            ->assertSee('data-discipleship-people-search-row', false)
            ->assertSee('data-discipleship-people-search-empty', false)
            ->assertDontSee('rec-pagination', false)
            ->assertDontSee('Halaman 1 dari')
            ->assertDontSee('type="submit">Cari</button>', false);
        $this->assertLessThanOrEqual(10, $queries);

        $this->get('/pemuridan/anggota?per_page=500')
            ->assertOk()
            ->assertSee('Peserta 1000')
            ->assertDontSee('rec-pagination', false);
    }

    public function test_people_list_exports_filtered_rows_to_a_valid_excel_file(): void
    {
        $this->createDiscipleshipTables();
        DB::table('discipleship_people')->insert([
            [
                'id' => 1,
                'branch_id' => 1,
                'full_name' => 'Anggota Export Utama',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'branch_id' => 1,
                'full_name' => 'Anggota Tidak Dipilih',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('discipleship_group_people')->insert([
            [
                'branch_id' => 1,
                'discipleship_group_id' => 1,
                'person_id' => 1,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'active',
                'started_on' => '2026-01-01',
                'ended_on' => null,
                'end_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $this->mock(ActivityRecorder::class)
            ->shouldReceive('record')
            ->once()
            ->andReturnNull();
        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/anggota/ekspor?progress=all&q=Export+Utama');

        $response->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type'),
        );
        $this->assertStringContainsString('anggota-dg-kutisari-semua-peserta-', (string) $response->headers->get('Content-Disposition'));

        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $error = '';
            $sheets = import_read_xlsx_sheets($path, $error);
            $this->assertSame('', $error);
            $this->assertArrayHasKey('Anggota DG', $sheets);
            $this->assertSame('Daftar Anggota DG', $sheets['Anggota DG'][0][0] ?? null);
            $this->assertSame('No.', $sheets['Anggota DG'][2][0] ?? null);
            $this->assertSame('Nama', $sheets['Anggota DG'][2][1] ?? null);
            $this->assertSame('Peran', $sheets['Anggota DG'][2][3] ?? null);
            $this->assertNotContains('Relasi Pembina', $sheets['Anggota DG'][2] ?? []);
            $this->assertSame('Anggota Export Utama', $sheets['Anggota DG'][3][1] ?? null);
            $this->assertSame('Kutisari', $sheets['Anggota DG'][3][2] ?? null);
            $this->assertSame('Sedang', $sheets['Anggota DG'][3][4] ?? null);
            $this->assertStringNotContainsString('Anggota Tidak Dipilih', json_encode($sheets, JSON_UNESCAPED_UNICODE));

            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $sheetXml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $this->assertStringContainsString('<pane ySplit="4"', $sheetXml);
            $this->assertStringContainsString('<autoFilter ref="A4:H5"/>', $sheetXml);
            $this->assertStringNotContainsString('Relasi Pembina', $sheetXml);
            $this->assertLessThan(
                strpos($sheetXml, '<mergeCells'),
                strpos($sheetXml, '<autoFilter'),
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_central_and_developer_can_export_discipleship_people(): void
    {
        $this->mock(DiscipleshipPeopleExportService::class)
            ->shouldReceive('export')
            ->twice()
            ->andReturn(redirect('/pemuridan/anggota?exported=1'));

        $this->actingAsRecUser('central_reader', null, 'pemuridan_pusat');
        $this->get('/pemuridan/anggota/ekspor?branch_id=all')
            ->assertRedirect('/pemuridan/anggota?exported=1');

        $this->actingAsRecUser('developer', null, 'developer');
        $this->get('/pemuridan/anggota/ekspor?branch_id=all')
            ->assertRedirect('/pemuridan/anggota?exported=1');
    }

    private function createDiscipleshipTables(): void
    {
        Schema::dropIfExists('discipleship_group_people');
        Schema::dropIfExists('discipleship_relationships');
        Schema::dropIfExists('discipleship_groups');
        Schema::dropIfExists('discipleship_people');

        Schema::create('discipleship_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('status', 40)->default('active');
            $table->timestamps();
        });

        Schema::create('discipleship_groups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('name')->nullable();
            $table->string('status', 40)->default('active');
            $table->string('start_stage', 40)->nullable();
            $table->string('current_stage', 40)->nullable();
            $table->unsignedBigInteger('parent_group_id')->nullable();
            $table->unsignedBigInteger('source_group_id')->nullable();
            $table->unsignedBigInteger('initiated_by_person_id')->nullable();
            $table->date('multiplied_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_relationships', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('mentor_person_id')->nullable();
            $table->unsignedBigInteger('disciple_person_id')->nullable();
            $table->unsignedBigInteger('context_group_id')->nullable();
            $table->string('relation_type')->nullable();
            $table->string('stage_at_start')->nullable();
            $table->string('status', 40)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('reason_end')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_group_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('role')->nullable();
            $table->string('stage')->nullable();
            $table->string('status', 40)->default('active');
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->string('end_reason')->nullable();
            $table->timestamps();
        });
    }
}
