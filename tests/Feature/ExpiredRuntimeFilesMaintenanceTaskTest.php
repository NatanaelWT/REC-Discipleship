<?php

namespace Tests\Feature;

use App\Services\Maintenance\ExpiredRuntimeFilesMaintenanceTask;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExpiredRuntimeFilesMaintenanceTaskTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing-runtime-files-'.bin2hex(random_bytes(4)));
        config([
            'maintenance.runtime_root' => $this->root,
            'maintenance.compiled_view_retention_days' => 7,
            'session.lifetime' => 120,
        ]);
        File::ensureDirectoryExists($this->root.'/sessions');
        File::ensureDirectoryExists($this->root.'/cache/data/aa/bb');
        File::ensureDirectoryExists($this->root.'/views');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_it_only_removes_expired_runtime_files_and_is_resumable(): void
    {
        $expiredSession = $this->root.'/sessions/expired';
        $freshSession = $this->root.'/sessions/fresh';
        $expiredCache = $this->root.'/cache/data/aa/bb/expired';
        $foreverCache = $this->root.'/cache/data/aa/bb/forever';
        $expiredView = $this->root.'/views/expired.php';
        File::put($expiredSession, 'session');
        File::put($freshSession, 'session');
        File::put($expiredCache, str_pad((string) (time() - 60), 10, '0', STR_PAD_LEFT).'payload');
        File::put($foreverCache, '9999999999payload');
        File::put($expiredView, '<?php');
        touch($expiredSession, time() - 10_000);
        touch($expiredView, time() - 8 * 86400);

        $task = app(ExpiredRuntimeFilesMaintenanceTask::class);
        $this->assertSame(3, $task->preview()['expired_files']);

        $cursor = [];
        do {
            $step = $task->run($cursor, 1, microtime(true) + 2);
            $cursor = $step['cursor'];
        } while (! $step['complete']);

        $this->assertSame(3, $step['summary']['deleted_files']);
        $this->assertFileDoesNotExist($expiredSession);
        $this->assertFileDoesNotExist($expiredCache);
        $this->assertFileDoesNotExist($expiredView);
        $this->assertFileExists($freshSession);
        $this->assertFileExists($foreverCache);
    }

    public function test_it_refuses_to_delete_from_a_runtime_root_outside_storage_framework(): void
    {
        $unsafeRoot = storage_path('testing-runtime-outside-framework-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($unsafeRoot.'/sessions');
        $expired = $unsafeRoot.'/sessions/must-stay';
        File::put($expired, 'session');
        touch($expired, time() - 10_000);
        config(['maintenance.runtime_root' => $unsafeRoot]);

        try {
            $task = app(ExpiredRuntimeFilesMaintenanceTask::class);
            $this->assertFalse($task->preview()['runtime_root_valid']);
            $step = $task->run([], 100, microtime(true) + 1);
            $this->assertTrue($step['complete']);
            $this->assertSame(0, $step['summary']['deleted_files']);
            $this->assertFileExists($expired);
        } finally {
            File::deleteDirectory($unsafeRoot);
        }
    }
}
