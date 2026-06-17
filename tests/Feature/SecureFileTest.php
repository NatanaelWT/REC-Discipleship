<?php

namespace Tests\Feature;

use App\Support\RuntimeBootstrap;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SecureFileTest extends TestCase
{
    private string $testUploadDirectory;

    private string $imagePath = 'uploads/secure-test/preview.png';

    private string $textPath = 'uploads/secure-test/readme.txt';

    private string $blockedPath = 'uploads/secure-test/script.php';

    private string $legacyTextPath = 'uploads/secure-test-legacy/readme.txt';

    protected function setUp(): void
    {
        parent::setUp();

        RuntimeBootstrap::load();

        $this->testUploadDirectory = rec_runtime_path('uploads/secure-test');
        File::ensureDirectoryExists($this->testUploadDirectory);
        File::put(
            rec_runtime_path($this->imagePath),
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=') ?: '',
        );
        File::put(rec_runtime_path($this->textPath), "Secure file text\n");
        File::put(rec_runtime_path($this->blockedPath), '<?php echo "blocked";');
        File::ensureDirectoryExists(public_path('uploads/secure-test-legacy'));
        File::put(public_path($this->legacyTextPath), "Legacy public upload text\n");
    }

    protected function tearDown(): void
    {
        if ($this->testUploadDirectory !== '') {
            File::deleteDirectory($this->testUploadDirectory);
        }
        File::deleteDirectory(public_path('uploads/secure-test-legacy'));

        parent::tearDown();
    }

    public function test_legacy_secure_file_query_redirects_to_clean_route(): void
    {
        $response = $this->get('/index.php?page=secure_file&path=' . rawurlencode($this->imagePath));

        $response->assertRedirect('/file-aman?path=uploads%2Fsecure-test%2Fpreview.png');
    }

    public function test_secure_file_preview_renders_same_preview_shell_for_top_level_navigation(): void
    {
        $response = $this
            ->withHeaders(['Sec-Fetch-Dest' => 'document'])
            ->get('/file-aman?path=' . rawurlencode($this->imagePath));

        $response->assertOk();
        $response->assertSee('Preview File');
        $response->assertSee('file-view-image-wrap');
        $response->assertSee('Unduh');
        $response->assertSee('/file-aman?path=uploads%2Fsecure-test%2Fpreview.png&amp;raw=1', false);
    }

    public function test_secure_file_raw_streams_inline_file(): void
    {
        $response = $this->get('/file-aman?path=' . rawurlencode($this->textPath) . '&raw=1');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Secure file text', $response->streamedContent());
    }

    public function test_secure_file_streams_legacy_public_upload_file(): void
    {
        $response = $this->get('/file-aman?path=' . rawurlencode($this->legacyTextPath) . '&raw=1');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->assertStringContainsString('Legacy public upload text', $response->streamedContent());
    }

    public function test_secure_file_download_streams_attachment(): void
    {
        $response = $this->get('/file-aman?path=' . rawurlencode($this->imagePath) . '&download=1&name=Foto Pertemuan.png');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Foto_Pertemuan.png', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_secure_file_rejects_paths_outside_uploads(): void
    {
        $response = $this->get('/file-aman?path=' . rawurlencode('templates/file.txt'));

        $response->assertNotFound();
        $response->assertSee('File tidak ditemukan.');
    }

    public function test_secure_file_rejects_disallowed_extensions(): void
    {
        $response = $this->get('/file-aman?path=' . rawurlencode($this->blockedPath));

        $response->assertForbidden();
        $response->assertSee('Tipe file tidak diizinkan.');
    }
}
