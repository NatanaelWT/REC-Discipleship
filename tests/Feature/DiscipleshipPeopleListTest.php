<?php

namespace Tests\Feature;

use App\Services\Activity\ActivityRecorder;
use App\Services\Branches\BranchCatalog;
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
        DB::table('orang')->insert([
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
        DB::table('keanggotaan_kelompok_dg')->insert([
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
        $response->assertSee('discipleship-page-header__stats', false);
        $response->assertSee('discipleship-page-header__stat', false);
        $response->assertSee('people-export-button', false);
        $response->assertSee(icon_svg('download'), false);
    }

    public function test_people_list_hides_archived_duplicate_identity(): void
    {
        $this->createDiscipleshipTables();
        DB::table('orang')->insert([
            [
                'id' => 1,
                'branch_id' => 1,
                'full_name' => 'Identitas DG Sama',
                'whatsapp' => '081234567890',
                'status' => 'inactive',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
            [
                'id' => 2,
                'branch_id' => 1,
                'full_name' => 'Identitas DG Sama',
                'whatsapp' => '081234567890',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
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
        $personId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Peserta Progres DG',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
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
        DB::table('orang')->insert([
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
        DB::table('keanggotaan_kelompok_dg')->insert([
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

    public function test_people_list_lazy_loads_rows_and_ajax_searches_server_side(): void
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
            DB::table('orang')->insert($chunk);
        }
        $participantRows = DB::table('orang')
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
            DB::table('keanggotaan_kelompok_dg')->insert($chunk);
        }
        $otherBranchPersonId = DB::table('orang')->insertGetId([
            'branch_id' => 2,
            'full_name' => 'Peserta 1000 Rahasia',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
            'branch_id' => 2,
            'discipleship_group_id' => 2,
            'person_id' => $otherBranchPersonId,
            'role' => 'member',
            'stage' => 'DG 1',
            'status' => 'active',
            'started_on' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAsRecUser();
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $response = $this->get('/pemuridan/anggota');

        $response->assertOk()
            ->assertSee('Peserta 0001')
            ->assertSee('Peserta 0050')
            ->assertDontSee('Peserta 0051')
            ->assertDontSee('Peserta 1000')
            ->assertDontSee('Peserta 1000 Rahasia')
            ->assertSee('data-discipleship-people-list', false)
            ->assertSee('data-rows-url="'.route('discipleship.people-list.rows').'"', false)
            ->assertSee('data-per-page="50"', false)
            ->assertSee('data-next-page="2"', false)
            ->assertSee('data-people-stat="total">1000</strong>', false)
            ->assertSee('data-discipleship-people-search-form', false)
            ->assertSee('data-discipleship-people-search-input', false)
            ->assertSee('data-discipleship-people-search-row', false)
            ->assertSee('data-discipleship-people-search-empty', false)
            ->assertDontSee('rec-pagination', false)
            ->assertDontSee('Halaman 1 dari')
            ->assertDontSee('type="submit">Cari</button>', false);
        $this->assertLessThanOrEqual(30, $queries);

        $pageTwo = $this->getJson('/pemuridan/anggota/rows?page=2');
        $pageTwo->assertOk()
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('next_page', 3)
            ->assertJsonPath('stats.total', 1000);
        $this->assertStringContainsString('Peserta 0051', (string) $pageTwo->json('html'));
        $this->assertStringContainsString('Peserta 0100', (string) $pageTwo->json('html'));
        $this->assertStringNotContainsString('Peserta 0050', (string) $pageTwo->json('html'));
        $this->assertStringNotContainsString('Peserta 0101', (string) $pageTwo->json('html'));

        $search = $this->getJson('/pemuridan/anggota/rows?q=Peserta+1000');
        $search->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('next_page', null)
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.dg1', 1);
        $this->assertStringContainsString('Peserta 1000', (string) $search->json('html'));
        $this->assertStringNotContainsString('Peserta 0001', (string) $search->json('html'));
        $this->assertStringNotContainsString('Peserta 1000 Rahasia', (string) $search->json('html'));

        $largePage = $this->getJson('/pemuridan/anggota/rows?per_page=500');
        $largePage->assertOk()->assertJsonPath('next_page', 2);
        $this->assertStringContainsString('Peserta 0100', (string) $largePage->json('html'));
        $this->assertStringNotContainsString('Peserta 0101', (string) $largePage->json('html'));
    }

    public function test_people_list_exports_filtered_rows_to_a_valid_excel_file(): void
    {
        $this->createDiscipleshipTables();
        DB::table('orang')->insert([
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
        DB::table('keanggotaan_kelompok_dg')->insert([
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

    public function test_central_and_developer_can_export_people(): void
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
        Schema::dropIfExists('keanggotaan_kelompok_dg');
        Schema::dropIfExists('relasi_dg');
        Schema::dropIfExists('kelompok_dg');
        Schema::dropIfExists('orang');
        $this->createBranchesTable();

        Schema::create('orang', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('status', 40)->default('active');
            $table->timestamps();
        });

        Schema::create('kelompok_dg', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('status', 40)->default('active');
            $table->string('stage')->nullable();
            $table->unsignedBigInteger('parent_group_id')->nullable();
            $table->unsignedBigInteger('source_group_id')->nullable();
            $table->unsignedBigInteger('initiated_by_person_id')->nullable();
            $table->date('multiplied_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('keanggotaan_kelompok_dg', function (Blueprint $table): void {
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

    private function createBranchesTable(): void
    {
        Schema::dropIfExists('cabang');

        Schema::create('cabang', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('camp_gap_participant_target')->default(50);
            $table->unsignedInteger('msk_completion_target')->default(50);
            $table->unsignedInteger('dg1_completion_target')->default(50);
            $table->unsignedInteger('dg2_completion_target')->default(50);
            $table->unsignedInteger('dg3_completion_target')->default(50);
            $table->timestamps();
        });

        DB::table('cabang')->insert([
            ['id' => 1, 'label' => 'Kutisari', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'label' => 'GM', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'label' => 'Darmo', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'label' => 'Merr', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'label' => 'Batam', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'label' => 'Nginden', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        app(BranchCatalog::class)->clearCache();
    }
}



