<?php

namespace Tests\Feature;

use App\Services\Activity\ActivityContext;
use App\Services\Maintenance\MediaInventoryMaintenanceTask;
use App\Services\Media\MediaInventoryService;
use App\Services\Media\MediaVariantManifestImporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MediaInventoryServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/media-inventory-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->root.'/uploads/peserta');
        config([
            'media.private_root' => $this->root,
            'media.orphan_grace_hours' => 1,
        ]);

        Schema::create('orang', function (Blueprint $table): void {
            $table->id();
            $table->json('photos')->nullable();
        });
        Schema::create('jurnal_temu_dg', function (Blueprint $table): void {
            $table->id();
            $table->json('photos')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('jurnal_temu_dg');
        Schema::dropIfExists('orang');
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_it_reports_missing_files_and_quarantines_only_old_orphans(): void
    {
        $referencedPath = 'uploads/peserta/referenced.jpg';
        $missingPath = 'uploads/peserta/missing.jpg';
        $orphanPath = 'uploads/peserta/orphan.jpg';
        file_put_contents($this->root.'/'.$referencedPath, 'referenced');
        file_put_contents($this->root.'/'.$orphanPath, 'orphan');
        touch($this->root.'/'.$orphanPath, time() - 7200);

        DB::table('orang')->insert([
            'photos' => json_encode([
                ['path' => $referencedPath, 'name' => 'Ada'],
                ['path' => $missingPath, 'name' => 'Hilang'],
            ], JSON_THROW_ON_ERROR),
        ]);

        $service = app(MediaInventoryService::class);
        $scan = $service->scan();
        $this->assertContains($missingPath, $scan['missing']);
        $this->assertContains($orphanPath, $scan['orphans']);
        $this->assertNotContains($referencedPath, $scan['orphans']);

        $quarantinedPath = $service->quarantine($orphanPath);
        $this->assertNotNull($quarantinedPath);
        $this->assertFileDoesNotExist($this->root.'/'.$orphanPath);
        $this->assertFileExists($this->root.'/'.$quarantinedPath);
        $this->assertFileExists($this->root.'/quarantine/media/manifest.jsonl');
        $this->assertNull($service->quarantine($referencedPath));

        $this->assertCount(1, $service->quarantineEntries());
        $this->assertSame($orphanPath, $service->restoreFromQuarantine((string) $quarantinedPath));
        $this->assertFileExists($this->root.'/'.$orphanPath);
        $this->assertFileDoesNotExist($this->root.'/'.$quarantinedPath);
        $this->assertSame([], $service->quarantineEntries());

        config(['media.quarantine_days' => -1]);
        $deletablePath = 'uploads/peserta/delete-after-review.jpg';
        file_put_contents($this->root.'/'.$deletablePath, 'reviewed orphan');
        touch($this->root.'/'.$deletablePath, time() - 7200);
        $deletableQuarantine = $service->quarantine($deletablePath);
        $this->assertNotNull($deletableQuarantine);
        $this->assertTrue($service->deleteFromQuarantine((string) $deletableQuarantine));
        $this->assertFileDoesNotExist($this->root.'/'.(string) $deletableQuarantine);
        $this->assertSame([], $service->quarantineEntries());
    }

    public function test_restore_and_delete_fail_closed_when_quarantined_bytes_change(): void
    {
        config(['media.quarantine_days' => -1]);
        $source = 'uploads/peserta/checksum-guard.jpg';
        file_put_contents($this->root.'/'.$source, 'original bytes');
        touch($this->root.'/'.$source, time() - 7200);

        $service = app(MediaInventoryService::class);
        $quarantine = $service->quarantine($source);
        $this->assertNotNull($quarantine);
        file_put_contents($this->root.'/'.$quarantine, 'changed bytes');

        $this->assertNull($service->restoreFromQuarantine((string) $quarantine));
        $this->assertFalse($service->deleteFromQuarantine((string) $quarantine));
        $this->assertFileExists($this->root.'/'.$quarantine);
    }

    public function test_a_quarantine_path_that_becomes_referenced_cannot_be_moved_or_deleted(): void
    {
        config(['media.quarantine_days' => -1]);
        $source = 'uploads/peserta/reference-guard.jpg';
        file_put_contents($this->root.'/'.$source, 'guarded bytes');
        touch($this->root.'/'.$source, time() - 7200);

        $service = app(MediaInventoryService::class);
        $quarantine = $service->quarantine($source);
        $this->assertNotNull($quarantine);
        DB::table('orang')->insert([
            'photos' => json_encode([['path' => $quarantine]], JSON_THROW_ON_ERROR),
        ]);

        $this->assertNull($service->restoreFromQuarantine((string) $quarantine));
        $this->assertFalse($service->deleteFromQuarantine((string) $quarantine));
        $this->assertFileExists($this->root.'/'.$quarantine);
    }

    public function test_media_maintenance_persists_its_inventory_and_resumes_quarantine_by_index(): void
    {
        foreach (['first.jpg', 'second.jpg'] as $name) {
            file_put_contents($this->root.'/uploads/peserta/'.$name, $name);
            touch($this->root.'/uploads/peserta/'.$name, time() - 7200);
        }

        $cursor = [];
        $calls = 0;
        do {
            $step = app(MediaInventoryMaintenanceTask::class)->run($cursor, 1, microtime(true) + 1);
            $cursor = $step['cursor'];
            $calls++;
        } while (! $step['complete'] && $calls < 10);

        $this->assertTrue($step['complete']);
        $this->assertGreaterThanOrEqual(4, $calls);
        $this->assertSame(2, $step['summary']['inventory']['orphans']);
        $this->assertSame(2, $step['summary']['quarantined']);
        $this->assertSame(0, $step['summary']['remaining']);
        $this->assertFileDoesNotExist($this->root.'/uploads/peserta/first.jpg');
        $this->assertFileDoesNotExist($this->root.'/uploads/peserta/second.jpg');
        $this->assertCount(2, app(MediaInventoryService::class)->quarantineEntries());
    }

    public function test_permanent_delete_waits_for_the_audit_transaction_commit(): void
    {
        config(['media.quarantine_days' => -1]);
        $source = 'uploads/peserta/commit-gated.jpg';
        file_put_contents($this->root.'/'.$source, 'commit gated bytes');
        touch($this->root.'/'.$source, time() - 7200);
        $service = app(MediaInventoryService::class);
        $quarantine = $service->quarantine($source);
        $this->assertNotNull($quarantine);

        $activity = app(ActivityContext::class);
        $activity->activate('01KCOMMITGATED000000000000');
        $this->assertTrue($service->deleteFromQuarantine((string) $quarantine));
        $this->assertFileExists($this->root.'/'.$quarantine);
        $activity->runRollbackCallbacks();
        $activity->clear();
        $this->assertFileExists($this->root.'/'.$quarantine);

        $this->assertTrue($service->deleteFromQuarantine((string) $quarantine));
        $this->assertFileDoesNotExist($this->root.'/'.$quarantine);
    }

    public function test_variant_manifest_verifies_original_and_both_derivative_checksums(): void
    {
        $originalBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
            true,
        );
        $webpBytes = base64_decode(
            'UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA',
            true,
        );
        $this->assertIsString($originalBytes);
        $this->assertIsString($webpBytes);

        $originalHash = hash('sha256', $originalBytes);
        $original = 'uploads/peserta/original.png';
        $web = 'uploads/peserta/web/web_'.$originalHash.'.webp';
        $thumbnail = 'uploads/peserta/thumbnails/thumb_'.$originalHash.'.webp';
        File::ensureDirectoryExists(dirname($this->root.'/'.$web));
        File::ensureDirectoryExists(dirname($this->root.'/'.$thumbnail));
        File::put($this->root.'/'.$original, $originalBytes);
        File::put($this->root.'/'.$web, $webpBytes);
        File::put($this->root.'/'.$thumbnail, $webpBytes);

        $entry = [
            'original' => $original,
            'sha256' => $originalHash,
            'size' => strlen($originalBytes),
            'width' => 1,
            'height' => 1,
            'web_path' => $web,
            'web_sha256' => hash('sha256', $webpBytes),
            'web_size' => strlen($webpBytes),
            'web_width' => 1,
            'web_height' => 1,
            'web_format' => 'webp',
            'thumbnail_path' => $thumbnail,
            'thumbnail_sha256' => hash('sha256', $webpBytes),
            'thumbnail_size' => strlen($webpBytes),
            'thumbnail_width' => 1,
            'thumbnail_height' => 1,
            'thumbnail_format' => 'webp',
        ];
        File::put(
            $this->root.'/media-variants-manifest.json',
            json_encode(['files' => [$entry]], JSON_THROW_ON_ERROR),
        );

        $valid = app(MediaVariantManifestImporter::class)->validatedManifest();
        $this->assertSame(1, $valid['summary']['valid']);
        $this->assertSame(0, $valid['summary']['invalid']);
        $this->assertSame($entry['web_sha256'], $valid['updates'][$original]['web_sha256']);

        File::put($this->root.'/'.$web, 'tampered derivative');
        $invalid = app(MediaVariantManifestImporter::class)->validatedManifest();
        $this->assertSame(0, $invalid['summary']['valid']);
        $this->assertSame(1, $invalid['summary']['invalid']);
    }
}
