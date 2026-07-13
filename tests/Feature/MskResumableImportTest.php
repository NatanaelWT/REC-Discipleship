<?php

namespace Tests\Feature;

use App\Http\Requests\MskParticipants\ImportMskParticipantsRequest;
use App\Models\MskImportJob;
use App\Services\Branches\BranchCatalog;
use App\Services\MskParticipants\MskImportBatchProcessor;
use App\Services\MskParticipants\MskImportCoordinator;
use App\Support\RuntimeBootstrap;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RejectsTrackingQueries;
use Tests\TestCase;

class MskResumableImportTest extends TestCase
{
    use RejectsTrackingQueries;

    /** @var array<int,string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        RuntimeBootstrap::load();
        Storage::fake('local');
        config([
            'msk_import.disk' => 'local',
            'msk_import.batch_size' => 1,
            'msk_import.batch_seconds' => 8,
        ]);
        $this->createTables();
        $this->actingAsRecUser();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_import_is_persisted_resumable_and_batch_tokens_are_idempotent(): void
    {
        $this->startTrackingQueryGuard();

        $aliceId = DB::table('orang')->insertGetId($this->person('Alice Lama', '0811'));
        $removedId = DB::table('orang')->insertGetId($this->person('Tidak Ada di File', '0822'));
        $xlsx = $this->xlsx([
            [
                'id' => $aliceId,
                'full_name' => 'Alice Baru',
                'whatsapp' => '0811',
                'msk_month' => '2026-07',
                'session_numbers' => [1, 2],
            ],
            [
                'full_name' => 'Bob Baru',
                'whatsapp' => '0833',
                'msk_month' => '2026-07',
                'session_numbers' => [1],
            ],
        ]);

        $start = $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'idempotency_token' => 'browser-upload-1',
            'import_pemuridan_excel' => new UploadedFile($xlsx, 'peserta.xlsx', null, null, true),
        ]);
        $start->assertStatus(202)->assertJsonPath('status', 'pending');
        $jobId = (string) $start->json('id');
        $this->assertNotSame('', $jobId);
        $this->assertDatabaseHas('msk_import_jobs', ['id' => $jobId, 'status' => 'pending', 'total_rows' => 2]);
        $this->assertDatabaseHas('orang', ['id' => $aliceId, 'full_name' => 'Alice Lama']);

        $parallel = $this->xlsx([[
            'full_name' => 'Import Paralel',
            'whatsapp' => '0899',
            'msk_month' => '2026-07',
            'session_numbers' => [1],
        ]]);
        $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'idempotency_token' => 'parallel-upload',
            'import_pemuridan_excel' => new UploadedFile($parallel, 'parallel.xlsx', null, null, true),
        ])->assertStatus(422)->assertJsonPath('error', 'import_in_progress');

        // A retried upload with the same browser token returns the same job,
        // even when the multipart body is no longer available.
        $retryStart = $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'idempotency_token' => 'browser-upload-1',
        ]);
        $retryStart->assertStatus(202)->assertJsonPath('id', $jobId);
        $this->assertSame(1, MskImportJob::query()->count());

        $status = $this->getJson("/pemuridan/msk/impor/{$jobId}/status");
        $status->assertOk()->assertJsonPath('processed', 0)->assertJsonPath('terminal', false);

        $first = $this->postJson("/pemuridan/msk/impor/{$jobId}/batch", [
            'action' => 'import_pemuridan_excel',
            'batch_token' => 'batch-one',
        ]);
        $first->assertOk()->assertJsonPath('processed', 1)->assertJsonPath('status', 'running');
        $this->assertDatabaseHas('orang', ['id' => $aliceId, 'full_name' => 'Alice Baru']);

        // Retrying after a lost response returns the recorded result and does
        // not advance into the next row.
        $sameBatch = $this->postJson("/pemuridan/msk/impor/{$jobId}/batch", [
            'action' => 'import_pemuridan_excel',
            'batch_token' => 'batch-one',
        ]);
        $sameBatch->assertOk()->assertJsonPath('processed', 1);
        $this->assertDatabaseMissing('orang', ['full_name' => 'Bob Baru']);

        $completed = $this->postJson("/pemuridan/msk/impor/{$jobId}/batch", [
            'action' => 'import_pemuridan_excel',
            'batch_token' => 'batch-two',
        ]);
        $completed->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('terminal', true)
            ->assertJsonPath('processed', 2)
            ->assertJsonPath('inserted', 1)
            ->assertJsonPath('updated', 1);
        $this->assertDatabaseHas('orang', ['full_name' => 'Bob Baru', 'branch_id' => 1]);
        $this->assertDatabaseMissing('orang', ['id' => $removedId]);

        // Idempotency is durable, not merely a cache of the latest request.
        $oldRetry = $this->postJson("/pemuridan/msk/impor/{$jobId}/batch", [
            'action' => 'import_pemuridan_excel',
            'batch_token' => 'batch-one',
        ]);
        $oldRetry->assertOk()->assertJsonPath('processed', 1)->assertJsonPath('status', 'running');
        $this->assertSame(2, DB::table('orang')->count());
        $this->assertSame(2, DB::table('msk_import_batches')->where('job_id', $jobId)->count());

        $job = MskImportJob::query()->findOrFail($jobId);
        Storage::disk('local')->assertMissing((string) $job->source_path);
        Storage::disk('local')->assertMissing((string) $job->staged_path);
        $this->assertNoTrackingQueriesWereExecuted();
    }

    public function test_preflight_validation_fails_before_any_domain_row_is_changed(): void
    {
        $aliceId = DB::table('orang')->insertGetId($this->person('Alice Aman', '0811'));
        $xlsx = $this->xlsx([
            [
                'id' => $aliceId,
                'full_name' => 'Alice Tidak Boleh Berubah',
                'whatsapp' => '0811',
                'msk_month' => 'bukan-bulan',
                'session_numbers' => [1],
            ],
        ]);

        $response = $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'idempotency_token' => 'invalid-upload',
            'import_pemuridan_excel' => new UploadedFile($xlsx, 'invalid.xlsx', null, null, true),
        ]);
        $response->assertOk()->assertJsonPath('status', 'failed');
        $jobId = (string) $response->json('id');

        $this->getJson("/pemuridan/msk/impor/{$jobId}/status")
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('errors.0.code', 'invalid_msk_month');
        $this->assertDatabaseHas('orang', ['id' => $aliceId, 'full_name' => 'Alice Aman']);
    }

    public function test_outer_transaction_failure_removes_new_import_files_and_job(): void
    {
        $xlsx = $this->xlsx([[
            'full_name' => 'Rollback Upload',
            'whatsapp' => '08123',
            'msk_month' => '2026-07',
            'session_numbers' => [1],
        ]]);

        Route::middleware('web')->post(
            '/_tests/msk-import/start-rollback',
            static function (ImportMskParticipantsRequest $request, MskImportCoordinator $imports) {
                $imports->start($request);

                return response('force outer rollback', 500);
            },
        );

        $this->post('/_tests/msk-import/start-rollback', [
            'action' => 'import_pemuridan_excel',
            'idempotency_token' => 'rollback-upload',
            'import_pemuridan_excel' => new UploadedFile($xlsx, 'rollback.xlsx', null, null, true),
        ])->assertStatus(500);

        $this->assertDatabaseCount('msk_import_jobs', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('imports/msk'));
    }

    public function test_outer_batch_failure_keeps_resumable_files_until_a_real_commit(): void
    {
        $xlsx = $this->xlsx([[
            'full_name' => 'Resume Setelah Rollback',
            'whatsapp' => '08456',
            'msk_month' => '2026-07',
            'session_numbers' => [1],
        ]]);
        $start = $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'idempotency_token' => 'batch-rollback',
            'import_pemuridan_excel' => new UploadedFile($xlsx, 'batch-rollback.xlsx', null, null, true),
        ])->assertStatus(202);
        $jobId = (string) $start->json('id');
        $job = MskImportJob::query()->findOrFail($jobId);
        Storage::disk('local')->assertExists((string) $job->source_path);
        Storage::disk('local')->assertExists((string) $job->staged_path);

        Route::middleware('web')->post(
            '/_tests/msk-import/{importJob}/batch-rollback',
            static function (MskImportJob $importJob, MskImportBatchProcessor $processor) {
                $processor->process($importJob, 'rolled-back-batch');

                return response('force outer rollback', 500);
            },
        );

        $this->post("/_tests/msk-import/{$jobId}/batch-rollback")->assertStatus(500);
        $job->refresh();
        $this->assertSame('pending', $job->status);
        $this->assertSame(0, (int) $job->processed_rows);
        Storage::disk('local')->assertExists((string) $job->source_path);
        Storage::disk('local')->assertExists((string) $job->staged_path);

        $this->postJson("/pemuridan/msk/impor/{$jobId}/batch", [
            'action' => 'import_pemuridan_excel',
            'batch_token' => 'committed-batch',
        ])->assertOk()->assertJsonPath('status', 'completed');

        $this->assertDatabaseHas('orang', ['full_name' => 'Resume Setelah Rollback']);
        Storage::disk('local')->assertMissing((string) $job->source_path);
        Storage::disk('local')->assertMissing((string) $job->staged_path);
    }

    /** @param array<int,array<string,mixed>> $participants */
    private function xlsx(array $participants): string
    {
        $error = '';
        $path = create_msk_import_export_xlsx($participants, $error);
        $this->assertSame('', $error);
        $this->assertNotNull($path);
        $this->temporaryFiles[] = $path;

        return $path;
    }

