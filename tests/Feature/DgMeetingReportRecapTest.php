<?php

namespace Tests\Feature;

use App\Models\ActivityRequest;
use App\Services\Branches\BranchCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DgMeetingReportRecapTest extends TestCase
{
    public function test_legacy_dg_report_recap_query_is_rejected(): void
    {
        $response = $this->get('/pemuridan/laporan-dg?page=dg_reports_recap');

        $response->assertNotFound();
    }

    public function test_dg_report_recap_renders_from_laravel_tables(): void
    {
        $this->createTables();
        $this->seedReport();

        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/laporan-dg');

        $response->assertStatus(200);
        $response->assertSee('Jurnal Temu DG');
        $response->assertSee('Pemimpin Test');
        $response->assertSee('Materi Test');
        $response->assertSee('card discipleship-page-header', false);
        $response->assertSee('discipleship-page-header__stats', false);
        $response->assertSee('discipleship-page-header__stat', false);
        $response->assertSee('discipleship-page-header__filter', false);
        $response->assertSee('data-filter-role="recap-progress"', false);
        $response->assertSee('discipleship-page-header__search', false);
        $response->assertSee('data-recap-progress="dg1"', false);

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'discipleship-page-header__search'),
            strpos($content, 'discipleship-page-header__filter'),
        );
    }

    public function test_dg_report_recap_only_displays_active_groups_and_their_reports(): void
    {
        $this->createTables();
        $ids = $this->seedReport();
        $inactiveGroupId = DB::table('kelompok_dg')->insertGetId([
            'branch_id' => 1,
            'status' => 'completed',
            'stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
            'branch_id' => 1,
            'discipleship_group_id' => $inactiveGroupId,
            'person_id' => $ids['leader_id'],
            'role' => 'leader',
            'status' => 'closed',
            'started_on' => '2025-01-01',
            'ended_on' => '2025-12-31',
            'end_reason' => 'group_completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('jurnal_temu_dg')->insert([
            'branch_id' => 1,
            'leader_person_id' => $ids['leader_id'],
            'leader_name_snapshot' => 'Pemimpin Test',
            'discipleship_group_id' => $inactiveGroupId,
            'group_name_snapshot' => 'Kelompok Nonaktif',
            'meeting_date' => '2025-12-01',
            'material_topic' => 'Materi Kelompok Nonaktif',
            'group_progress_snapshot' => 'DG 1',
            'absences' => json_encode([]),
            'meditation_sharers' => json_encode([]),
            'photos' => json_encode([]),
            'source' => 'public_form',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAsRecUser();

        $this->get('/pemuridan/laporan-dg')
            ->assertOk()
            ->assertSee('Materi Test')
            ->assertDontSee('Kelompok Nonaktif')
            ->assertDontSee('Materi Kelompok Nonaktif');
    }

    public function test_calendar_cells_keep_dates_and_report_counts_visible(): void
    {
        $css = file_get_contents(public_path('assets/style.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.dg-recap-calendar-grid-body\s*\{[^}]*grid-template-rows:\s*repeat\(6,\s*minmax\(62px,\s*auto\)\);/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.dg-recap-calendar-day-count\s*\{[^}]*position:\s*absolute;[^}]*top:\s*7px;[^}]*right:\s*7px;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.dg-recap-calendar-panels\s*\{[^}]*overflow-y:\s*auto;/s',
            $css,
        );
    }

    public function test_central_discipleship_user_can_view_and_filter_all_branches(): void
    {
        $this->createTables();
        $this->seedReport();
        $this->actingAsRecUser('recpusat', null, 'pemuridan_pusat');

        $this->get('/pemuridan/laporan-dg?rekap_cabang=all')
            ->assertOk()
            ->assertSee('Materi Test')
            ->assertSee('Mode Pusat')
            ->assertSee('Semua Cabang');

        $this->get('/pemuridan/laporan-dg?rekap_cabang=gm')
            ->assertOk()
            ->assertDontSee('Materi Test');
    }

    public function test_developer_views_discipleship_recap_as_readonly_all_branch_preview(): void
    {
        $this->createTables();
        $this->seedReport();
        $this->actingAsRecUser('developer', null, 'developer');

        $this->assertSame('', current_user_branch());
        $this->assertTrue(is_effective_central_discipleship_readonly());

        $this->get('/pemuridan/laporan-dg?rekap_cabang=all')
            ->assertOk()
            ->assertSee('Materi Test');
    }

    public function test_public_dg_form_normalizes_numeric_ids_for_javascript_selects(): void
    {
        $this->createTables();
        $this->seedReport();

        $this->get('/publik/jurnal-dg/kutisari/laporan')
            ->assertOk()
            ->assertSee('Pemimpin Test')
            ->assertSee("groupRow.id = String(groupRow.id || '');", false)
            ->assertSee("groupRow.leader_id = String(groupRow.leader_id || '');", false)
            ->assertSee("memberRow.id = String(memberRow.id || '');", false);
    }

    public function test_public_dg_form_rejects_developer_testing_branch_url(): void
    {
        Schema::dropIfExists('cabang');
        Schema::create('cabang', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        DB::table('cabang')->insert([
            ['label' => 'Kutisari', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Testing', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
        app(BranchCatalog::class)->clearCache();

        $this->get('/publik/jurnal-dg/testing/laporan')
            ->assertRedirect(route('public.dg.branch', ['error' => 'invalid_branch']));
    }

    public function test_public_dg_report_accepts_matching_numeric_ids_sent_as_strings(): void
    {
        $this->createTables();
        $ids = $this->seedGmReportFormData();

        $response = $this->post('/publik/jurnal-dg/gm/laporan', [
            'public_cabang' => 'gm',
            'leader_id' => (string) $ids['leader_id'],
            'group_id' => (string) $ids['group_id'],
            'meeting_date' => '2026-06-18',
            'material_topic' => 'Sesi 9',
            'sharing_openness' => '10',
            'quality_prepare' => '1',
            'quality_pray' => '1',
            'quality_share_meditation' => '1',
            'quality_relational' => '1',
            'meditation_sharer_ids' => [(string) $ids['member_id']],
        ]);

        $response->assertRedirect(route('public.dg.report', ['branch' => 'gm', 'submitted' => 1]));
        $response->assertSessionMissing('public_dg_report_error');
        $response->assertSessionMissing('public_dg_report_old');

        $report = DB::table('jurnal_temu_dg')->orderByDesc('id')->first();
        $this->assertNotNull($report);
        $this->assertSame(2, (int) $report->branch_id);
        $this->assertSame($ids['leader_id'], (int) $report->leader_person_id);
        $this->assertSame('Veronica Lahindah', $report->leader_name_snapshot);
        $this->assertSame($ids['group_id'], (int) $report->discipleship_group_id);
        $this->assertSame('DG 2 (Veronica Lahindah)', $report->group_name_snapshot);
        $this->assertSame('Sesi 9', $report->material_topic);
        $this->assertSame('2026-06-18', substr((string) $report->meeting_date, 0, 10));
        $this->assertSame([[
            'person_id' => 510,
            'person_name_snapshot' => 'Carlini Fan Hardi',
        ]], json_decode((string) $report->meditation_sharers, true));
    }

    public function test_public_dg_report_accepts_cross_branch_leader_for_branch_group(): void
    {
        $this->createTables();
        DB::table('orang')->insert([
            ['id' => 626, 'branch_id' => 2, 'full_name' => 'Yakub Tri Handoko', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 641, 'branch_id' => 1, 'full_name' => 'Anggota Kutisari', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('kelompok_dg')->insert([
            'id' => 175,
            'branch_id' => 1,
            'status' => 'active',
            'stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
            ['branch_id' => 1, 'discipleship_group_id' => 175, 'person_id' => 626, 'role' => 'leader', 'stage' => null, 'status' => 'active', 'started_on' => '2026-07-01', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 1, 'discipleship_group_id' => 175, 'person_id' => 641, 'role' => 'member', 'stage' => 'DG 1', 'status' => 'active', 'started_on' => '2026-07-01', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->post('/publik/jurnal-dg/kutisari/laporan', [
            'public_cabang' => 'kutisari',
            'leader_id' => '626',
            'group_id' => '175',
            'meeting_date' => '2026-07-01',
            'material_topic' => 'Sesi 1',
            'sharing_openness' => '9',
            'quality_prepare' => '1',
            'quality_pray' => '1',
            'quality_share_meditation' => '1',
            'quality_relational' => '1',
            'meditation_sharer_ids' => ['641'],
        ]);

        $response->assertRedirect(route('public.dg.report', ['branch' => 'kutisari', 'submitted' => 1]));
        $response->assertSessionMissing('public_dg_report_error');

        $report = DB::table('jurnal_temu_dg')->orderByDesc('id')->first();
        $this->assertNotNull($report);
        $this->assertSame(1, (int) $report->branch_id);
        $this->assertSame(626, (int) $report->leader_person_id);
        $this->assertSame('Yakub Tri Handoko (GM)', $report->leader_name_snapshot);
        $this->assertSame(175, (int) $report->discipleship_group_id);
    }

    public function test_public_dg_report_uploads_a_real_meeting_photo_without_server_error(): void
    {
        $this->createTables();
        $ids = $this->seedGmReportFormData();

        $response = $this->post('/publik/jurnal-dg/gm/laporan', [
            'public_cabang' => 'gm',
            'leader_id' => (string) $ids['leader_id'],
            'group_id' => (string) $ids['group_id'],
            'meeting_date' => '2026-06-18',
            'material_topic' => 'Sesi 9',
            'sharing_openness' => '10',
            'meeting_photos' => [
                UploadedFile::fake()->createWithContent('foto-pertemuan.png', $this->tinyPng()),
            ],
        ]);

        $response->assertRedirect(route('public.dg.report', ['branch' => 'gm', 'submitted' => 1]));
        $response->assertSessionMissing('public_dg_report_error');

        $report = DB::table('jurnal_temu_dg')->orderByDesc('id')->first();
        $this->assertNotNull($report);
        $photos = json_decode((string) $report->photos, true);
        $this->assertIsArray($photos);
        $this->assertCount(1, $photos);
        $this->assertSame('foto-pertemuan.png', $photos[0]['name']);
        $this->assertStringStartsWith('uploads/dg_reports/dg_', $photos[0]['path']);
        $this->assertFileExists(rec_runtime_path($photos[0]['path']));

        delete_relative_upload_file($photos[0]['path']);
    }

    public function test_public_dg_report_rejects_non_image_upload_without_creating_report(): void
    {
        $this->createTables();
        $ids = $this->seedGmReportFormData();
        $this->createActivityTables();

        $response = $this->post('/publik/jurnal-dg/gm/laporan', [
            'public_cabang' => 'gm',
            'leader_id' => (string) $ids['leader_id'],
            'group_id' => (string) $ids['group_id'],
            'meeting_date' => '2026-06-18',
            'material_topic' => 'Sesi 9',
            'sharing_openness' => '10',
            'meeting_photos' => [
                UploadedFile::fake()->createWithContent('bukan-foto.png', 'plain text'),
            ],
        ]);

        $response->assertRedirect(route('public.dg.report', ['branch' => 'gm']));
        $response->assertSessionHas(
            'public_dg_report_error',
            'Format foto pertemuan tidak didukung. Gunakan JPG/PNG/WEBP.',
        );
        $response->assertSessionHasErrors('public_dg_report_error');
        $response->assertSessionMissing('public_dg_report_old.meeting_photos');
        $this->assertSame(0, DB::table('jurnal_temu_dg')->count());

        $activity = ActivityRequest::query()->findOrFail($response->headers->get('X-Activity-Request-Id'));
        $this->assertSame('failed', $activity->outcome);
        $this->assertTrue($activity->events()->where('action', 'request.validation_failed')->exists());
    }

    public function test_public_dg_report_rejects_oversized_photo_without_server_error(): void
    {
        $this->createTables();
        $ids = $this->seedGmReportFormData();

        $response = $this->post('/publik/jurnal-dg/gm/laporan', [
            'public_cabang' => 'gm',
            'leader_id' => (string) $ids['leader_id'],
            'group_id' => (string) $ids['group_id'],
            'meeting_date' => '2026-06-18',
            'material_topic' => 'Sesi 9',
            'sharing_openness' => '10',
            'meeting_photos' => [
                UploadedFile::fake()->create('terlalu-besar.jpg', 5121, 'image/jpeg'),
            ],
        ]);

        $response->assertRedirect(route('public.dg.report', ['branch' => 'gm']));
        $response->assertSessionHas(
            'public_dg_report_error',
            'Ukuran foto pertemuan terlalu besar. Maksimal 5 MB per file.',
        );
        $this->assertSame(0, DB::table('jurnal_temu_dg')->count());
    }

    public function test_public_dg_report_cleans_up_previous_files_when_one_of_multiple_uploads_is_invalid(): void
    {
        $this->createTables();
        $ids = $this->seedGmReportFormData();
        $uploadDirectory = rec_runtime_path('uploads/dg_reports');
        $filesBefore = glob($uploadDirectory.'/dg_*') ?: [];
        sort($filesBefore);

        $response = $this->post('/publik/jurnal-dg/gm/laporan', [
            'public_cabang' => 'gm',
            'leader_id' => (string) $ids['leader_id'],
            'group_id' => (string) $ids['group_id'],
            'meeting_date' => '2026-06-18',
            'material_topic' => 'Sesi 9',
            'sharing_openness' => '10',
            'meeting_photos' => [
                UploadedFile::fake()->createWithContent('valid.png', $this->tinyPng()),
                UploadedFile::fake()->createWithContent('invalid.png', 'plain text'),
            ],
        ]);

        $response->assertRedirect(route('public.dg.report', ['branch' => 'gm']));
        $response->assertSessionHas(
            'public_dg_report_error',
            'Format foto pertemuan tidak didukung. Gunakan JPG/PNG/WEBP.',
        );
        $this->assertSame(0, DB::table('jurnal_temu_dg')->count());

        $filesAfter = glob($uploadDirectory.'/dg_*') ?: [];
        sort($filesAfter);
        $this->assertSame($filesBefore, $filesAfter);
    }

    public function test_public_dg_report_rejects_real_leader_group_mismatch_and_audits_failure(): void
    {
        $this->createTables();
        $ids = $this->seedReport();
        $otherLeaderId = $this->seedOtherLeaderGroup();
        $this->createActivityTables();
        $initialReportCount = DB::table('jurnal_temu_dg')->count();

        $response = $this->post('/publik/jurnal-dg/kutisari/laporan', [
            'public_cabang' => 'kutisari',
            'leader_id' => (string) $otherLeaderId,
            'group_id' => (string) $ids['group_id'],
            'meeting_date' => '2026-06-18',
            'material_topic' => 'Sesi 9',
            'sharing_openness' => '10',
        ]);

        $response->assertRedirect(route('public.dg.report', ['branch' => 'kutisari']));
        $response->assertSessionHasErrors('public_dg_report_error');
        $response->assertSessionHas('public_dg_report_error', 'Kelompok yang dipilih tidak sesuai dengan pemimpin DG.');
        $response->assertSessionHas('public_dg_report_old.leader_id', (string) $otherLeaderId);
        $response->assertSessionHas('public_dg_report_old.group_id', (string) $ids['group_id']);
        $this->assertSame($initialReportCount, DB::table('jurnal_temu_dg')->count());

        $activity = ActivityRequest::query()->findOrFail($response->headers->get('X-Activity-Request-Id'));
        $this->assertSame('failed', $activity->outcome);
        $this->assertTrue($activity->events()->where('action', 'request.validation_failed')->exists());
    }

    private function createTables(): void
    {
        Schema::dropIfExists('jurnal_temu_dg');
        Schema::dropIfExists('keanggotaan_kelompok_dg');
        Schema::dropIfExists('kelompok_dg');
        Schema::dropIfExists('orang');

        Schema::create('orang', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('gender')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('kelompok_dg', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('status')->default('active');
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
            $table->unsignedBigInteger('discipleship_group_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('role')->default('leader');
            $table->string('stage')->nullable();
            $table->string('status')->default('active');
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->string('end_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('jurnal_temu_dg', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->string('leader_name_snapshot')->nullable();
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->string('group_name_snapshot')->nullable();
            $table->date('meeting_date')->nullable();
            $table->string('material_topic')->nullable();
            $table->string('group_progress_snapshot')->nullable();
            $table->text('absence_reason')->nullable();
            $table->json('absences')->nullable();
            $table->json('meditation_sharers')->nullable();
            $table->json('photos')->nullable();
            $table->longText('additional_notes')->nullable();
            $table->unsignedTinyInteger('meditation_min_times')->default(0);
            $table->unsignedTinyInteger('sharing_openness_score')->nullable();
            $table->boolean('prepared_material')->default(false);
            $table->boolean('prayed_for_members')->default(false);
            $table->boolean('shared_meditation')->default(false);
            $table->boolean('relationally_contacted')->default(false);
            $table->string('source')->default('public_form');
            $table->timestamps();
        });

    }

    private function createActivityTables(): void
    {
        Schema::dropIfExists('aktivitas');
        Schema::dropIfExists('peristiwa_aktivitas');
        Schema::dropIfExists('permintaan_aktivitas');
        Schema::dropIfExists('activity_events');
        Schema::dropIfExists('activity_requests');
        $activityMigration = require database_path('migrations/2026_06_21_000001_create_activity_audit_tables.php');
        $activityMigration->up();
        $renameMigration = require database_path('migrations/2026_07_04_000003_rename_domain_tables_to_indonesian.php');
        $renameMigration->up();
        $mergeMigration = require database_path('migrations/2026_07_07_000001_merge_activity_audit_tables.php');
        $mergeMigration->up();
    }

    /** @return array{leader_id:int,member_id:int,group_id:int,report_id:int} */
    private function seedReport(): array
    {
        $leaderId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Pemimpin Test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Anggota Test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupId = DB::table('kelompok_dg')->insertGetId([
            'branch_id' => 1,
            'status' => 'active',
            'stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('keanggotaan_kelompok_dg')->insert([
            'branch_id' => 1,
            'discipleship_group_id' => $groupId,
            'person_id' => $leaderId,
            'role' => 'leader',
            'status' => 'active',
            'started_on' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('keanggotaan_kelompok_dg')->insert([
            'branch_id' => 1,
            'discipleship_group_id' => $groupId,
            'person_id' => $memberId,
            'role' => 'member',
            'stage' => 'DG 1',
            'status' => 'active',
            'started_on' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reportId = DB::table('jurnal_temu_dg')->insertGetId([
            'branch_id' => 1,
            'leader_person_id' => $leaderId,
            'leader_name_snapshot' => 'Pemimpin Test',
            'discipleship_group_id' => $groupId,
            'group_name_snapshot' => 'Kelompok Test',
            'meeting_date' => '2026-06-01',
            'material_topic' => 'Materi Test',
            'group_progress_snapshot' => 'DG 1',
            'absence_reason' => 'Sakit',
            'absences' => json_encode([[
                'person_id' => $memberId,
                'person_name_snapshot' => 'Anggota Test',
            ]]),
            'meditation_sharers' => json_encode([]),
            'photos' => json_encode([]),
            'additional_notes' => 'Catatan laporan',
            'meditation_min_times' => 2,
            'sharing_openness_score' => 8,
            'prepared_material' => true,
            'prayed_for_members' => true,
            'shared_meditation' => false,
            'relationally_contacted' => true,
            'source' => 'public_form',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'leader_id' => $leaderId,
            'member_id' => $memberId,
            'group_id' => $groupId,
            'report_id' => $reportId,
        ];
    }

    private function seedOtherLeaderGroup(): int
    {
        $leaderId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Pemimpin Lain',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $groupId = DB::table('kelompok_dg')->insertGetId([
            'branch_id' => 1,
            'status' => 'active',
            'stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
            'branch_id' => 1,
            'discipleship_group_id' => $groupId,
            'person_id' => $leaderId,
            'role' => 'leader',
            'status' => 'active',
            'started_on' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $leaderId;
    }

    /** @return array{leader_id:int,member_id:int,group_id:int} */
    private function seedGmReportFormData(): array
    {
        DB::table('orang')->insert([
            ['id' => 605, 'branch_id' => 2, 'full_name' => 'Veronica Lahindah', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 510, 'branch_id' => 2, 'full_name' => 'Carlini Fan Hardi', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 525, 'branch_id' => 2, 'full_name' => 'Pris Cilla Sandy', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('kelompok_dg')->insert([
            'id' => 322,
            'branch_id' => 2,
            'status' => 'active',
            'stage' => 'DG 2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
            ['branch_id' => 2, 'discipleship_group_id' => 322, 'person_id' => 605, 'role' => 'leader', 'stage' => null, 'status' => 'active', 'started_on' => '2026-04-08', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 2, 'discipleship_group_id' => 322, 'person_id' => 510, 'role' => 'member', 'stage' => 'DG 2', 'status' => 'active', 'started_on' => '2026-04-08', 'created_at' => now(), 'updated_at' => now()],
            ['branch_id' => 2, 'discipleship_group_id' => 322, 'person_id' => 525, 'role' => 'member', 'stage' => 'DG 2', 'status' => 'active', 'started_on' => '2026-04-08', 'created_at' => now(), 'updated_at' => now()],
        ]);

        return ['leader_id' => 605, 'member_id' => 510, 'group_id' => 322];
    }

    private function tinyPng(): string
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        $this->assertIsString($png);

        return $png;
    }
}



