<?php

namespace Tests\Feature;

use App\Contracts\MaintenanceTask;
use App\Models\MaintenanceRun;
use App\Models\User;
use App\Services\Activity\ActivitySpool;
use App\Services\AppConfig\AppConfigService;
use App\Services\Maintenance\ActivityRetentionMaintenanceTask;
use App\Services\Maintenance\MaintenanceRunner;
use App\Services\Media\MediaInventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeveloperMaintenanceTest extends TestCase
{
    private User $developer;

    private string $mediaRoot;

    private string $spoolDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-13 12:00:00', 'UTC'));
        $this->mediaRoot = storage_path('framework/testing/developer-maintenance-media-'.bin2hex(random_bytes(5)));
        $this->spoolDirectory = 'testing-maintenance-spool-'.bin2hex(random_bytes(5));
        File::ensureDirectoryExists($this->mediaRoot.'/uploads/peserta');
        config([
            'activity.storage' => 'split',
            'activity.enabled' => true,
            'analytics.enabled' => false,
            'activity.retention_days' => 90,
            'activity.maintenance.batch_size' => 2,
            'activity.maintenance.batch_seconds' => 2,
            'activity.spool.directory' => $this->spoolDirectory,
            'maintenance.tasks' => [ActivityRetentionMaintenanceTask::class],
            'media.private_root' => $this->mediaRoot,
            'media.orphan_grace_hours' => 1,
        ]);
        $this->createCoreTables();
        $migration = require database_path('migrations/2026_07_13_100000_create_retained_activity_storage.php');
        $migration->up();
        $this->developer = User::query()->create([
            'username' => 'maintenance_developer',
            'password' => Hash::make('secret-maintenance'),
            'branch_id' => null,
            'access_scope' => 'developer',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        AppConfigService::clearCache();
        File::deleteDirectory($this->mediaRoot);
        File::deleteDirectory(storage_path('app/private/'.$this->spoolDirectory));
        parent::tearDown();
    }

    public function test_only_password_confirmed_developer_can_start_a_run(): void
    {
        $this->actingAs($this->developer)
            ->get('/developer/maintenance')
            ->assertOk()
            ->assertSee('Maintenance Data')
            ->assertSee('Dry-run otomatis')
            ->assertSee('name="_token"', false);

        $this->post('/developer/maintenance', [
            'current_password' => 'salah',
            'idempotency_key' => (string) Str::uuid(),
            'dry_run' => '1',
        ])->assertRedirect('/developer/maintenance?error=password_invalid');
        $this->assertSame(0, MaintenanceRun::query()->count());

        $this->post('/developer/maintenance', [
            'current_password' => 'secret-maintenance',
            'idempotency_key' => str_replace('-', '', (string) Str::uuid()),
            'dry_run' => '1',
        ])->assertRedirect('/developer/maintenance?status=started');
        $this->assertDatabaseHas('maintenance_runs', [
            'requested_by_username' => 'maintenance_developer',
            'status' => 'pending',
            'dry_run' => true,
        ]);
    }

    public function test_maintenance_routes_reject_guests_and_non_developers(): void
    {
        $this->get('/developer/maintenance')->assertRedirect('/login');

        $branchUser = User::query()->create([
            'username' => 'maintenance_branch',
            'password' => Hash::make('branch-secret'),
            'branch_id' => null,
            'access_scope' => 'pemuridan_cabang',
            'is_active' => true,
        ]);
        $this->actingAs($branchUser)
            ->get('/developer/maintenance')
            ->assertRedirect();
        $this->post('/developer/maintenance', [
            'current_password' => 'branch-secret',
            'idempotency_key' => str_replace('-', '', (string) Str::uuid()),
            'dry_run' => '1',
        ])->assertRedirect();

        $this->assertSame(0, MaintenanceRun::query()->count());
    }

    public function test_active_run_mode_cannot_be_switched_and_confirmation_is_bound_to_the_user(): void
    {
        $this->actingAs($this->developer)->post('/developer/maintenance', [
            'current_password' => 'secret-maintenance',
            'idempotency_key' => str_replace('-', '', (string) Str::uuid()),
            'dry_run' => '1',
        ])->assertRedirect('/developer/maintenance?status=started');
        $run = MaintenanceRun::query()->firstOrFail();

        $this->enableMaintenanceMode();
        $this->post('/developer/maintenance', [
            'current_password' => 'secret-maintenance',
            'idempotency_key' => str_replace('-', '', (string) Str::uuid()),
        ])->assertRedirect('/developer/maintenance?error=run_mode_mismatch');
        $this->assertSame(1, MaintenanceRun::query()->count());

        $otherDeveloper = User::query()->create([
            'username' => 'maintenance_developer_two',
            'password' => Hash::make('other-secret'),
            'branch_id' => null,
            'access_scope' => 'developer',
            'is_active' => true,
        ]);
        $this->actingAs($otherDeveloper)
            ->postJson('/developer/maintenance/'.$run->id.'/batch')
            ->assertForbidden()
            ->assertJsonPath('message', 'Konfirmasi password telah kedaluwarsa.');
    }

    public function test_runner_clamps_batch_limits_and_honors_the_single_lock(): void
    {
        ProbeMaintenanceTask::reset();
        config([
            'maintenance.tasks' => [ProbeMaintenanceTask::class],
            'activity.maintenance.batch_size' => 0,
            'activity.maintenance.batch_seconds' => 100,
            'activity.maintenance.lock_seconds' => 1,
        ]);
        $run = $this->maintenanceRun(false);
        app(MaintenanceRunner::class)->runBatch($run);

        $this->assertSame(1, ProbeMaintenanceTask::$receivedBatchSize);
        $this->assertGreaterThan(0, ProbeMaintenanceTask::$receivedSeconds);
        $this->assertLessThanOrEqual(10.1, ProbeMaintenanceTask::$receivedSeconds);

        $lockedRun = $this->maintenanceRun(false, 'second-lock-token');
        $lock = Cache::lock('rec:developer-maintenance', 30);
        $this->assertTrue($lock->get());
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('request lain');
            app(MaintenanceRunner::class)->runBatch($lockedRun);
        } finally {
            $lock->release();
        }
    }

    public function test_dry_run_never_executes_tasks_and_reusing_its_token_is_idempotent(): void
    {
        ProbeMaintenanceTask::reset();
        config(['maintenance.tasks' => [ProbeMaintenanceTask::class]]);
        $key = str_replace('-', '', (string) Str::uuid());

        $this->actingAs($this->developer)->post('/developer/maintenance', [
            'current_password' => 'secret-maintenance',
            'idempotency_key' => $key,
            'dry_run' => '1',
        ])->assertRedirect('/developer/maintenance?status=started');
        $run = MaintenanceRun::query()->firstOrFail();
        app(MaintenanceRunner::class)->runBatch($run);

        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertSame(-1, ProbeMaintenanceTask::$receivedBatchSize);
        $this->assertSame('probe', data_get($run->summary, 'preview.0.key'));

        $this->post('/developer/maintenance', [
            'current_password' => 'secret-maintenance',
            'idempotency_key' => $key,
            'dry_run' => '1',
        ])->assertRedirect('/developer/maintenance?status=started');
        $this->assertSame(1, MaintenanceRun::query()->count());
    }

    public function test_incomplete_task_resumes_from_its_persisted_cursor_on_the_next_request(): void
    {
        ResumableProbeMaintenanceTask::reset();
        config(['maintenance.tasks' => [ResumableProbeMaintenanceTask::class]]);
        $run = $this->maintenanceRun(false);
        $runner = app(MaintenanceRunner::class);

        $first = $runner->runBatch($run);
        $this->assertSame('running', $first->status);
        $this->assertSame(1, data_get($first->cursor, 'task_cursor.step'));

        $second = $runner->runBatch($first->fresh());
        $this->assertSame('completed', $second->status);
        $this->assertSame(2, data_get($second->summary, 'resumable_probe.step'));
        $this->assertSame([0, 1], ResumableProbeMaintenanceTask::$receivedCursors);
    }

    public function test_overdue_warning_appears_only_after_more_than_seven_days(): void
    {
        $run = $this->maintenanceRun(false);
        $run->forceFill([
            'status' => 'completed',
            'completed_at' => CarbonImmutable::now('UTC')->subDays(7),
            'summary' => ['activity_retention' => [
                'legacy_validation_status' => 'passed',
                'legacy_validated_at' => CarbonImmutable::now('UTC')->subDays(7)->toIso8601String(),
            ]],
        ])->save();

        $this->actingAs($this->developer)
            ->get('/developer/maintenance')
            ->assertOk()
            ->assertDontSee('retensi tidak tertinggal lebih dari tujuh hari')
            ->assertSee('Validasi cutover legacy lulus')
            ->assertSee('pertahankan minimal tujuh hari');

        $run->forceFill(['completed_at' => CarbonImmutable::now('UTC')->subDays(7)->subSecond()])->save();
        $this->get('/developer/maintenance')
            ->assertOk()
            ->assertSee('retensi tidak tertinggal lebih dari tujuh hari');
    }

    public function test_legacy_history_is_rolled_up_but_raw_older_than_retention_is_not_copied(): void
    {
        $this->createLegacyActivityTable();
        $old = CarbonImmutable::now('UTC')->subDays(180);
        $legacyId = (string) Str::ulid();
        DB::table('aktivitas')->insert([
            'id' => $legacyId,
            'visitor_hash' => hash('sha256', 'legacy-visitor'),
            'route_name' => 'public.legacy-history',
            'path' => '/publik/legacy-history',
            'is_page_view' => true,
            'segment' => 'publik',
            'language_code' => 'id',
            'device_type' => 'desktop',
            'is_bot' => false,
            'is_prefetch' => false,
            'response_ms' => 12.5,
            'occurred_at' => $old,
            'started_at' => $old,
        ]);
        $recent = CarbonImmutable::now('UTC')->subDay();
        $recentId = (string) Str::ulid();
        DB::table('aktivitas')->insert([
            'id' => $recentId,
            'visitor_hash' => hash('sha256', 'recent-legacy-visitor'),
            'route_name' => 'developer.legacy-change',
            'path' => '/developer/legacy-change',
            'is_page_view' => false,
            'is_bot' => false,
            'is_prefetch' => false,
            'started_at' => $recent,
            'event_entries' => json_encode([[
                'category' => 'data',
                'action' => 'legacy.changed',
                'subject_type' => 'person',
                'subject_id' => '42',
                'occurred_at' => $recent->toIso8601String(),
            ]], JSON_THROW_ON_ERROR),
        ]);

        $cursor = [];
        for ($attempt = 0; $attempt < 220; $attempt++) {
            $step = app(ActivityRetentionMaintenanceTask::class)->run($cursor, 20, microtime(true) + 1);
            $cursor = $step['cursor'];
            if ($step['complete']) {
                break;
            }
        }

        $this->assertTrue($step['complete']);
        $this->assertDatabaseHas('website_daily_rollups', [
            'activity_date' => $old->setTimezone(config('app.timezone'))->format('Y-m-d'),
            'route_name' => 'public.legacy-history',
            'page_views' => 1,
        ]);
        $this->assertDatabaseMissing('request_activities', ['id' => $legacyId]);
        $this->assertDatabaseHas('request_activities', ['id' => $recentId, 'events_count' => 1]);
        $this->assertSame(1, DB::table('audit_events')->where('request_id', $recentId)->count());
        $this->assertSame('passed', $step['summary']['legacy_validation_status']);
        $this->assertSame(1, $step['summary']['validation_legacy_requests_checked']);
        $this->assertSame(0, $step['summary']['validation_missing_requests']);
        $this->assertSame(0, $step['summary']['validation_event_mismatches']);
        $this->assertSame(0, $step['summary']['validation_missing_rollup_days']);
        $this->assertSame(0, $step['summary']['validation_rollup_metric_mismatches']);
        $this->assertNotEmpty($step['summary']['legacy_validated_at']);
    }

    public function test_invalid_spool_line_is_quarantined_without_blocking_valid_replay(): void
    {
        $directory = storage_path('app/private/'.$this->spoolDirectory);
        File::ensureDirectoryExists($directory);
        $id = (string) Str::ulid();
        $valid = json_encode(['version' => 1, 'attributes' => [
            'id' => $id,
            'actor_type' => 'anonymous',
            'method' => 'GET',
            'path' => '/spooled',
            'category' => 'request',
            'action' => 'request',
            'outcome' => 'succeeded',
            'is_page_view' => false,
            'is_bot' => false,
            'is_prefetch' => false,
            'events_count' => 0,
            'started_at' => CarbonImmutable::now('UTC')->toDateTimeString(),
        ]], JSON_THROW_ON_ERROR);
        File::put($directory.'/2026-07-13-12.jsonl', "not-json\n".$valid."\n");

        $cursor = ['phase' => 'spool_replay'];
        do {
            $step = app(ActivityRetentionMaintenanceTask::class)->run($cursor, 10, microtime(true) + 2);
            $cursor = $step['cursor'];
        } while (! $step['complete']);

        $this->assertTrue($step['complete']);
        $this->assertSame(1, $step['summary']['spool_requests_replayed']);
        $this->assertSame(1, $step['summary']['spool_requests_quarantined']);
        $this->assertDatabaseHas('request_activities', ['id' => $id]);
        $this->assertNotEmpty(File::files($directory.'/invalid'));
    }

    public function test_cutover_verification_fails_before_prune_when_a_recent_legacy_request_is_missing(): void
    {
        $this->createLegacyActivityTable();
        $recent = CarbonImmutable::now('UTC')->subDay();
        DB::table('aktivitas')->insert([
            'id' => (string) Str::ulid(),
            'path' => '/legacy/missing-copy',
            'is_page_view' => false,
            'is_bot' => false,
            'is_prefetch' => false,
            'started_at' => $recent,
        ]);
        $expiredId = $this->requestRow(CarbonImmutable::now('UTC')->subDays(90)->subSecond());
        $task = app(ActivityRetentionMaintenanceTask::class);

        $requestVerification = $task->run(['phase' => 'verify_requests'], 10, microtime(true) + 2);
        $this->assertSame('verify_rollups', $requestVerification['cursor']['phase']);
        $this->assertSame(1, $requestVerification['summary']['validation_missing_requests']);
        $rollupVerification = $task->run($requestVerification['cursor'], 10, microtime(true) + 2);
        $this->assertSame('verification_finalize', $rollupVerification['cursor']['phase']);
        $this->assertSame('failed', $rollupVerification['summary']['legacy_validation_status']);

        try {
            $task->run($rollupVerification['cursor'], 10, microtime(true) + 2);
            $this->fail('Verification should stop before the prune phases.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('1 request hilang', $exception->getMessage());
        }
        $this->assertDatabaseHas('request_activities', ['id' => $expiredId]);
    }

    public function test_permanent_quarantine_delete_requires_maintenance_mode(): void
    {
        config(['media.quarantine_days' => -1]);
        $source = 'uploads/peserta/delete-gated.jpg';
        File::put($this->mediaRoot.'/'.$source, 'delete only in maintenance');
        touch($this->mediaRoot.'/'.$source, time() - 7200);
        $quarantine = app(MediaInventoryService::class)->quarantine($source);
        $this->assertNotNull($quarantine);

        $this->actingAs($this->developer)->post('/developer/maintenance/quarantine/delete', [
            'current_password' => 'secret-maintenance',
            'quarantine_path' => $quarantine,
            'confirmation' => 'HAPUS PERMANEN',
        ])->assertRedirect('/developer/maintenance?error=maintenance_required');
        $this->assertFileExists($this->mediaRoot.'/'.$quarantine);

        $this->enableMaintenanceMode();
        $this->post('/developer/maintenance/quarantine/delete', [
            'current_password' => 'secret-maintenance',
            'quarantine_path' => $quarantine,
            'confirmation' => 'HAPUS PERMANEN',
        ])->assertRedirect('/developer/maintenance?status=quarantine_deleted');
        $this->assertFileDoesNotExist($this->mediaRoot.'/'.$quarantine);
    }

    public function test_browser_batches_build_rollup_and_prune_only_rows_older_than_ninety_days(): void
    {
        $expiredId = $this->requestRow(CarbonImmutable::now('UTC')->subDays(90)->subSecond());
        $boundaryId = $this->requestRow(CarbonImmutable::now('UTC')->subDays(90));
        $pageViewId = $this->requestRow(CarbonImmutable::now('UTC')->subDay(), true);
        DB::table('audit_events')->insert([
            'id' => (string) Str::ulid(),
            'request_id' => $expiredId,
            'category' => 'data',
            'action' => 'expired.changed',
            'occurred_at' => CarbonImmutable::now('UTC')->subDays(90)->subSecond(),
        ]);
        DB::table('konfigurasi')->insert([
            'key' => 'maintenance_mode',
            'value' => '1',
            'updated_by' => 'maintenance_developer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        AppConfigService::clearCache();
        $this->actingAs($this->developer);

        $this->post('/developer/maintenance', [
            'current_password' => 'secret-maintenance',
            'idempotency_key' => str_replace('-', '', (string) Str::uuid()),
        ])->assertRedirect('/developer/maintenance?status=started');
        $run = MaintenanceRun::query()->firstOrFail();

        for ($attempt = 0; $attempt < 20 && $run->status !== 'completed'; $attempt++) {
            $this->postJson('/developer/maintenance/'.$run->id.'/batch')
                ->assertOk();
            $run->refresh();
        }

        $this->assertSame('completed', $run->status, (string) $run->error_message);
        $this->assertDatabaseMissing('request_activities', ['id' => $expiredId]);
        $this->assertDatabaseHas('request_activities', ['id' => $boundaryId]);
        $this->assertDatabaseHas('request_activities', ['id' => $pageViewId]);
        $this->assertDatabaseMissing('audit_events', ['request_id' => $expiredId]);
        $this->assertDatabaseHas('website_daily_rollups', [
            'activity_date' => CarbonImmutable::now('UTC')->subDay()->format('Y-m-d'),
            'route_name' => 'public.maintenance-test',
            'page_views' => 1,
        ]);
    }

    private function createCoreTables(): void
    {
        Schema::create('cabang', static function (Blueprint $table): void {
            $table->id();
            $table->string('label')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('username', 120)->unique();
            $table->string('password');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('access_scope', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('konfigurasi', static function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->string('updated_by', 120)->nullable();
            $table->timestamps();
        });
        Schema::create('orang', static function (Blueprint $table): void {
            $table->id();
            $table->json('photos')->nullable();
        });
        Schema::create('jurnal_temu_dg', static function (Blueprint $table): void {
            $table->id();
            $table->json('photos')->nullable();
        });
    }

    private function createLegacyActivityTable(): void
    {
        Schema::create('aktivitas', static function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->char('visitor_hash', 64)->nullable();
            $table->string('route_name')->nullable();
            $table->text('path');
            $table->boolean('is_page_view')->default(false);
            $table->string('segment')->nullable();
            $table->string('language_code')->nullable();
            $table->string('device_type')->nullable();
            $table->boolean('is_bot')->default(false);
            $table->boolean('is_prefetch')->default(false);
            $table->decimal('response_ms', 14, 3)->nullable();
            $table->dateTime('occurred_at')->nullable();
            $table->dateTime('started_at');
            $table->json('event_entries')->nullable();
        });
    }

    private function enableMaintenanceMode(): void
    {
        DB::table('konfigurasi')->updateOrInsert(
            ['key' => 'maintenance_mode'],
            [
                'value' => '1',
                'updated_by' => 'maintenance_developer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        AppConfigService::clearCache();
    }

    private function maintenanceRun(bool $dryRun, ?string $key = null): MaintenanceRun
    {
        return MaintenanceRun::query()->create([
            'idempotency_key' => $key ?? str_replace('-', '', (string) Str::uuid()),
            'requested_by_user_id' => $this->developer->getKey(),
            'requested_by_username' => $this->developer->username,
            'status' => 'pending',
            'dry_run' => $dryRun,
            'cursor' => ['task_index' => 0, 'task_cursor' => []],
            'summary' => [],
        ]);
    }

    private function requestRow(CarbonImmutable $startedAt, bool $pageView = false): string
    {
        $id = (string) Str::ulid();
        DB::table('request_activities')->insert([
            'id' => $id,
            'actor_type' => 'anonymous',
            'visitor_hash' => hash('sha256', $id),
            'method' => 'GET',
            'route_name' => $pageView ? 'public.maintenance-test' : 'maintenance-test',
            'path' => $pageView ? '/publik/maintenance-test' : '/maintenance-test',
            'category' => 'request',
            'action' => 'request',
            'http_status' => 200,
            'outcome' => 'succeeded',
            'response_ms' => 25.5,
            'duration_ms' => 25.5,
            'is_page_view' => $pageView,
            'segment' => $pageView ? 'publik' : null,
            'device_type' => $pageView ? 'mobile' : null,
            'is_bot' => false,
            'is_prefetch' => false,
            'events_count' => 0,
            'occurred_at' => $pageView ? $startedAt : null,
            'started_at' => $startedAt,
            'completed_at' => $startedAt,
        ]);

        return $id;
    }
}

class ProbeMaintenanceTask implements MaintenanceTask
{
    public static int $receivedBatchSize = -1;

    public static float $receivedSeconds = -1;

    public static function reset(): void
    {
        self::$receivedBatchSize = -1;
        self::$receivedSeconds = -1;
    }

    public function key(): string
    {
        return 'probe';
    }

    public function label(): string
    {
        return 'Probe';
    }

    public function preview(): array
    {
        return ['safe' => true];
    }

    public function run(array $cursor, int $batchSize, float $deadline): array
    {
        self::$receivedBatchSize = $batchSize;
        self::$receivedSeconds = $deadline - microtime(true);

        return ['complete' => true, 'cursor' => [], 'summary' => ['done' => true]];
    }
}

class ResumableProbeMaintenanceTask implements MaintenanceTask
{
    /** @var array<int, int> */
    public static array $receivedCursors = [];

    public static function reset(): void
    {
        self::$receivedCursors = [];
    }

    public function key(): string
    {
        return 'resumable_probe';
    }

    public function label(): string
    {
        return 'Resumable probe';
    }

    public function preview(): array
    {
        return ['safe' => true];
    }

    public function run(array $cursor, int $batchSize, float $deadline): array
    {
        $step = (int) ($cursor['step'] ?? 0);
        self::$receivedCursors[] = $step;
        $next = $step + 1;

        return [
            'complete' => $next >= 2,
            'cursor' => ['step' => $next],
            'summary' => ['step' => $next],
        ];
    }
}
