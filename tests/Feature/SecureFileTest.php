<?php

namespace Tests\Feature;

use App\Support\RuntimeBootstrap;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
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
        $this->actingAsRecUser('developer', null, 'developer');

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
        if (File::isDirectory(public_path('uploads')) && count(File::files(public_path('uploads'))) === 0 && count(File::directories(public_path('uploads'))) === 0) {
            File::deleteDirectory(public_path('uploads'));
        }

        parent::tearDown();
    }

    public function test_legacy_secure_file_query_is_rejected(): void
    {
        $response = $this->get('/index.php?page=secure_file&path='.rawurlencode($this->imagePath));

        $response->assertNotFound();
    }

    public function test_secure_file_preview_renders_same_preview_shell_for_top_level_navigation(): void
    {
        $response = $this
            ->withHeaders(['Sec-Fetch-Dest' => 'document'])
            ->get($this->secureUrl($this->imagePath));

        $response->assertOk();
        $response->assertSee('Preview File');
        $response->assertSee('file-view-image-wrap');
        $response->assertSee('Unduh');
        $response->assertSee('raw=1', false);
        $response->assertSee('signature=', false);
    }

    public function test_secure_file_raw_streams_inline_file(): void
    {
        $response = $this->get($this->secureUrl($this->textPath, ['raw' => '1']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Secure file text', $response->streamedContent());
    }

    public function test_secure_file_streams_legacy_public_upload_file(): void
    {
        $response = $this->get($this->secureUrl($this->legacyTextPath, ['raw' => '1']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->assertStringContainsString('Legacy public upload text', $response->streamedContent());
    }

    public function test_secure_file_download_streams_attachment(): void
    {
        $response = $this->get($this->secureUrl($this->imagePath, [
            'download' => '1',
            'name' => 'Foto Pertemuan.png',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Foto_Pertemuan.png', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_secure_file_supports_bounded_range_requests_without_public_caching(): void
    {
        $response = $this
            ->withHeaders(['Range' => 'bytes=0-5'])
            ->get($this->secureUrl($this->textPath, ['raw' => '1']));

        $response->assertStatus(206);
        $this->assertSame('bytes 0-5/17', $response->headers->get('Content-Range'));
        $this->assertSame('6', $response->headers->get('Content-Length'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_secure_file_rejects_paths_outside_uploads(): void
    {
        $response = $this->get($this->secureUrl('templates/file.txt'));

        $response->assertNotFound();
        $response->assertSee('File tidak ditemukan.');
    }

    public function test_secure_file_rejects_disallowed_extensions(): void
    {
        $response = $this->get($this->secureUrl($this->blockedPath));

        $response->assertForbidden();
        $response->assertSee('Tipe file tidak diizinkan.');
    }

    public function test_secure_file_requires_authentication_and_rejects_a_link_signed_for_another_user(): void
    {
        $url = $this->secureUrl($this->textPath, ['raw' => '1']);

        auth()->logout();
        $this->get($url)->assertRedirect(route('auth.login'));

        $this->actingAsRecUser('other-developer', null, 'developer');
        $this->get($url)->assertForbidden();
    }

    public function test_secure_file_rejects_a_tampered_signature(): void
    {
        $url = $this->secureUrl($this->textPath, ['raw' => '1']);
        $tampered = str_replace('readme.txt', 'preview.png', $url);

        $this->get($tampered)->assertForbidden();
    }

    /** @param array<string, string> $parameters */
    private function secureUrl(string $path, array $parameters = []): string
    {
        $viewerId = auth()->id();
        $parameters = array_merge([
            'path' => $path,
            'viewer' => hash_hmac('sha256', implode('|', [
                (string) $viewerId,
                (string) auth()->user()?->username,
                (string) auth()->user()?->access_scope,
                (string) auth()->user()?->branch_id,
            ]), (string) config('app.key')),
        ], $parameters);

        return URL::temporarySignedRoute('secure-file.show', now()->addMinutes(5), $parameters, false);
    }
}
