<?php

namespace Tests\Feature;

use App\Enums\PublicMaterialMenuKey;
use App\Services\PublicMaterials\PublicMaterialTextExtractor;
use App\Support\RuntimeBootstrap;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicMaterialManagementTest extends TestCase
{
    private string $testBasePath = 'msk-dg-test-public-materials';

    protected function setUp(): void
    {
        parent::setUp();

        RuntimeBootstrap::load();
        config(['public_materials.base_path' => $this->testBasePath]);
        $this->createMaterialTables();
        $this->deleteTestUploadFolders();
    }

    protected function tearDown(): void
    {
        $this->deleteTestUploadFolders();
        config(['public_materials.base_path' => 'msk-dg']);

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
        $this->insertMaterialFile('church_file_sort_10', '10 Kesimpulan Injil', 'file_sort_10.pdf');
        $this->insertMaterialFile('church_file_sort_01', '01 Injil Bagi Orang Kristen', 'file_sort_01.pdf');
        $this->insertMaterialFile('church_file_sort_02', '02 Dampak Injil Dalam Kehidupan', 'file_sort_02.pdf');

        $response = $this->get('/materi/materi_dg_1');

        $response->assertOk();
        $response->assertSeeInOrder([
            '01 Injil Bagi Orang Kristen',
            '02 Dampak Injil Dalam Kehidupan',
            '10 Kesimpulan Injil',
        ]);
    }

    public function test_developer_can_upload_public_material_file(): void
    {
        $this->loginAsMaterialManager();

        $response = $this->post('/materi/materi_dg_1/upload', [
            'title' => 'Materi Baru Pusat',
            'material_file' => UploadedFile::fake()->create('materi-baru.pdf', 12, 'application/pdf'),
        ]);

        $response->assertRedirect('/materi/materi_dg_1?material_status=uploaded');

        $row = DB::table('public_material_files')->where('title', 'Materi Baru Pusat')->first();
        $this->assertNotNull($row);
        $this->assertSame(PublicMaterialMenuKey::MateriDg1->value, $row->menu);
        $this->assertSame('Materi Baru Pusat.pdf', $row->original_file_name);
        $this->assertStringStartsWith($this->test_relative_folder(PublicMaterialMenuKey::MateriDg1).'/', (string) $row->relative_path);
        $this->assertFileExists(storage_path('app/public/'.(string) $row->relative_path));
        $this->assertFileDoesNotExist(rec_runtime_path((string) $row->relative_path));
    }

    public function test_public_material_preview_streams_file_from_public_storage(): void
    {
        $fileName = 'file_public_only_20260617000000.pdf';
        $this->putPublicMaterialFile(PublicMaterialMenuKey::MateriDg1, $fileName, 'Public material');

        $fileId = $this->insertMaterialFile('church_file_public_only', 'File Public Only', $fileName);

        $response = $this->get("/materi/materi_dg_1/{$fileId}/preview?raw=1");

        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', (string) $response->headers->get('Content-Type'));
    }

    public function test_dg1_pdf_preview_renders_stored_text_when_available(): void
    {
        $fileName = 'file_text_preview_20260617000000.pdf';
        $this->putPublicMaterialFile(PublicMaterialMenuKey::MateriDg1, $fileName, 'Public material');

        $fileId = $this->insertMaterialFile(
            'church_file_text_preview',
            'File Text Preview',
            $fileName,
            PublicMaterialMenuKey::MateriDg1,
            "Baris pertama materi.\nBaris kedua materi.",
        );

        $response = $this->get("/materi/materi_dg_1/{$fileId}/preview");

        $response->assertOk();
        $response->assertSee('Baris pertama materi.');
        $response->assertSee('Baris kedua materi.');
        $response->assertSee('Unduh PDF');
        $response->assertDontSee('data-public-material-pdf-viewer', false);
        $response->assertDontSee('public-material-preview-embed', false);
    }

    public function test_dg1_pdf_raw_preview_still_streams_pdf_when_text_exists(): void
    {
        $fileName = 'file_text_raw_20260617000000.pdf';
        $this->putPublicMaterialFile(PublicMaterialMenuKey::MateriDg1, $fileName, 'Public material');

        $fileId = $this->insertMaterialFile(
            'church_file_text_raw',
            'File Text Raw',
            $fileName,
            PublicMaterialMenuKey::MateriDg1,
            'Stored text should not replace raw PDF.',
        );

        $response = $this->get("/materi/materi_dg_1/{$fileId}/preview?raw=1");

        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_public_material_download_still_streams_pdf_attachment(): void
    {
        $fileName = 'file_download_20260617000000.pdf';
        $this->putPublicMaterialFile(PublicMaterialMenuKey::MateriDg1, $fileName, 'Public material');

        $fileId = $this->insertMaterialFile(
            'church_file_download',
            'File Download',
            $fileName,
            PublicMaterialMenuKey::MateriDg1,
            'Stored text for preview.',
        );

        $response = $this->get("/materi/materi_dg_1/{$fileId}/download");

        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_dg1_pdf_preview_falls_back_to_pdf_viewer_without_text(): void
    {
        $fileName = 'file_text_missing_20260617000000.pdf';
        $this->putPublicMaterialFile(PublicMaterialMenuKey::MateriDg1, $fileName, 'Public material');

        $fileId = $this->insertMaterialFile('church_file_text_missing', 'File Text Missing', $fileName);

        $response = $this->get("/materi/materi_dg_1/{$fileId}/preview");

        $response->assertOk();
        $response->assertSee('data-public-material-pdf-viewer', false);
        $response->assertSee('public-material-preview-embed', false);
    }

    public function test_dg2_pdf_preview_keeps_existing_pdf_viewer_even_when_text_exists(): void
    {
        $fileName = 'file_dg2_text_20260617000000.pdf';
        $this->putPublicMaterialFile(PublicMaterialMenuKey::MateriDg2, $fileName, 'DG2 material');

        $fileId = $this->insertMaterialFile(
            'church_file_dg2_text',
            'DG2 Text File',
            $fileName,
            PublicMaterialMenuKey::MateriDg2,
            'DG2 text should not render here.',
        );

        $response = $this->get("/materi/materi_dg_2/{$fileId}/preview");

        $response->assertOk();
        $response->assertSee('data-public-material-pdf-viewer', false);
        $response->assertDontSee('DG2 text should not render here.');
    }

    public function test_public_material_preview_rejects_file_from_wrong_menu(): void
    {
        $fileName = 'file_wrong_menu_20260617000000.pdf';
        $this->putPublicMaterialFile(PublicMaterialMenuKey::MateriDg2, $fileName, 'Wrong menu material');

        $fileId = $this->insertMaterialFile(
            'church_file_wrong_menu',
            'Wrong Menu File',
            $fileName,
            PublicMaterialMenuKey::MateriDg2,
        );

        $response = $this->get("/materi/materi_dg_1/{$fileId}/preview?raw=1");

        $response->assertNotFound();
    }

    public function test_materials_audit_reports_missing_registered_files(): void
    {
        $this->insertMaterialFile('church_file_missing', 'Missing File', 'file_missing_20260617000000.pdf');

        $exitCode = Artisan::call('materials:audit-files');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('File fisik hilang', $output);
        $this->assertStringContainsString($this->test_relative_folder(PublicMaterialMenuKey::MateriDg1).'/file_missing_20260617000000.pdf', $output);
    }

    public function test_materials_audit_reports_unregistered_public_files(): void
    {
        $fileName = 'file_unregistered_20260617000000.pdf';
        $this->putPublicMaterialFile(PublicMaterialMenuKey::MateriDg1, $fileName, 'Unregistered material');

        $exitCode = Artisan::call('materials:audit-files');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('File fisik belum terdaftar', $output);
        $this->assertStringContainsString($this->test_relative_folder(PublicMaterialMenuKey::MateriDg1).'/'.$fileName, $output);
    }

    public function test_materials_sync_adds_enum_folder_files_only(): void
    {
        $fileName = 'file_sync_20260617000000.pdf';
        $this->putPublicMaterialFile(PublicMaterialMenuKey::MateriDg1, $fileName, 'Sync material');

        $ignoredDir = storage_path('app/public/'.$this->testBasePath.'/Materi-MSK');
        File::ensureDirectoryExists($ignoredDir);
        File::put($ignoredDir.'/file_ignored_20260617000000.pdf', 'Ignored material');

        $exitCode = Artisan::call('materials:sync-files');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('public_material_files', [
            'menu' => PublicMaterialMenuKey::MateriDg1->value,
            'relative_path' => $this->test_relative_folder(PublicMaterialMenuKey::MateriDg1).'/'.$fileName,
        ]);
        $this->assertDatabaseMissing('public_material_files', [
            'relative_path' => $this->testBasePath.'/Materi-MSK/file_ignored_20260617000000.pdf',
        ]);
    }

    public function test_materials_extract_text_command_backfills_dg1_pdf_text(): void
    {
        $this->app->instance(
            PublicMaterialTextExtractor::class,
            new FakePublicMaterialTextExtractor('Teks hasil ekstraksi.'),
        );

        $fileName = 'file_backfill_20260617000000.pdf';
        $this->putPublicMaterialFile(PublicMaterialMenuKey::MateriDg1, $fileName, 'Backfill material');

        $fileId = $this->insertMaterialFile('church_file_backfill', 'Backfill File', $fileName);

        $exitCode = Artisan::call('materials:extract-text', [
            '--menu' => 'materi_dg_1',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('public_material_files', [
            'id' => $fileId,
            'text_content' => 'Teks hasil ekstraksi.',
            'text_extraction_error' => null,
        ]);
        $this->assertNotNull(DB::table('public_material_files')->where('id', $fileId)->value('text_extracted_at'));
    }

    public function test_developer_can_rename_public_material_file(): void
    {
        $this->loginAsMaterialManager();
        $fileId = $this->seedExistingMaterialFile();

        $response = $this->post("/materi/materi_dg_1/{$fileId}/rename", [
            'title' => 'Nama File Baru.pdf',
        ]);

        $response->assertRedirect('/materi/materi_dg_1?material_status=renamed');
        $this->assertDatabaseHas('public_material_files', [
            'id' => $fileId,
            'menu' => PublicMaterialMenuKey::MateriDg1->value,
            'title' => 'Nama File Baru',
            'original_file_name' => 'Nama File Baru.pdf',
        ]);
    }

    public function test_branch_user_cannot_upload_public_material_file(): void
    {
        $this->actingAsRecUser('admin_cabang');

        $response = $this->post('/materi/materi_dg_1/upload', [
            'title' => 'Tidak Boleh',
            'material_file' => UploadedFile::fake()->create('tidak-boleh.pdf', 12, 'application/pdf'),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('public_material_files', [
            'title' => 'Tidak Boleh',
        ]);
    }

    public function test_central_discipleship_user_cannot_upload_public_material_file(): void
    {
        $this->actingAsRecUser('recpusat', null, 'pemuridan_pusat');

        $this->post('/materi/materi_dg_1/upload', [
            'title' => 'Tidak Boleh Pusat',
            'material_file' => UploadedFile::fake()->create('tidak-boleh-pusat.pdf', 12, 'application/pdf'),
        ])->assertForbidden();

        $this->assertDatabaseMissing('public_material_files', [
            'title' => 'Tidak Boleh Pusat',
        ]);
    }

    private function createMaterialTables(): void
    {
        Schema::dropIfExists('public_material_files');

        Schema::create('public_material_files', function (Blueprint $table): void {
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

    private function seedExistingMaterialFile(): int
    {
        $fileName = 'file_existing_20260617000000.pdf';
        $this->putPublicMaterialFile(PublicMaterialMenuKey::MateriDg1, $fileName, 'Existing material');

        return $this->insertMaterialFileWithPath(
            'church_file_test',
            'Nama Lama',
            $this->test_relative_folder(PublicMaterialMenuKey::MateriDg1).'/'.$fileName,
            'Nama Lama.pdf',
            PublicMaterialMenuKey::MateriDg1,
        );
    }

    private function insertMaterialFile(
        string $publicId,
        string $title,
        string $fileName,
        PublicMaterialMenuKey $menu = PublicMaterialMenuKey::MateriDg1,
        ?string $textContent = null,
    ): int {
        return $this->insertMaterialFileWithPath(
            $publicId,
            $title,
            $this->test_relative_folder($menu).'/'.$fileName,
            $title.'.pdf',
            $menu,
            $textContent,
        );
    }

    private function insertMaterialFileWithPath(
        string $publicId,
        string $title,
        string $relativePath,
        string $originalFileName,
        PublicMaterialMenuKey $menu,
        ?string $textContent = null,
    ): int {
        $values = [
            'menu' => $menu->value,
            'title' => $title,
            'relative_path' => $relativePath,
            'original_file_name' => $originalFileName,
            'size_bytes' => 1024,
            'mime_type' => 'application/pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($textContent !== null) {
            $values['text_content'] = $textContent;
            $values['text_extracted_at'] = now();
            $values['text_extraction_error'] = null;
        }

        return DB::table('public_material_files')->insertGetId($values);
    }

    private function putPublicMaterialFile(PublicMaterialMenuKey $menu, string $fileName, string $contents): void
    {
        $fullDir = public_material_folder_full_path($menu->folder());
        File::ensureDirectoryExists($fullDir);
        File::put($fullDir.'/'.$fileName, $contents);
    }

    private function test_relative_folder(PublicMaterialMenuKey $menu): string
    {
        return public_material_folder_relative_path($menu->folder());
    }

    private function loginAsMaterialManager(): void
    {
        $this->actingAsRecUser('developer', null, 'developer');
    }

    private function deleteTestUploadFolders(): void
    {
        File::deleteDirectory(storage_path('app/public/'.$this->testBasePath));
    }
}

class FakePublicMaterialTextExtractor extends PublicMaterialTextExtractor
{
    public function __construct(private readonly string $textContent) {}

    public function extractForStorage(PublicMaterialMenuKey $menu, string $fullPath): array
    {
        return [
            'text_content' => $this->textContent,
            'text_extracted_at' => now(),
            'text_extraction_error' => null,
        ];
    }
}
