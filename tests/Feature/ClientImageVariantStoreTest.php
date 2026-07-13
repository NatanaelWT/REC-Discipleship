<?php

namespace Tests\Feature;

use App\Services\Media\ClientImageVariantStore;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ClientImageVariantStoreTest extends TestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    /** @var array<int, string> */
    private array $createdPaths = [];

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/media-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->root);
        config(['media.private_root' => $this->root]);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdPaths as $path) {
            $absolutePath = $this->root.'/'.trim(str_replace('\\', '/', $path), '/');
            if (is_file($absolutePath)) {
                unlink($absolutePath);
            }
        }
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_it_attaches_valid_client_derivatives_and_original_metadata(): void
    {
        $bytes = base64_decode(self::PNG_1X1, true);
        $this->assertIsString($bytes);

        $originalPath = 'uploads/testing/original.png';
        $originalAbsolutePath = $this->root.'/'.$originalPath;
        $originalDirectory = dirname($originalAbsolutePath);
        if (! is_dir($originalDirectory)) {
            mkdir($originalDirectory, 0775, true);
        }
        file_put_contents($originalAbsolutePath, $bytes);
        $this->createdPaths[] = $originalPath;

        $request = Request::create('/', 'POST', [], [], [
            'web_variants' => [UploadedFile::fake()->createWithContent('web.png', $bytes)],
            'thumbnails' => [UploadedFile::fake()->createWithContent('thumb.png', $bytes)],
        ]);

        $photos = app(ClientImageVariantStore::class)->attachFromRequest(
            [['path' => $originalPath, 'name' => 'Original']],
            $request,
            'web_variants',
            'thumbnails',
            'uploads/testing',
        );

        $this->assertCount(1, $photos);
        $this->assertSame(hash('sha256', $bytes), $photos[0]['sha256']);
        $this->assertSame(1, $photos[0]['width']);
        $this->assertSame(1, $photos[0]['height']);
        $this->assertSame('ready', $photos[0]['variant_status']);
        $this->assertFileExists($this->root.'/'.$photos[0]['web_path']);
        $this->assertFileExists($this->root.'/'.$photos[0]['thumbnail_path']);

        $this->createdPaths[] = $photos[0]['web_path'];
        $this->createdPaths[] = $photos[0]['thumbnail_path'];
    }

    public function test_it_keeps_original_when_browser_does_not_send_a_variant(): void
    {
        $bytes = base64_decode(self::PNG_1X1, true);
        $this->assertIsString($bytes);

        $originalPath = 'uploads/testing/pending-original.png';
        $originalAbsolutePath = $this->root.'/'.$originalPath;
        $originalDirectory = dirname($originalAbsolutePath);
        if (! is_dir($originalDirectory)) {
            mkdir($originalDirectory, 0775, true);
        }
        file_put_contents($originalAbsolutePath, $bytes);
        $this->createdPaths[] = $originalPath;

        $photos = app(ClientImageVariantStore::class)->attachFromRequest(
            [['path' => $originalPath, 'name' => 'Original']],
            Request::create('/', 'POST'),
            'web_variants',
            'thumbnails',
            'uploads/testing',
        );

        $this->assertSame($originalPath, $photos[0]['path']);
        $this->assertSame('pending', $photos[0]['variant_status']);
        $this->assertArrayNotHasKey('web_path', $photos[0]);
    }

    public function test_it_discards_partial_derivative_arrays_instead_of_pairing_the_wrong_photo(): void
    {
        $bytes = base64_decode(self::PNG_1X1, true);
        $this->assertIsString($bytes);

        $originals = [];
        foreach (['first', 'second'] as $name) {
            $path = 'uploads/testing/'.$name.'.png';
            File::ensureDirectoryExists(dirname($this->root.'/'.$path));
            file_put_contents($this->root.'/'.$path, $bytes);
            $this->createdPaths[] = $path;
            $originals[] = ['path' => $path, 'name' => ucfirst($name)];
        }

        $request = Request::create('/', 'POST', [], [], [
            'web_variants' => [UploadedFile::fake()->createWithContent('only-first.png', $bytes)],
            'thumbnails' => [
                UploadedFile::fake()->createWithContent('first.png', $bytes),
                UploadedFile::fake()->createWithContent('second.png', $bytes),
            ],
        ]);

        $photos = app(ClientImageVariantStore::class)->attachFromRequest(
            $originals,
            $request,
            'web_variants',
            'thumbnails',
            'uploads/testing',
        );

        $this->assertSame(['pending', 'pending'], array_column($photos, 'variant_status'));
        $this->assertArrayNotHasKey('web_path', $photos[0]);
        $this->assertArrayNotHasKey('thumbnail_path', $photos[1]);
    }
}
