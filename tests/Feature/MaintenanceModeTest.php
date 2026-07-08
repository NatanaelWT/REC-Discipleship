<?php

namespace Tests\Feature;

use App\Enums\PublicMaterialMenuKey;
use App\Models\User;
use App\Services\AppConfig\AppConfigService;
use App\Support\RuntimeBootstrap;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    private string $testBasePath = 'msk-dg-test-maintenance-mode';

    protected function setUp(): void
    {
        parent::setUp();

        RuntimeBootstrap::load();
        AppConfigService::clearCache();
        config(['public_materials.base_path' => $this->testBasePath]);
        $this->deleteTestUploadFolders();
    }

    protected function tearDown(): void
    {
        $this->deleteTestUploadFolders();
        config(['public_materials.base_path' => 'msk-dg']);
        AppConfigService::clearCache();

        parent::tearDown();
    }

    public function test_developer_can_toggle_maintenance_mode_from_config(): void
    {
        $this->createCoreTables();
        $developerId = $this->seedUser('developer', 'developer', ['branch_id' => null]);
        $this->actingAs(User::query()->findOrFail($developerId));

        $this->post('/developer/config', [
            'church_name' => 'REC Internal',
            'app_timezone' => 'Asia/Jakarta',
            'developer_debug_banner' => '0',
            'maintenance_mode' => '1',
        ])->assertRedirect('/developer/config?status=saved');

        $this->assertDatabaseHas('konfigurasi', [
            'key' => 'maintenance_mode',
            'value' => '1',
        ]);

        $this->get('/developer/config')
            ->assertOk()
            ->assertSee('Maintenance mode aktif')
            ->assertSee('Login dan akses aplikasi untuk non-developer sedang dikunci');

        $this->post('/developer/config', [
            'church_name' => 'REC Internal',
            'app_timezone' => 'Asia/Jakarta',
            'developer_debug_banner' => '0',
            'maintenance_mode' => '0',
        ])->assertRedirect('/developer/config?status=saved');

        $this->assertDatabaseHas('konfigurasi', [
            'key' => 'maintenance_mode',
            'value' => '0',
        ]);
    }

    public function test_maintenance_rejects_non_developer_login_and_allows_developer_login(): void
    {
        $this->createCoreTables();
        $this->enableMaintenanceMode();
        $this->seedUser('branch_user', 'pemuridan_cabang');
        $this->seedUser('central_user', 'pemuridan_pusat', ['branch_id' => null]);
        $this->seedUser('steward_user', 'pelayan', ['branch_id' => null]);
        $developerId = $this->seedUser('developer', 'developer', ['branch_id' => null]);

        foreach (['branch_user', 'central_user', 'steward_user'] as $username) {
            $this->post('/login', [
                'username' => $username,
                'password' => 'secret-test',
            ])->assertRedirect('/login?maintenance=1');

            $this->assertGuest();
            $this->assertNull(DB::table('users')->where('username', $username)->value('last_login_at'));
        }

        $this->post('/login', [
            'username' => 'developer',
            'password' => 'secret-test',
        ])->assertRedirect('/developer');

        $this->assertAuthenticatedAs(User::query()->findOrFail($developerId));
        $this->assertNotNull(DB::table('users')->where('username', 'developer')->value('last_login_at'));
    }

    public function test_maintenance_logs_out_existing_non_developer_session(): void
    {
        $this->createCoreTables();
        $this->enableMaintenanceMode();
        $branchUserId = $this->seedUser('branch_user', 'pemuridan_cabang');
        $this->actingAs(User::query()->findOrFail($branchUserId));

        $this->get('/pemuridan/dashboard')
            ->assertRedirect('/login?maintenance=1');

        $this->assertGuest();
    }

    public function test_public_material_read_only_routes_remain_available_during_maintenance(): void
    {
        $this->createConfigTable();
        $this->createMaterialTables();
        $this->enableMaintenanceMode();
        $fileId = $this->seedMaterialFile();

        $this->get('/')
            ->assertOk()
            ->assertSee('Aplikasi sedang maintenance. Untuk sementara, akses dibatasi.')
            ->assertSee('Tidak tersedia sementara')
            ->assertSee('aria-disabled="true"', false)
            ->assertSee('href="/materi/materi_dg_1"', false)
            ->assertDontSee('href="/publik/jurnal-dg"', false)
            ->assertDontSee('href="/publik/umpan-balik-anggota"', false)
            ->assertDontSee('href="/publik/pertanyaan-sulit/kirim"', false)
            ->assertDontSee('href="/publik/pertanyaan-sulit/jawaban"', false);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Aplikasi sedang maintenance. Untuk sementara, login tidak dapat digunakan.');

        $this->get('/materi/materi_dg_1')
            ->assertOk()
            ->assertSee('Materi DG tetap bisa dibaca');

        $this->get("/materi/materi_dg_1/{$fileId}/preview?raw=1")
            ->assertOk();

        $this->get("/materi/materi_dg_1/{$fileId}/download")
            ->assertOk();
    }

    public function test_maintenance_blocks_public_forms_and_submissions_without_writing(): void
    {
        $this->createConfigTable();
        $this->createDifficultQuestionsTable();
        $this->enableMaintenanceMode();

        $this->get('/publik/pertanyaan-sulit/kirim')
            ->assertRedirect('/materi/materi_dg_1?maintenance=1');

        $this->post('/publik/pertanyaan-sulit/kirim', [
            'asker_name' => 'Tester',
            'question_text' => 'Apakah maintenance memblokir submit?',
            'question_password' => 'secret-test',
            'question_password_confirm' => 'secret-test',
        ])->assertRedirect('/materi/materi_dg_1?maintenance=1');

        $this->get('/publik/jurnal-dg')
            ->assertRedirect('/materi/materi_dg_1?maintenance=1');

        $this->post('/publik/umpan-balik-anggota/form', [
            'public_cabang' => 'kutisari',
        ])->assertRedirect('/materi/materi_dg_1?maintenance=1');

        $this->getJson('/publik/pertanyaan-sulit/kirim')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Aplikasi sedang maintenance. Untuk sementara, akses dibatasi.');

        $this->assertSame(0, DB::table('pertanyaan_sulit')->count());
    }

    public function test_developer_access_mode_bypasses_maintenance_as_original_developer(): void
    {
        $this->createCoreTables();
        $this->enableMaintenanceMode();
        $developerId = $this->seedUser('developer', 'developer', ['branch_id' => null]);
        $branchUserId = $this->seedUser('branch_user', 'pemuridan_cabang', ['branch_id' => 2]);
        $this->actingAs(User::query()->findOrFail($developerId));

        $this->post('/developer/users/'.$branchUserId.'/access')
            ->assertRedirect('/pemuridan/dashboard');

        $this->get('/pengaturan')
            ->assertOk()
            ->assertSee('Mode akses: developer sebagai branch_user');
    }

    private function createCoreTables(): void
    {
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
            $table->timestamps();
        });

        $this->createConfigTable();

        foreach (['Kutisari', 'GM', 'Darmo'] as $label) {
            DB::table('cabang')->insert([
                'label' => $label,
                'is_active' => true,
                'created_at' => '2026-06-19 08:00:00',
                'updated_at' => '2026-06-19 08:00:00',
            ]);
        }
    }

    private function createConfigTable(): void
    {
        Schema::dropIfExists('konfigurasi');
        Schema::create('konfigurasi', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->string('updated_by', 120)->nullable();
            $table->timestamps();
        });
        AppConfigService::clearCache();
    }

    private function createMaterialTables(): void
    {
        Schema::dropIfExists('materi_publik');
        Schema::create('materi_publik', function (Blueprint $table): void {
            $table->id();
            $table->string('menu', 80)->index();
            $table->string('title')->nullable();
            $table->string('category_name', 120)->nullable();
            $table->longText('description')->nullable();
            $table->string('relative_path', 500)->index();
            $table->string('original_file_name')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime_type', 180)->nullable();
            $table->longText('text_content')->nullable();
            $table->timestamp('text_extracted_at')->nullable();
            $table->text('text_extraction_error')->nullable();
            $table->timestamps();
        });
    }

    private function createDifficultQuestionsTable(): void
    {
        Schema::dropIfExists('pertanyaan_sulit');
        Schema::create('pertanyaan_sulit', function (Blueprint $table): void {
            $table->id();
            $table->string('asker_name')->nullable();
            $table->string('asker_whatsapp')->nullable();
            $table->text('question');
            $table->string('password_hash')->nullable();
            $table->string('password_lookup_hash')->nullable()->index();
            $table->string('status', 30)->default('pending');
            $table->text('answer')->nullable();
            $table->string('answered_by_username')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
        });
    }

    private function enableMaintenanceMode(): void
    {
        if (! Schema::hasTable('konfigurasi')) {
            $this->createConfigTable();
        }

        DB::table('konfigurasi')->updateOrInsert(
            ['key' => 'maintenance_mode'],
            [
                'value' => '1',
                'updated_by' => 'test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        AppConfigService::clearCache();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedUser(string $username, string $scope, array $overrides = []): int
    {
        return DB::table('users')->insertGetId(array_merge([
            'username' => $username,
            'password' => 'secret-test',
            'branch_id' => $scope === 'pemuridan_cabang' ? 1 : null,
            'access_scope' => $scope,
            'is_active' => true,
            'last_login_at' => null,
            'created_at' => '2026-06-14 08:00:00',
            'updated_at' => '2026-06-14 08:00:00',
        ], $overrides));
    }

    private function seedMaterialFile(): int
    {
        $folder = public_material_folder_full_path(PublicMaterialMenuKey::MateriDg1->folder());
        File::ensureDirectoryExists($folder);
        File::put($folder.'/maintenance-test.pdf', 'Maintenance material');

        return DB::table('materi_publik')->insertGetId([
            'menu' => PublicMaterialMenuKey::MateriDg1->value,
            'title' => 'Maintenance Test',
            'relative_path' => public_material_folder_relative_path(PublicMaterialMenuKey::MateriDg1->folder()).'/maintenance-test.pdf',
            'original_file_name' => 'Maintenance Test.pdf',
            'size_bytes' => 1024,
            'mime_type' => 'application/pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function deleteTestUploadFolders(): void
    {
        File::deleteDirectory(storage_path('app/public/'.$this->testBasePath));
    }
}
