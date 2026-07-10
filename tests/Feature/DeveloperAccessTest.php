<?php

namespace Tests\Feature;

use App\Models\ActivityRequest;
use App\Models\User;
use App\Services\Branches\BranchCatalog;
use App\Services\Developer\DeveloperDatabaseService;
use App\Services\Developer\DeveloperDiagnosticsService;
use App\Support\RuntimeBootstrap;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeveloperAccessTest extends TestCase
{
    public function test_developer_login_redirects_to_developer_dashboard(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();

        $response = $this->post('/login', [
            'username' => 'developer',
            'password' => 'secret-dev',
        ]);

        $response->assertRedirect('/developer');
        $this->assertAuthenticatedAs(User::query()->where('username', 'developer')->firstOrFail());
        $this->assertSame('developer', current_auth_access_scope());
        $this->assertSame('', current_user_branch());
    }

    public function test_non_developer_cannot_open_developer_routes(): void
    {
        $this->createCoreTables();
        $this->seedUser('branch_user', 'pemuridan_cabang');
        $this->loginAs('branch_user');

        $this->get('/developer')->assertRedirect('/pemuridan/dashboard?error=access_denied');
        $this->get('/developer/users')->assertRedirect('/pemuridan/dashboard?error=access_denied');
        $this->get('/developer/branches')->assertRedirect('/pemuridan/dashboard?error=access_denied');
        $this->get('/developer/config')->assertRedirect('/pemuridan/dashboard?error=access_denied');
        $this->get('/developer/database')->assertRedirect('/pemuridan/dashboard?error=access_denied');
        $this->get('/developer/statistics')->assertRedirect('/pemuridan/dashboard?error=access_denied');
    }

    public function test_developer_bypasses_existing_access_gates(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $this->loginAs('developer');
        RuntimeBootstrap::load();

        $this->assertTrue(branch_can_access_page(current_user_branch(), 'discipleship_dashboard'));
        $this->assertTrue(branch_can_access_page(current_user_branch(), 'worship_penatalayan'));
        $this->assertTrue(branch_can_use_action(current_user_branch(), 'save_worship_penatalayan'));
        $this->assertFalse(branch_can_use_action(current_user_branch(), 'save_person'));
        $this->assertTrue(is_effective_central_discipleship_readonly());
        $this->assertTrue(can_manage_public_materials());
        $this->assertTrue(can_manage_difficult_questions());
        $this->assertTrue(branch_can_access_secure_upload_path(current_user_branch(), 'restricted/example.pdf'));
    }

    public function test_developer_has_no_active_branch_or_branch_switch_route(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $this->loginAs('developer');

        session()->put('developer_branch_id', 2);
        session()->put('developer_branch', 'gm');

        $this->get('/developer')
            ->assertOk()
            ->assertSee('Pemuridan lintas cabang hanya lihat')
            ->assertDontSee('Cabang Aktif')
            ->assertDontSee('Pakai')
            ->assertDontSee('Mode Pusat')
            ->assertDontSee('Semua Cabang');

        $this->post('/developer/branch', ['branch_id' => 2])->assertNotFound();

        $this->assertNull(session('developer_branch_id'));
        $this->assertNull(session('developer_branch'));
        $this->assertSame('', current_user_branch());
    }

    public function test_developer_can_manage_users_and_reset_passwords(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $this->loginAs('developer');

        $this->post('/developer/users', [
            'username' => 'managed_user',
            'password' => 'new-secret',
            'branch_id' => 2,
            'access_scope' => 'pemuridan_cabang',
            'is_active' => '1',
        ])->assertRedirect('/developer/users?status=created');

        $storedPassword = (string) DB::table('users')->where('username', 'managed_user')->value('password');
        $this->assertNotSame('new-secret', $storedPassword);
        $this->assertTrue(Hash::check('new-secret', $storedPassword));

        $managedUserId = (int) DB::table('users')->where('username', 'managed_user')->value('id');
        $this->post('/developer/users/'.$managedUserId, [
            'access_scope' => 'pelayan',
            'is_active' => '0',
        ])->assertRedirect('/developer/users?status=updated&user='.$managedUserId);

        $this->assertDatabaseHas('users', [
            'username' => 'managed_user',
            'branch_id' => null,
            'access_scope' => 'pelayan',
            'is_active' => false,
        ]);

        $this->post('/developer/users/'.$managedUserId.'/password', [
            'password' => 'reset-secret',
        ])->assertRedirect('/developer/users?status=password_reset&user='.$managedUserId);

        $resetPassword = (string) DB::table('users')->where('username', 'managed_user')->value('password');
        $this->assertTrue(Hash::check('reset-secret', $resetPassword));
    }

    public function test_developer_cannot_deactivate_self_or_remove_last_active_developer(): void
    {
        $this->createCoreTables();
        $developerId = $this->seedDeveloper();
        $this->loginAs('developer');

        $this->post('/developer/users/'.$developerId, [
            'access_scope' => 'developer',
            'is_active' => '0',
        ])->assertRedirect('/developer/users?error=self_deactivate&user='.$developerId);

        $this->post('/developer/users/'.$developerId, [
            'branch_id' => 1,
            'access_scope' => 'pemuridan_cabang',
            'is_active' => '1',
        ])->assertRedirect('/developer/users?error=last_active_developer&user='.$developerId);

        $this->assertDatabaseHas('users', [
            'username' => 'developer',
            'access_scope' => 'developer',
            'is_active' => true,
        ]);
    }

    public function test_developer_config_update_is_persisted_and_used(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $this->loginAs('developer');

        $this->post('/developer/config', [
            'church_name' => 'REC Internal',
            'app_timezone' => 'Asia/Jakarta',
            'developer_debug_banner' => '1',
        ])->assertRedirect('/developer/config?status=saved');

        $this->assertDatabaseHas('konfigurasi', [
            'key' => 'church_name',
            'value' => 'REC Internal',
        ]);
        $this->assertDatabaseHas('konfigurasi', [
            'key' => 'developer_debug_banner',
            'value' => '1',
        ]);

        $this->get('/developer/config')
            ->assertOk()
            ->assertSee('REC Internal')
            ->assertSee('data-developer-header', false)
            ->assertSee('discipleship-page-header__stats', false)
            ->assertDontSee('developer-hub-nav', false)
            ->assertSee('class="btn developer-primary-action"', false);
        $this->get('/developer')
            ->assertOk()
            ->assertSee('Developer debug aktif');
    }

    public function test_user_management_uses_roles_and_has_no_central_branch_option(): void
    {
        $this->createCoreTables();
        $developerId = $this->seedDeveloper();
        $branchUserId = $this->seedUser('branch_user', 'pemuridan_cabang');
        $this->loginAs('developer');

        $this->get('/developer/users')
            ->assertOk()
            ->assertSee('data-developer-header', false)
            ->assertSee('discipleship-page-header__stats', false)
            ->assertDontSee('developer-hub-nav', false)
            ->assertSee('Role')
            ->assertSee('value="pemuridan_cabang"', false)
            ->assertSee('value="pemuridan_pusat"', false)
            ->assertSee('value="pelayan"', false)
            ->assertSee('Tanpa cabang')
            ->assertSee('branch_user')
            ->assertSee('<details class="developer-user-item" data-developer-user', false)
            ->assertSee('<summary class="developer-user-toggle">', false)
            ->assertSee('<strong>branch_user</strong>', false)
            ->assertDontSee(route('developer.users.update', $developerId), false)
            ->assertDontSee('name="name"', false)
            ->assertDontSee('name="email"', false)
            ->assertDontSee('value="pusat"', false);

        $this->get('/developer/users?user='.$branchUserId)
            ->assertOk()
            ->assertSee('<details class="developer-user-item" data-developer-user open>', false);
    }

    public function test_developer_can_manage_branches_from_database(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $this->loginAs('developer');

        $this->get('/developer/branches')
            ->assertOk()
            ->assertSee('Manajemen Cabang')
            ->assertSee('Tambah Cabang')
            ->assertSee('Tidak Aktif')
            ->assertDontSee('Produksi aktif muncul di public form dan pilihan user cabang.');

        $this->post('/developer/branches', [
            'label' => 'Surabaya',
            'is_active' => '0',
            'camp_gap_participant_target' => 61,
            'msk_completion_target' => 62,
            'dg1_completion_target' => 63,
            'dg2_completion_target' => 64,
            'dg3_completion_target' => 65,
        ])->assertRedirect('/developer/branches?status=created');

        $branchId = (int) DB::table('cabang')->where('label', 'Surabaya')->value('id');
        $this->assertGreaterThan(0, $branchId);
        $this->assertDatabaseHas('cabang', [
            'id' => $branchId,
            'label' => 'Surabaya',
            'is_active' => false,
            'camp_gap_participant_target' => 61,
        ]);
        RuntimeBootstrap::load();
        $this->assertNull(app(BranchCatalog::class)->idForSlug('surabaya'));
        $this->assertSame($branchId, app(BranchCatalog::class)->idForSlug('surabaya', true));

        $this->get('/developer/users')
            ->assertOk()
            ->assertDontSee('Surabaya');

        $this->post('/developer/branches/'.$branchId, [
            'label' => 'Surabaya Raya',
            'is_active' => '1',
            'camp_gap_participant_target' => 71,
            'msk_completion_target' => 72,
            'dg1_completion_target' => 73,
            'dg2_completion_target' => 74,
            'dg3_completion_target' => 75,
        ])->assertRedirect('/developer/branches?status=updated&branch='.$branchId);

        $this->assertDatabaseHas('cabang', [
            'id' => $branchId,
            'label' => 'Surabaya Raya',
            'is_active' => true,
            'msk_completion_target' => 72,
        ]);
        $this->assertSame($branchId, app(BranchCatalog::class)->idForSlug('surabaya-raya'));
        $this->get('/developer/users')
            ->assertOk()
            ->assertSee('Surabaya Raya');

        $this->post('/developer/branches/'.$branchId, [
            'label' => 'GM',
            'is_active' => '1',
            'camp_gap_participant_target' => 71,
            'msk_completion_target' => 72,
            'dg1_completion_target' => 73,
            'dg2_completion_target' => 74,
            'dg3_completion_target' => 75,
        ])->assertRedirect('/developer/branches?error=label_taken&branch='.$branchId);
    }

    public function test_developer_can_delete_only_empty_branches(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $usedBranchId = (int) DB::table('cabang')->insertGetId([
            'label' => 'Used Branch',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $emptyBranchId = (int) DB::table('cabang')->insertGetId([
            'label' => 'Empty Branch',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedUser('used_branch_user', 'pemuridan_cabang', ['branch_id' => $usedBranchId]);
        app(BranchCatalog::class)->clearCache();
        $this->loginAs('developer');

        $this->post('/developer/branches/'.$usedBranchId.'/delete')
            ->assertRedirect('/developer/branches?error=branch_not_empty&branch='.$usedBranchId);
        $this->assertDatabaseHas('cabang', ['id' => $usedBranchId]);

        $this->post('/developer/branches/'.$emptyBranchId.'/delete')
            ->assertRedirect('/developer/branches?status=deleted');
        $this->assertDatabaseMissing('cabang', ['id' => $emptyBranchId]);
    }

    public function test_developer_can_browse_database_tables_and_structure(): void
    {
        $this->createCoreTables();
        $this->createDatabaseAdminSampleTables();
        $this->seedDeveloper();
        $this->loginAs('developer');

        $this->get('/developer/database')
            ->assertOk()
            ->assertSee('Database Admin')
            ->assertSee('db_admin_sample')
            ->assertSee('SQL');

        $this->get('/developer/database/db_admin_sample?search=Alpha')
            ->assertOk()
            ->assertSee('Data Row')
            ->assertSee('Alpha')
            ->assertDontSee('Beta')
            ->assertSee('Hitung total');

        $browse = app(DeveloperDatabaseService::class)->browse('db_admin_sample');
        $this->assertNull($browse['total']);
        $this->assertFalse($browse['total_known']);
        $this->assertSame(2, count($browse['rows']));

        $countedBrowse = app(DeveloperDatabaseService::class)->browse('db_admin_sample', ['count_total' => '1']);
        $this->assertSame(2, $countedBrowse['total']);
        $this->assertTrue($countedBrowse['total_known']);

        $this->get('/developer/database/db_admin_sample?tab=structure')
            ->assertOk()
            ->assertSee('Kolom')
            ->assertSee('name')
            ->assertSee('PK');

        $this->get('/developer/database/db_admin_no_pk')
            ->assertOk()
            ->assertSee('Primary key tidak ada')
            ->assertSee('Readonly');

        $this->get('/developer/database/db_admin_fk_child')
            ->assertOk()
            ->assertSee('Parent Alpha')
            ->assertSee('owner_id');

        $fkBrowse = app(DeveloperDatabaseService::class)->browse('db_admin_fk_child');
        $this->assertSame('Parent Alpha', $fkBrowse['rows'][0]['foreign_labels']['owner_id']['label'] ?? null);
    }

    public function test_developer_database_admin_can_insert_update_and_delete_rows(): void
    {
        $this->createCoreTables();
        $this->createDatabaseAdminSampleTables();
        $this->seedDeveloper();
        $this->loginAs('developer');

        $this->post('/developer/database/db_admin_sample/rows', [
            'values' => [
                'id' => '',
                'name' => 'Gamma',
                'score' => '30',
                'notes' => 'Created from DB admin',
            ],
        ])->assertRedirect('/developer/database/db_admin_sample?status=row_created&tab=browse');
        $this->assertDatabaseHas('db_admin_sample', ['name' => 'Gamma', 'score' => 30]);

        $rowKey = $this->rowKeyForSampleName('Gamma');
        $this->post('/developer/database/db_admin_sample/rows/'.$rowKey, [
            'values' => [
                'id' => (string) DB::table('db_admin_sample')->where('name', 'Gamma')->value('id'),
                'name' => 'Gamma Updated',
                'score' => '31',
                'notes' => 'Updated from DB admin',
            ],
        ])->assertRedirect('/developer/database/db_admin_sample?status=row_updated&tab=browse');
        $this->assertDatabaseHas('db_admin_sample', ['name' => 'Gamma Updated', 'score' => 31]);

        $rowKey = $this->rowKeyForSampleName('Gamma Updated');
        $this->post('/developer/database/db_admin_sample/rows/'.$rowKey.'/delete')
            ->assertRedirect('/developer/database/db_admin_sample?error=confirm_required&tab=browse');
        $this->assertDatabaseHas('db_admin_sample', ['name' => 'Gamma Updated']);

        $this->post('/developer/database/db_admin_sample/rows/'.$rowKey.'/delete', [
            'confirm_danger' => '1',
        ])->assertRedirect('/developer/database/db_admin_sample?status=row_deleted&tab=browse');
        $this->assertDatabaseMissing('db_admin_sample', ['name' => 'Gamma Updated']);
    }

    public function test_developer_database_sql_console_requires_confirmation_for_mutations(): void
    {
        $this->createCoreTables();
        $this->createDatabaseAdminSampleTables();
        $this->seedDeveloper();
        $this->loginAs('developer');

        $this->post('/developer/database/query', [
            'table' => 'db_admin_sample',
            'sql' => "UPDATE db_admin_sample SET score = 99 WHERE name = 'Alpha'",
        ])->assertOk()
            ->assertSee('Konfirmasi diperlukan');
        $this->assertSame(10, (int) DB::table('db_admin_sample')->where('name', 'Alpha')->value('score'));

        $this->post('/developer/database/query', [
            'table' => 'db_admin_sample',
            'sql' => "UPDATE db_admin_sample SET score = 99 WHERE name = 'Alpha'",
            'confirm_danger' => '1',
        ])->assertOk()
            ->assertSee('Query mutasi berhasil');
        $this->assertSame(99, (int) DB::table('db_admin_sample')->where('name', 'Alpha')->value('score'));

        $this->post('/developer/database/query', [
            'table' => 'db_admin_sample',
            'sql' => 'SELECT name, score FROM db_admin_sample WHERE score = 99',
        ])->assertOk()
            ->assertSee('Alpha')
            ->assertSee('99');
    }

    public function test_developer_database_admin_exports_and_imports_sql(): void
    {
        $this->createCoreTables();
        $this->createDatabaseAdminSampleTables();
        $this->seedDeveloper();
        $this->loginAs('developer');

        $export = app(DeveloperDatabaseService::class)->exportSql('db_admin_sample');
        $this->assertSame('exported', $export['status'] ?? null);
        $this->assertFileExists((string) $export['path']);
        $sql = (string) file_get_contents((string) $export['path']);
        @unlink((string) $export['path']);
        $this->assertStringContainsString('db_admin_sample', $sql);
        $this->assertStringContainsString('INSERT INTO', $sql);

        $tmpPath = tempnam(sys_get_temp_dir(), 'rec_import_');
        $this->assertIsString($tmpPath);
        file_put_contents($tmpPath, "CREATE TABLE db_admin_imported (id integer primary key autoincrement, name varchar(255));\nINSERT INTO db_admin_imported (name) VALUES ('Imported Row');\n");

        $this->post('/developer/database/import', [
            'confirm_danger' => '1',
            'sql_file' => new UploadedFile($tmpPath, 'db-admin-import.sql', 'application/sql', null, true),
        ])->assertRedirect('/developer/database?status=imported');

        $this->assertTrue(Schema::hasTable('db_admin_imported'));
        $this->assertDatabaseHas('db_admin_imported', ['name' => 'Imported Row']);
    }

    public function test_developer_can_access_as_active_user_and_return_to_developer(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $branchUserId = $this->seedUser('branch_user', 'pemuridan_cabang', ['branch_id' => 2]);
        $inactiveUserId = $this->seedUser('inactive_user', 'pemuridan_cabang', ['is_active' => false]);
        $this->loginAs('developer');

        $this->get('/developer/users')
            ->assertOk()
            ->assertSee(route('developer.users.access', $branchUserId), false)
            ->assertDontSee(route('developer.users.access', $inactiveUserId), false);

        $this->post('/developer/users/'.$branchUserId.'/access')
            ->assertRedirect('/pemuridan/dashboard');

        RuntimeBootstrap::load();
        $this->assertTrue(is_developer_access_mode());
        $this->assertSame('branch_user', current_username());
        $this->assertSame('pemuridan_cabang', current_auth_access_scope());
        $this->assertSame('gm', current_user_branch());

        $this->get('/developer/users')
            ->assertRedirect('/pemuridan/dashboard?error=access_denied');

        $this->get('/pengaturan')
            ->assertOk()
            ->assertSee('Mode akses: developer sebagai branch_user')
            ->assertSee('Kembali ke Developer')
            ->assertSee('Mode akses developer · password dikunci')
            ->assertSee('disabled aria-disabled="true"', false);

        $this->post('/pengaturan', [
            'current_password' => 'secret-dev',
            'new_password' => 'new-secret',
            'new_password_confirm' => 'new-secret',
        ])->assertRedirect('/pengaturan?error=developer_access_password_disabled');

        $this->post('/developer/access/return')
            ->assertRedirect('/developer/users?status=access_returned');

        RuntimeBootstrap::load();
        $this->assertFalse(is_developer_access_mode());
        $this->assertSame('developer', current_username());
        $this->assertSame('developer', current_auth_access_scope());
        $this->assertSame('', current_user_branch());
    }

    public function test_developer_access_rejects_inactive_self_and_developer_targets(): void
    {
        $this->createCoreTables();
        $developerId = $this->seedDeveloper();
        $inactiveUserId = $this->seedUser('inactive_user', 'pemuridan_cabang', ['is_active' => false]);
        $otherDeveloperId = $this->seedUser('otherdev', 'developer', ['branch_id' => null]);
        $this->loginAs('developer');

        $this->post('/developer/users/'.$inactiveUserId.'/access')
            ->assertRedirect('/developer/users?error=access_target_inactive&user='.$inactiveUserId);
        $this->post('/developer/users/'.$developerId.'/access')
            ->assertRedirect('/developer/users?error=access_self&user='.$developerId);
        $this->post('/developer/users/'.$otherDeveloperId.'/access')
            ->assertRedirect('/developer/users?error=access_target_developer&user='.$otherDeveloperId);

        RuntimeBootstrap::load();
        $this->assertFalse(is_developer_access_mode());
        $this->assertSame('developer', current_auth_access_scope());
    }

    public function test_developer_diagnostics_counts_only_active_discipleship_branches(): void
    {
        $this->createCoreTables();

        DB::table('cabang')->insert([
            [
                'label' => 'Pusat',
                'is_active' => true,
                'created_at' => '2026-06-19 08:00:00',
                'updated_at' => '2026-06-19 08:00:00',
            ],
            [
                'label' => 'Inactive',
                'is_active' => false,
                'created_at' => '2026-06-19 08:00:00',
                'updated_at' => '2026-06-19 08:00:00',
            ],
        ]);

        $summary = app(DeveloperDiagnosticsService::class)->summary();

        $this->assertSame(3, $summary['counts']['branches']);
    }

    public function test_developer_dashboard_shows_operational_overview_and_omits_diagnostics(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $branchUserId = $this->seedUser('branch_user', 'pemuridan_cabang');
        $this->createDashboardTelemetryTables();
        $this->seedDashboardTelemetry($branchUserId);
        $this->loginAs('developer');

        $summary = app(DeveloperDiagnosticsService::class)->summary();

        $this->assertArrayNotHasKey('storage', $summary);
        $this->assertArrayNotHasKey('materials', $summary);

        $this->get('/developer')
            ->assertOk()
            ->assertSee('data-developer-header', false)
            ->assertSee('discipleship-page-header__stats', false)
            ->assertDontSee('developer-hub-nav', false)
            ->assertSee('Kondisi Aplikasi')
            ->assertSee('Kunjungan Publik')
            ->assertSee('Aktivitas Terbaru')
            ->assertSee('Perlu Dicek')
            ->assertSee('Total Request')
            ->assertSee('Error 5xx')
            ->assertSee('/pemuridan/anggota', false)
            ->assertSee('/pemuridan/gagal', false)
            ->assertSee('/publik/materi', false)
            ->assertDontSee('Aktif 5 Menit')
            ->assertDontSee('Akses Cepat')
            ->assertDontSee('Navigasi')
            ->assertDontSee('Diagnostics')
            ->assertDontSee('Runtime')
            ->assertDontSee('<small>/developer/config</small>', false)
            ->assertDontSee('/publik/bot', false)
            ->assertDontSee('Material Audit')
            ->assertDontSee('Public storage')
            ->assertDontSee('Path target');
    }

    public function test_steward_has_no_branch_and_cannot_access_discipleship_or_developer(): void
    {
        $this->createCoreTables();
        $this->seedUser('keziaae', 'pelayan', ['branch_id' => null]);
        $this->loginAs('keziaae');
        RuntimeBootstrap::load();

        $this->assertSame('', current_user_branch());
        $this->assertTrue(current_user_can_access_worship());
        $this->assertFalse(branch_can_access_page('', 'discipleship_dashboard'));
        $this->assertTrue(branch_can_access_page('', 'worship_penatalayan'));
        $this->get('/pemuridan/dashboard')->assertRedirect('/ibadah/penatalayan?error=access_denied');
        $this->get('/developer')->assertRedirect('/ibadah/penatalayan?error=access_denied');
    }

    public function test_discipleship_branch_role_requires_a_branch(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $this->loginAs('developer');

        $this->post('/developer/users', [
            'username' => 'missing_branch',
            'password' => 'new-secret',
            'access_scope' => 'pemuridan_cabang',
            'is_active' => '1',
        ])->assertRedirect('/developer/users?error=branch_invalid');

        $this->assertDatabaseMissing('users', ['username' => 'missing_branch']);
    }

    public function test_non_developer_users_cannot_be_assigned_to_developer_testing_branch(): void
    {
        $this->createCoreTables();
        $testingBranchId = $this->seedTestingBranch();
        $this->seedDeveloper();
        $branchUserId = $this->seedUser('branch_user', 'pemuridan_cabang');
        $this->loginAs('developer');

        $this->post('/developer/users', [
            'username' => 'testing_user',
            'password' => 'new-secret',
            'branch_id' => $testingBranchId,
            'access_scope' => 'pemuridan_cabang',
            'is_active' => '1',
        ])->assertRedirect('/developer/users?error=branch_invalid');
        $this->assertDatabaseMissing('users', ['username' => 'testing_user']);

        $this->post('/developer/users/'.$branchUserId, [
            'branch_id' => $testingBranchId,
            'access_scope' => 'pemuridan_cabang',
            'is_active' => '1',
        ])->assertRedirect('/developer/users?error=branch_invalid&user='.$branchUserId);
        $this->assertSame(1, (int) DB::table('users')->where('id', $branchUserId)->value('branch_id'));
    }

    public function test_developer_testing_branch_is_editable_context(): void
    {
        $this->createCoreTables();
        $testingBranchId = $this->seedTestingBranch();
        $this->seedDeveloper();
        $this->loginAs('developer');

        $this->get('/pemuridan/target?branch_id='.$testingBranchId)
            ->assertOk()
            ->assertSee('Mode Eksperimen Developer')
            ->assertSee('Cabang Testing')
            ->assertSee('name="target_msk_completed"', false)
            ->assertSee('Simpan Target');

        RuntimeBootstrap::load();
        $this->assertSame('testing', current_user_branch());
        $this->assertSame($testingBranchId, current_user_branch_id());
        $this->assertFalse(is_effective_central_discipleship_readonly());
        $this->assertTrue(branch_can_use_action(current_user_branch(), 'save_person'));
    }

    private function createCoreTables(): void
    {
        Schema::dropIfExists('aktivitas');
        Schema::dropIfExists('kunjungan_halaman');
        Schema::dropIfExists('peristiwa_aktivitas');
        Schema::dropIfExists('permintaan_aktivitas');
        Schema::dropIfExists('konfigurasi');
        Schema::dropIfExists('percobaan_login');
        Schema::dropIfExists('cabang');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username', 120)->nullable()->unique();
            $table->string('password');
            $table->rememberToken();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('access_scope', 80)->default('pemuridan_cabang');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('percobaan_login', function (Blueprint $table): void {
            $table->id();
            $table->string('attempt_key', 120)->unique();
            $table->unsignedInteger('failed_attempt_count')->default(0);
            $table->timestamp('window_started_at')->nullable();
            $table->timestamp('locked_until_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();
        });

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

        Schema::create('konfigurasi', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->string('updated_by', 120)->nullable();
            $table->timestamps();
        });

        foreach (['Kutisari', 'GM', 'Darmo'] as $label) {
            DB::table('cabang')->insert([
                'label' => $label,
                'is_active' => true,
                'created_at' => '2026-06-19 08:00:00',
                'updated_at' => '2026-06-19 08:00:00',
            ]);
        }

        app(BranchCatalog::class)->clearCache();
    }

    private function seedTestingBranch(): int
    {
        $id = (int) DB::table('cabang')->insertGetId([
            'label' => 'Testing',
            'is_active' => false,
            'created_at' => '2026-07-09 08:00:00',
            'updated_at' => '2026-07-09 08:00:00',
        ]);
        app(BranchCatalog::class)->clearCache();

        return $id;
    }

    private function createDashboardTelemetryTables(): void
    {
        Schema::dropIfExists('aktivitas');
        Schema::dropIfExists('kunjungan_halaman');
        Schema::dropIfExists('peristiwa_aktivitas');
        Schema::dropIfExists('permintaan_aktivitas');

        $mergeMigration = require database_path('migrations/2026_07_07_000001_merge_activity_audit_tables.php');
        $mergeMigration->up();
    }

    private function seedDashboardTelemetry(int $branchUserId): void
    {
        $recentId = (string) Str::ulid();
        $developerId = (string) Str::ulid();
        $errorId = (string) Str::ulid();
        $now = now('UTC');

        DB::table('aktivitas')->insert([
            [
                'id' => $recentId,
                'actor_type' => 'user',
                'user_id' => $branchUserId,
                'username' => 'branch_user',
                'role' => 'pemuridan_cabang',
                'method' => 'POST',
                'route_name' => 'discipleship.people-list',
                'path' => '/pemuridan/anggota',
                'category' => 'data',
                'action' => 'people.update',
                'http_status' => 200,
                'outcome' => 'success',
                'duration_ms' => 84.5,
                'started_at' => $now->copy()->subMinutes(20)->format('Y-m-d H:i:s.u'),
                'completed_at' => $now->copy()->subMinutes(20)->addMilliseconds(85)->format('Y-m-d H:i:s.u'),
            ],
            [
                'id' => $developerId,
                'actor_type' => 'user',
                'user_id' => 1,
                'username' => 'developer',
                'role' => 'developer',
                'method' => 'GET',
                'route_name' => 'developer.config',
                'path' => '/developer/config',
                'category' => 'developer',
                'action' => 'config.open',
                'http_status' => 200,
                'outcome' => 'success',
                'duration_ms' => 33.1,
                'started_at' => $now->copy()->subMinutes(10)->format('Y-m-d H:i:s.u'),
                'completed_at' => $now->copy()->subMinutes(10)->addMilliseconds(34)->format('Y-m-d H:i:s.u'),
            ],
            [
                'id' => $errorId,
                'actor_type' => 'user',
                'user_id' => $branchUserId,
                'username' => 'branch_user',
                'role' => 'pemuridan_cabang',
                'method' => 'GET',
                'route_name' => 'discipleship.error',
                'path' => '/pemuridan/gagal',
                'category' => 'request',
                'action' => 'request.failed',
                'http_status' => 500,
                'outcome' => 'error',
                'duration_ms' => 140.2,
                'started_at' => $now->copy()->subMinutes(5)->format('Y-m-d H:i:s.u'),
                'completed_at' => $now->copy()->subMinutes(5)->addMilliseconds(141)->format('Y-m-d H:i:s.u'),
            ],
        ]);

        ActivityRequest::query()->findOrFail($recentId)->appendEventEntry(['category' => 'data', 'action' => 'people.updated', 'subject_label' => 'Peserta Test', 'description' => 'Updated person', 'occurred_at' => $now->copy()->subMinutes(20)->format('Y-m-d H:i:s.u')]);
        ActivityRequest::query()->findOrFail($recentId)->appendEventEntry(['category' => 'data', 'action' => 'audit.logged', 'subject_label' => 'Peserta Test', 'description' => 'Audit logged', 'occurred_at' => $now->copy()->subMinutes(20)->format('Y-m-d H:i:s.u')]);
        ActivityRequest::query()->findOrFail($errorId)->appendEventEntry(['category' => 'request', 'action' => 'request.failed', 'subject_label' => 'Error Test', 'description' => 'Failure logged', 'occurred_at' => $now->copy()->subMinutes(5)->format('Y-m-d H:i:s.u')]);

        DB::table('aktivitas')->insert([
            $this->dashboardPageViewRow([
                'id' => (string) Str::ulid(),
                'visitor_hash' => str_repeat('a', 64),
                'user_id' => null,
                'segment' => 'publik',
                'route_name' => 'public.materials',
                'path' => '/publik/materi',
                'is_bot' => false,
                'is_prefetch' => false,
                'response_ms' => 52.4,
                'occurred_at' => $now->copy()->subDay()->format('Y-m-d H:i:s.u'),
            ]),
            $this->dashboardPageViewRow([
                'id' => (string) Str::ulid(),
                'visitor_hash' => str_repeat('b', 64),
                'user_id' => null,
                'segment' => 'publik',
                'route_name' => 'public.bot',
                'path' => '/publik/bot',
                'is_bot' => true,
                'is_prefetch' => false,
                'response_ms' => 20.0,
                'occurred_at' => $now->copy()->subDay()->format('Y-m-d H:i:s.u'),
            ]),
            $this->dashboardPageViewRow([
                'id' => (string) Str::ulid(),
                'visitor_hash' => str_repeat('c', 64),
                'user_id' => null,
                'segment' => 'publik',
                'route_name' => 'public.prefetch',
                'path' => '/publik/prefetch',
                'is_bot' => false,
                'is_prefetch' => true,
                'response_ms' => 19.0,
                'occurred_at' => $now->copy()->subDay()->format('Y-m-d H:i:s.u'),
            ]),
        ]);
    }

    private function createDatabaseAdminSampleTables(): void
    {
        Schema::dropIfExists('db_admin_imported');
        Schema::dropIfExists('db_admin_fk_child');
        Schema::dropIfExists('db_admin_fk_owner');
        Schema::dropIfExists('db_admin_no_pk');
        Schema::dropIfExists('db_admin_sample');

        Schema::create('db_admin_sample', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->integer('score')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('db_admin_no_pk', function (Blueprint $table): void {
            $table->string('code');
            $table->string('value')->nullable();
        });
        Schema::create('db_admin_fk_owner', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
        });
        Schema::create('db_admin_fk_child', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->string('note')->nullable();
            $table->foreign('owner_id')->references('id')->on('db_admin_fk_owner');
        });

        DB::table('db_admin_sample')->insert([
            ['name' => 'Alpha', 'score' => 10, 'notes' => 'First row', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Beta', 'score' => 20, 'notes' => 'Second row', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('db_admin_no_pk')->insert([
            ['code' => 'readonly', 'value' => 'No primary key'],
        ]);
        $ownerId = (int) DB::table('db_admin_fk_owner')->insertGetId(['label' => 'Parent Alpha']);
        DB::table('db_admin_fk_child')->insert(['owner_id' => $ownerId, 'note' => 'Child row']);
    }

    private function rowKeyForSampleName(string $name): string
    {
        $browse = app(DeveloperDatabaseService::class)->browse('db_admin_sample', ['search' => $name]);
        $row = collect($browse['rows'] ?? [])
            ->first(static fn (array $row): bool => ($row['values']['name'] ?? null) === $name);
        $this->assertIsArray($row);
        $this->assertNotEmpty($row['key'] ?? null);

        return (string) $row['key'];
    }

    /** @param array<string, mixed> $overrides */
    private function dashboardPageViewRow(array $overrides): array
    {
        $id = (string) ($overrides['id'] ?? Str::ulid());
        $occurredAt = (string) ($overrides['occurred_at'] ?? now('UTC')->format('Y-m-d H:i:s.u'));
        $path = (string) ($overrides['path'] ?? '/');

        return array_merge([
            'id' => $id,
            'actor_type' => 'anonymous',
            'method' => 'GET',
            'path' => $path,
            'category' => 'request',
            'action' => 'request.page_view',
            'http_status' => 200,
            'outcome' => 'succeeded',
            'started_at' => $occurredAt,
            'completed_at' => $occurredAt,
            'is_page_view' => true,
            'identity_source' => 'legacy_session',
            'device_type' => 'unknown',
            'is_bot' => false,
            'is_prefetch' => false,
            'occurred_at' => $occurredAt,
        ], $overrides, ['id' => $id]);
    }

    private function seedDeveloper(): int
    {
        return $this->seedUser('developer', 'developer', [
            'password' => Hash::make('secret-dev'),
            'branch_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedUser(string $username, string $scope, array $overrides = []): int
    {
        return (int) DB::table('users')->insertGetId(array_merge([
            'username' => $username,
            'password' => Hash::make('secret-test'),
            'branch_id' => $scope === 'pemuridan_cabang' ? 1 : null,
            'access_scope' => $scope,
            'is_active' => true,
            'created_at' => '2026-06-19 08:00:00',
            'updated_at' => '2026-06-19 08:00:00',
        ], $overrides));
    }

    private function loginAs(string $username): void
    {
        $user = User::query()->where('username', $username)->firstOrFail();

        $this->actingAs($user);
    }
}
