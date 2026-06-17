<?php

namespace Tests\Feature;

use App\Support\RuntimeBootstrap;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicMaterialManagementTest extends TestCase
{
    private string $testFolder = 'uploads/files/Material-Test/DG-1';

    protected function setUp(): void
    {
        parent::setUp();

        RuntimeBootstrap::load();
        $this->createMaterialTables();
        $this->seedMaterialMenu();
        File::deleteDirectory(rec_runtime_path('uploads/files/Material-Test'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(rec_runtime_path('uploads/files/Material-Test'));
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        parent::tearDown();
    }

    public function test_public_material_page_hides_management_forms_for_public_user(): void
    {
        $response = $this->get('/materi/materi_dg_1');

        $response->assertOk();
        $response->assertDontSee('public-material-admin-form', false);
        $response->assertDontSee('public-material-rename-form', false);
    }

    public function test_public_material_page_orders_files_by_name(): void
    {
        $this->insertMaterialFile('church_file_sort_10', '10 Kesimpulan Injil', 'file_sort_10.pdf', 2);
        $this->insertMaterialFile('church_file_sort_01', '01 Injil Bagi Orang Kristen', 'file_sort_01.pdf', 0);
        $this->insertMaterialFile('church_file_sort_02', '02 Dampak Injil Dalam Kehidupan', 'file_sort_02.pdf', 1);

        $response = $this->get('/materi/materi_dg_1');

        $response->assertOk();
        $response->assertSeeInOrder([
            '01 Injil Bagi Orang Kristen',
            '02 Dampak Injil Dalam Kehidupan',
            '10 Kesimpulan Injil',
        ]);
    }

    public function test_central_user_can_upload_public_material_file(): void
    {
        $this->loginAsCentralUser();

        $response = $this->post('/materi/materi_dg_1/upload', [
            'title' => 'Materi Baru Pusat',
            'material_file' => UploadedFile::fake()->create('materi-baru.pdf', 12, 'application/pdf'),
        ]);

        $response->assertRedirect('/materi/materi_dg_1?material_status=uploaded');

        $row = DB::table('public_material_files')->where('title', 'Materi Baru Pusat')->first();
        $this->assertNotNull($row);
        $this->assertSame('Materi Baru Pusat.pdf', $row->original_file_name);
        $this->assertStringStartsWith($this->testFolder . '/file_', (string) $row->relative_path);
        $this->assertFileExists(rec_runtime_path((string) $row->relative_path));
    }

    public function test_central_user_can_rename_public_material_file(): void
    {
        $this->loginAsCentralUser();
        $this->seedExistingMaterialFile();

        $response = $this->post('/materi/materi_dg_1/church_file_test/rename', [
            'title' => 'Nama File Baru.pdf',
        ]);

        $response->assertRedirect('/materi/materi_dg_1?material_status=renamed');
        $this->assertDatabaseHas('public_material_files', [
            'public_id' => 'church_file_test',
            'title' => 'Nama File Baru',
            'original_file_name' => 'Nama File Baru.pdf',
        ]);
    }

    public function test_branch_user_cannot_upload_public_material_file(): void
    {
        $this->startLegacySession();
        $_SESSION['user'] = 'admin_cabang';
        $_SESSION['cabang'] = 'kutisari';
        $_SESSION['access_scope'] = 'branch';

        $response = $this->post('/materi/materi_dg_1/upload', [
            'title' => 'Tidak Boleh',
            'material_file' => UploadedFile::fake()->create('tidak-boleh.pdf', 12, 'application/pdf'),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('public_material_files', [
            'title' => 'Tidak Boleh',
        ]);
    }

    private function createMaterialTables(): void
    {
        Schema::dropIfExists('public_material_files');
        Schema::dropIfExists('public_material_menus');

        Schema::create('public_material_menus', function (Blueprint $table): void {
            $table->id();
            $table->string('menu_key', 120)->unique();
            $table->string('label');
            $table->string('subtitle')->nullable();
            $table->string('folder_path', 500);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('public_material_files', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('public_material_menu_id');
            $table->string('public_id', 120)->unique();
            $table->string('title')->nullable();
            $table->string('category_name', 120)->nullable();
            $table->longText('description')->nullable();
            $table->string('relative_path', 500);
            $table->string('original_file_name')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime_type', 180)->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('branch_code', 40)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    private function seedMaterialMenu(): void
    {
        DB::table('public_material_menus')->insert([
            'id' => 1,
            'menu_key' => 'materi_dg_1',
            'label' => 'Materi DG-1 (BePI)',
            'subtitle' => 'Berpusat Pada Injil',
            'folder_path' => 'Material-Test/DG-1',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedExistingMaterialFile(): void
    {
        $fullDir = rec_runtime_path($this->testFolder);
        File::ensureDirectoryExists($fullDir);
        File::put($fullDir . '/file_existing_20260617000000.pdf', 'Existing material');

        DB::table('public_material_files')->insert([
            'public_material_menu_id' => 1,
            'public_id' => 'church_file_test',
            'title' => 'Nama Lama',
            'relative_path' => $this->testFolder . '/file_existing_20260617000000.pdf',
            'original_file_name' => 'Nama Lama.pdf',
            'size_bytes' => 17,
            'mime_type' => 'application/pdf',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertMaterialFile(string $publicId, string $title, string $fileName, int $sortOrder): void
    {
        DB::table('public_material_files')->insert([
            'public_material_menu_id' => 1,
            'public_id' => $publicId,
            'title' => $title,
            'relative_path' => $this->testFolder . '/' . $fileName,
            'original_file_name' => $title . '.pdf',
            'size_bytes' => 1024,
            'mime_type' => 'application/pdf',
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function loginAsCentralUser(): void
    {
        $this->startLegacySession();

        $_SESSION['user'] = 'recpusat';
        $_SESSION['cabang'] = 'pusat';
        $_SESSION['access_scope'] = 'central_discipleship_readonly';
    }

    private function startLegacySession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_id('public-material-test-' . str_replace('.', '', uniqid('', true)));
            session_start();
        }
    }
}