    /** @return array<string,mixed> */
    private function person(string $name, string $whatsapp): array
    {
        return [
            'branch_id' => 1,
            'full_name' => $name,
            'whatsapp' => $whatsapp,
            'batch_month' => '2026-06',
            'journey_bridge_status' => 'belum',
            'status' => 'active',
            'session_numbers' => json_encode([1]),
            'photos' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function createTables(): void
    {
        foreach (['msk_import_batches', 'msk_import_existing_people', 'msk_import_source_keys', 'msk_import_jobs', 'orang', 'cabang'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('cabang', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('camp_gap_participant_target')->default(50);
            $table->unsignedInteger('msk_completion_target')->default(50);
            $table->unsignedInteger('dg1_completion_target')->default(50);
            $table->unsignedInteger('dg2_completion_target')->default(50);
            $table->unsignedInteger('dg3_completion_target')->default(50);
            $table->timestamps();
        });
        DB::table('cabang')->insert(['id' => 1, 'label' => 'Kutisari', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        app(BranchCatalog::class)->clearCache();

        Schema::create('orang', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name')->nullable();
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('batch_month')->nullable();
            $table->text('notes')->nullable();
            $table->string('completed_at')->nullable();
            $table->string('journey_bridge_status')->default('belum');
            $table->string('status')->default('active');
            $table->json('session_numbers')->nullable();
            $table->json('photos')->nullable();
            $table->timestamps();
        });

        $migration = require database_path('migrations/2026_07_13_120000_create_msk_import_jobs.php');
        $migration->up();
    }
}
