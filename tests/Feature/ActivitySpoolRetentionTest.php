<?php

namespace Tests\Feature;

use App\Services\Activity\ActivitySpool;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ActivitySpoolRetentionTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = 'testing-activity-spool-'.bin2hex(random_bytes(5));
        config([
            'activity.spool.directory' => $this->directory,
            'activity.retention_days' => 90,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/private/'.$this->directory));

        parent::tearDown();
    }

    public function test_capacity_limit_never_deletes_spool_files_inside_retention(): void
    {
        $directory = storage_path('app/private/'.$this->directory);
        File::ensureDirectoryExists($directory);
        $older = $directory.'/'.now('UTC')->subHours(2)->format('Y-m-d-H').'.jsonl';
        $newer = $directory.'/'.now('UTC')->subHour()->format('Y-m-d-H').'.jsonl';
        File::put($older, str_repeat('a', 80));
        File::put($newer, str_repeat('b', 80));
        config(['activity.spool.max_bytes' => 180]);

        $this->assertFalse(app(ActivitySpool::class)->append(['id' => 'would-overflow']));
        $this->assertFileExists($older);
        $this->assertFileExists($newer);
    }

    public function test_capacity_can_reclaim_only_files_older_than_retention(): void
    {
        $directory = storage_path('app/private/'.$this->directory);
        File::ensureDirectoryExists($directory);
        $expired = $directory.'/'.now('UTC')->subDays(91)->format('Y-m-d-H').'.jsonl';
        File::put($expired, str_repeat('x', 500));
        config(['activity.spool.max_bytes' => 550]);

        $this->assertTrue(app(ActivitySpool::class)->append(['id' => 'retained-request']));
        $this->assertFileDoesNotExist($expired);
        $this->assertSame(1, app(ActivitySpool::class)->lineCount());
    }
}
