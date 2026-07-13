<?php

namespace Tests\Feature;

use App\Http\Middleware\WrapUnsafeRequestInTransaction;
use App\Services\Branches\BranchCatalog;
use App\Support\RuntimeBootstrap;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Concerns\RejectsTrackingQueries;
use Tests\TestCase;
use ZipArchive;

class MskSynchronousImportTest extends TestCase
{
    use RejectsTrackingQueries;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        RuntimeBootstrap::load();
        config([
            'msk_import.max_file_bytes' => 10 * 1024 * 1024,
            'msk_import.max_errors' => 100,
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

    public function test_import_is_upsert_only_and_retrying_the_same_file_is_a_no_op(): void
    {
        $temporaryFilesBefore = $this->importTempFiles();
        $this->startTrackingQueryGuard();
        $legacyImportQueries = [];
        DB::listen(static function (QueryExecuted $query) use (&$legacyImportQueries): void {
            if (preg_match('/\bmsk_import_(?:jobs|source_keys|existing_people|batches)\b/i', $query->sql) === 1) {
                $legacyImportQueries[] = $query->sql;
            }
        });

        $aliceId = DB::table('orang')->insertGetId($this->person('Alice Lama', '0811'));
        DB::table('orang')->where('id', $aliceId)->update([
            'completed_at' => '2026-06-30',
            'journey_bridge_status' => 'sudah',
            'status' => 'inactive',
            'photos' => json_encode([['path' => 'msk/alice.webp']], JSON_THROW_ON_ERROR),
        ]);
        $charlieId = DB::table('orang')->insertGetId($this->person('Charlie Tetap', '0844'));
        $preservedId = DB::table('orang')->insertGetId($this->person('Tetap Walau Tidak Diimpor', '0855'));
        $xlsx = $this->xlsx([
            [
                'id' => $aliceId,
                'full_name' => 'Alice Baru',
                'whatsapp' => '0811',
                'msk_month' => '2026-07',
                'session_numbers' => [1, 2],
            ],
            [
                'id' => $charlieId,
                'full_name' => 'Charlie Tetap',
                'whatsapp' => '0844',
                'msk_month' => '2026-06',
                'session_numbers' => [1],
            ],
            [
                'full_name' => 'Bob Baru',
                'whatsapp' => '0833',
                'msk_month' => '2026-07',
                'session_numbers' => [1],
            ],
        ]);

        $response = $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'import_pemuridan_excel' => new UploadedFile($xlsx, 'peserta.xlsx', null, null, true),
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('total', 3)
            ->assertJsonPath('inserted', 1)
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('unchanged', 1)
            ->assertJsonPath('no_op', false)
            ->assertJsonPath('errors', []);
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertDatabaseHas('orang', ['id' => $aliceId, 'full_name' => 'Alice Baru']);
        $this->assertDatabaseHas('orang', [
            'id' => $aliceId,
            'completed_at' => '2026-06-30',
            'journey_bridge_status' => 'sudah',
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('orang', ['id' => $charlieId, 'full_name' => 'Charlie Tetap']);
        $this->assertDatabaseHas('orang', ['id' => $preservedId, 'full_name' => 'Tetap Walau Tidak Diimpor']);
        $this->assertDatabaseHas('orang', ['full_name' => 'Bob Baru', 'branch_id' => 1]);

        $retry = $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'import_pemuridan_excel' => new UploadedFile($xlsx, 'peserta.xlsx', null, null, true),
        ]);
        $retry->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('total', 3)
            ->assertJsonPath('inserted', 0)
            ->assertJsonPath('updated', 0)
            ->assertJsonPath('unchanged', 3)
            ->assertJsonPath('no_op', true)
            ->assertJsonPath('errors', []);
        $this->assertDatabaseCount('orang', 4);
        $this->assertSame([], $legacyImportQueries, 'Synchronous import must not use the removed resumable-import tables.');
        $this->assertNoTrackingQueriesWereExecuted();
        $this->assertSame($temporaryFilesBefore, $this->importTempFiles());
    }

    public function test_validation_failure_is_error_only_and_does_not_change_domain_rows(): void
    {
        $aliceId = DB::table('orang')->insertGetId($this->person('Alice Aman', '0811'));
        $xlsx = $this->xlsx([[
            'id' => $aliceId,
            'full_name' => 'Alice Tidak Boleh Berubah',
            'whatsapp' => '0811',
            'msk_month' => 'bukan-bulan',
            'session_numbers' => [1],
        ]]);

        $response = $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'import_pemuridan_excel' => new UploadedFile($xlsx, 'invalid.xlsx', null, null, true),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('error', 'import_validation_failed')
            ->assertJsonPath('context.errors.0.code', 'invalid_msk_month');
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertDatabaseHas('orang', ['id' => $aliceId, 'full_name' => 'Alice Aman']);
        $this->assertDatabaseCount('orang', 1);
    }

    public function test_held_branch_lock_returns_conflict_without_writing_rows(): void
    {
        $xlsx = $this->xlsx([[
            'full_name' => 'Tidak Boleh Masuk',
            'whatsapp' => '0866',
            'msk_month' => '2026-07',
            'session_numbers' => [1],
        ]]);
        $lock = Cache::lock('msk-import:branch:1', 300);
        $this->assertTrue($lock->get());

        try {
            $response = $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
                'action' => 'import_pemuridan_excel',
                'import_pemuridan_excel' => new UploadedFile($xlsx, 'locked.xlsx', null, null, true),
            ]);
        } finally {
            $lock->release();
        }

        $response->assertStatus(409)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('error', 'import_in_progress')
            ->assertJsonPath('errors', []);
        $this->assertDatabaseCount('orang', 0);
    }

    public function test_configured_row_limit_rejects_the_file_before_any_row_is_finalized(): void
    {
        config(['msk_import.max_rows' => 1]);
        $xlsx = $this->xlsx([
            [
                'full_name' => 'Baris Pertama',
                'whatsapp' => '0871',
                'msk_month' => '2026-07',
                'session_numbers' => [1],
            ],
            [
                'full_name' => 'Baris Kedua',
                'whatsapp' => '0872',
                'msk_month' => '2026-07',
                'session_numbers' => [1],
            ],
        ]);

        $response = $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'import_pemuridan_excel' => new UploadedFile($xlsx, 'too-many.xlsx', null, null, true),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('error', 'import_too_many_rows')
            ->assertJsonPath('context.total_rows', 2)
            ->assertJsonPath('context.max_rows', 1);
        $this->assertDatabaseCount('orang', 0);
    }

    public function test_ambiguous_duplicate_and_cross_branch_matches_are_rejected_before_writes(): void
    {
        DB::table('orang')->insert($this->person('Nama Sama', '08123'));
        DB::table('orang')->insert($this->person('Nama Sama', '08123'));
        $ambiguous = $this->xlsx([[
            'full_name' => 'Nama Sama',
            'whatsapp' => '08123',
            'msk_month' => '2026-07',
            'session_numbers' => [1],
        ]]);

        $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'import_pemuridan_excel' => new UploadedFile($ambiguous, 'ambiguous.xlsx', null, null, true),
        ])->assertStatus(422)
            ->assertJsonPath('error', 'import_validation_failed')
            ->assertJsonPath('errors.0.code', 'ambiguous_identity');

        $crossBranchId = DB::table('orang')->insertGetId([
            ...$this->person('Cabang Lain', '08234'),
            'branch_id' => 2,
        ]);
        $crossBranch = $this->xlsx([[
            'id' => $crossBranchId,
            'full_name' => 'Cabang Lain',
            'whatsapp' => '08234',
            'msk_month' => '2026-07',
            'session_numbers' => [1],
        ]]);
        $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'import_pemuridan_excel' => new UploadedFile($crossBranch, 'cross-branch.xlsx', null, null, true),
        ])->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'participant_not_in_branch');

        $duplicate = $this->xlsx([
            ['full_name' => 'Duplikat', 'whatsapp' => '08345', 'msk_month' => '2026-07', 'session_numbers' => [1]],
            ['full_name' => 'Duplikat', 'whatsapp' => '08345', 'msk_month' => '2026-07', 'session_numbers' => [1, 2]],
        ]);
        $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'import_pemuridan_excel' => new UploadedFile($duplicate, 'duplicate.xlsx', null, null, true),
        ])->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'duplicate_source_identity');

        $this->assertDatabaseCount('orang', 3);
    }

    public function test_file_and_field_resource_limits_fail_without_persisting_rows(): void
    {
        $oversized = UploadedFile::fake()->create('oversized.xlsx', 10 * 1024 + 1, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'import_pemuridan_excel' => $oversized,
        ])->assertStatus(422)->assertJsonPath('error', 'import_file_too_large');

        $longField = $this->xlsx([[
            'full_name' => str_repeat('A', 256),
            'whatsapp' => '08456',
            'msk_month' => '2026-07',
            'session_numbers' => [1],
        ]]);
        $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'import_pemuridan_excel' => new UploadedFile($longField, 'long-field.xlsx', null, null, true),
        ])->assertStatus(422)
            ->assertJsonPath('error', 'import_validation_failed')
            ->assertJsonPath('errors.0.code', 'field_too_long');

        $this->assertDatabaseCount('orang', 0);
    }

    public function test_corrupt_workbook_and_missing_headers_fail_before_writes(): void
    {
        $corrupt = UploadedFile::fake()->createWithContent('corrupt.xlsx', 'not-an-xlsx-archive');
        $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'import_pemuridan_excel' => $corrupt,
        ])->assertStatus(422)->assertJsonPath('error', 'import_invalid_excel');

        $missingHeader = $this->xlsx([[
            'full_name' => 'Header Tidak Lengkap',
            'msk_month' => '2026-07',
            'session_numbers' => [1],
        ]]);
        $this->replaceWorksheet($missingHeader,
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1">'.$this->inlineCell('A1', 'full_name').$this->inlineCell('B1', 'msk_month').'</row>'
            .'<row r="2">'.$this->inlineCell('A2', 'Header Tidak Lengkap').$this->inlineCell('B2', '2026-07').'</row>'
            .'</sheetData></worksheet>',
        );
        $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'import_pemuridan_excel' => new UploadedFile($missingHeader, 'missing-header.xlsx', null, null, true),
        ])->assertStatus(422)
            ->assertJsonPath('error', 'import_validation_failed')
            ->assertJsonPath('errors.0.code', 'missing_header');

        $this->assertDatabaseCount('orang', 0);
    }

    public function test_shared_string_expansion_and_invalid_indexes_are_rejected_without_writes(): void
    {
        $temporaryFilesBefore = $this->importTempFiles();
        $expanded = $this->xlsx([[
            'full_name' => 'Placeholder',
            'msk_month' => '2026-07',
            'session_numbers' => [1],
        ]]);
        $this->addSharedStrings($expanded, [str_repeat('N', 65535)]);
        $dataRows = '';
        for ($row = 2; $row <= 514; $row++) {
            $dataRows .= '<row r="'.$row.'">'
                .$this->inlineCell('A'.$row, 'Peserta Ekspansi '.$row)
                .$this->inlineCell('B'.$row, '2026-07')
                .$this->inlineCell('C'.$row, '1')
                .$this->sharedStringCell('D'.$row, '0')
                .'</row>';
        }
        $this->replaceWorksheet($expanded, $this->worksheetXmlWithNotes($dataRows));
        $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'import_pemuridan_excel' => new UploadedFile($expanded, 'expanded.xlsx', null, null, true),
        ])->assertStatus(422)->assertJsonPath('error', 'import_stage_too_large');

        foreach (['abc', '7'] as $invalidIndex) {
            $invalid = $this->xlsx([[
                'full_name' => 'Placeholder',
                'msk_month' => '2026-07',
                'session_numbers' => [1],
            ]]);
            $this->addSharedStrings($invalid, ['Satu-satunya shared string']);
            $this->replaceWorksheet($invalid, $this->worksheetXmlWithNotes(
                '<row r="2">'
                .$this->inlineCell('A2', 'Peserta Indeks Rusak')
                .$this->inlineCell('B2', '2026-07')
                .$this->inlineCell('C2', '1')
                .$this->sharedStringCell('D2', $invalidIndex)
                .'</row>',
            ));
            $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
                'action' => 'import_pemuridan_excel',
                'import_pemuridan_excel' => new UploadedFile($invalid, 'invalid-index.xlsx', null, null, true),
            ])->assertStatus(422)->assertJsonPath('error', 'import_invalid_excel');
        }

        $this->assertDatabaseCount('orang', 0);
        $this->assertSame($temporaryFilesBefore, $this->importTempFiles());
    }

    public function test_malformed_wide_columns_and_duplicate_row_numbers_are_rejected_safely(): void
    {
        $wide = $this->xlsx([[
            'full_name' => 'Placeholder',
            'whatsapp' => '08567',
            'msk_month' => '2026-07',
            'session_numbers' => [1],
        ]]);
        $this->replaceWorksheet($wide, $this->worksheetXml(
            '<row r="2"><c r="ZZZZZZ2" t="inlineStr"><is><t>Lebar</t></is></c></row>',
        ));
        $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'import_pemuridan_excel' => new UploadedFile($wide, 'wide.xlsx', null, null, true),
        ])->assertStatus(422)->assertJsonPath('error', 'import_too_many_columns');

        $duplicateRows = $this->xlsx([[
            'full_name' => 'Placeholder',
            'whatsapp' => '08568',
            'msk_month' => '2026-07',
            'session_numbers' => [1],
        ]]);
        $this->replaceWorksheet($duplicateRows, $this->worksheetXml(
            '<row r="2">'.$this->inlineCell('A2', 'Peserta Satu').$this->inlineCell('B2', '2026-07').$this->inlineCell('C2', '1').'</row>'.
            '<row r="2">'.$this->inlineCell('A2', 'Peserta Dua').$this->inlineCell('B2', '2026-07').$this->inlineCell('C2', '1').'</row>',
        ));
        $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'import_pemuridan_excel' => new UploadedFile($duplicateRows, 'duplicate-rows.xlsx', null, null, true),
        ])->assertStatus(422)
            ->assertJsonPath('error', 'import_validation_failed')
            ->assertJsonPath('errors.0.code', 'duplicate_row_number');

        $this->assertDatabaseCount('orang', 0);
    }

    public function test_failure_after_the_second_write_chunk_rolls_back_every_row_and_cleans_temp_file(): void
    {
        $rows = [];
        for ($index = 1; $index <= 501; $index++) {
            $rows[] = [
                'full_name' => 'Peserta Chunk '.$index,
                'whatsapp' => '09'.str_pad((string) $index, 6, '0', STR_PAD_LEFT),
                'msk_month' => '2026-07',
                'session_numbers' => [1],
            ];
        }
        $xlsx = $this->xlsx($rows);
        $temporaryFilesBefore = $this->importTempFiles();
        $insertQueries = 0;
        DB::listen(static function (QueryExecuted $query) use (&$insertQueries): void {
            if (preg_match('/^insert\s+into\s+["`]?orang["`]?/i', trim($query->sql)) !== 1) {
                return;
            }
            $insertQueries++;
            if ($insertQueries === 2) {
                throw new RuntimeException('Force rollback after second import chunk.');
            }
        });

        $response = $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'import_pemuridan_excel' => new UploadedFile($xlsx, 'rollback.xlsx', null, null, true),
        ]);

        $response->assertStatus(500)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('error', 'import_failed');
        $this->assertSame(2, $insertQueries);
        $this->assertDatabaseCount('orang', 0);
        $this->assertSame($temporaryFilesBefore, $this->importTempFiles());
        $releasedLock = Cache::lock('msk-import:branch:1', 60);
        $this->assertTrue($releasedLock->get());
        $releasedLock->release();
    }

    public function test_five_thousand_rows_use_bounded_memory_and_chunked_writes(): void
    {
        config(['msk_import.max_rows' => 5000]);
        $rows = [];
        for ($index = 1; $index <= 5000; $index++) {
            $rows[] = [
                'full_name' => 'Peserta Skala '.$index,
                'whatsapp' => '08'.str_pad((string) $index, 8, '0', STR_PAD_LEFT),
                'msk_month' => '2026-07',
                'session_numbers' => [1],
            ];
        }
        $xlsx = $this->xlsx($rows);
        unset($rows);

        $insertQueries = 0;
        DB::listen(static function (QueryExecuted $query) use (&$insertQueries): void {
            if (preg_match('/^insert\s+into\s+["`]?orang["`]?/i', trim($query->sql)) === 1) {
                $insertQueries++;
            }
        });

        $response = $this->withHeader('Accept', 'application/json')->post('/pemuridan/msk/impor', [
            'action' => 'import_pemuridan_excel',
            'import_pemuridan_excel' => new UploadedFile($xlsx, 'five-thousand.xlsx', null, null, true),
        ]);

        $response->assertOk()
            ->assertJsonPath('inserted', 5000)
            ->assertJsonPath('updated', 0)
            ->assertJsonPath('unchanged', 0);
        $this->assertSame(10, $insertQueries);
        $this->assertDatabaseCount('orang', 5000);
        $this->assertLessThanOrEqual(128 * 1024 * 1024, memory_get_peak_usage(true));
    }

    public function test_removed_progress_routes_and_frontend_contract_do_not_return(): void
    {
        $legacyStatusRoute = 'discipleship.msk-classes.import'.'-status';
        $legacyBatchRoute = 'discipleship.msk-classes.import'.'-batch';
        $this->assertFalse(Route::has($legacyStatusRoute));
        $this->assertFalse(Route::has($legacyBatchRoute));

        $importRoute = Route::getRoutes()->getByName('discipleship.msk-classes.import');
        $this->assertNotNull($importRoute);
        $this->assertContains(WrapUnsafeRequestInTransaction::class, $importRoute->excludedMiddleware());

        $jobId = '01J00000000000000000000000';
        $this->get('/pemuridan/msk/impor/'.$jobId.'/status')->assertNotFound();
        $this->post('/pemuridan/msk/impor/'.$jobId.'/batch')->assertNotFound();

        $index = file_get_contents(resource_path('views/discipleship/msk-participants/index.blade.php'));
        $controls = file_get_contents(resource_path('views/discipleship/partials/page-header-controls/msk.blade.php'));
        $entry = file_get_contents(resource_path('js/app.js'));
        $this->assertIsString($index);
        $this->assertIsString($controls);
        $this->assertIsString($entry);
        $this->assertStringNotContainsString('msk_import_'.'job_id', $index);
        $this->assertStringNotContainsString('import'.'-status', $index);
        $this->assertStringNotContainsString('idempotency_'.'token', $controls);
        $legacyModule = 'msk-'.'import.js';
        $this->assertStringNotContainsString($legacyModule, $entry);
        $this->assertFileDoesNotExist(resource_path('js/modules/'.$legacyModule));
    }

    public function test_import_feedback_never_mixes_success_and_failure(): void
    {
        $success = $this->renderFeedback([
            'imported' => '1',
            'import_msk_inserted' => '2',
            'import_msk_updated' => '3',
            'import_msk_unchanged' => '4',
        ]);
        $this->assertStringContainsString('2 ditambah, 3 diperbarui, 4 tidak berubah', $success);
        $this->assertStringContainsString('alert success', $success);
        $this->assertStringNotContainsString('alert danger', $success);

        $failure = $this->renderFeedback([
            'imported' => '1',
            'import_msk_inserted' => '9',
            'error' => 'import_validation_failed',
            'import_error_count' => '2',
            'import_error_preview' => 'Baris 7: bulan tidak valid.',
        ]);
        $this->assertStringContainsString('alert danger', $failure);
        $this->assertStringContainsString('Ditemukan 2 error', $failure);
        $this->assertStringContainsString('Baris 7: bulan tidak valid.', $failure);
        $this->assertStringNotContainsString('Import selesai', $failure);
        $this->assertStringNotContainsString('alert success', $failure);
    }

    /** @param array<string,string> $query */
    private function renderFeedback(array $query): string
    {
        $previous = $_GET;
        $_GET = $query;
        ob_start();
        try {
            render_pemuridan_import_feedback();

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
            $_GET = $previous;
        }
    }

    /** @return list<string> */
    private function importTempFiles(): array
    {
        $files = glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'rec_msk_import_*') ?: [];
        sort($files);

        return array_values($files);
    }

    private function replaceWorksheet(string $path, string $xml): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        try {
            $this->assertTrue($zip->addFromString('xl/worksheets/sheet1.xml', $xml));
        } finally {
            $zip->close();
        }
    }

    private function worksheetXml(string $dataRows): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1">'
            .$this->inlineCell('A1', 'full_name')
            .$this->inlineCell('B1', 'msk_month')
            .$this->inlineCell('C1', 'session_numbers')
            .'</row>'.$dataRows.'</sheetData></worksheet>';
    }

    private function worksheetXmlWithNotes(string $dataRows): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1">'
            .$this->inlineCell('A1', 'full_name')
            .$this->inlineCell('B1', 'msk_month')
            .$this->inlineCell('C1', 'session_numbers')
            .$this->inlineCell('D1', 'notes')
            .'</row>'.$dataRows.'</sheetData></worksheet>';
    }

    private function inlineCell(string $reference, string $value): string
    {
        return '<c r="'.$reference.'" t="inlineStr"><is><t>'
            .htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            .'</t></is></c>';
    }

    private function sharedStringCell(string $reference, string $index): string
    {
        return '<c r="'.$reference.'" t="s"><v>'
            .htmlspecialchars($index, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            .'</v></c>';
    }

    /** @param list<string> $values */
    private function addSharedStrings(string $path, array $values): void
    {
        $items = '';
        foreach ($values as $value) {
            $items .= '<si><t>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></si>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($values).'" uniqueCount="'.count($values).'">'
            .$items.'</sst>';

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        try {
            $this->assertTrue($zip->addFromString('xl/sharedStrings.xml', $xml));
        } finally {
            $zip->close();
        }
    }

    /** @param list<array<string,mixed>> $participants */
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
            'gender' => null,
            'birth_date' => null,
            'birth_place' => null,
            'address' => null,
            'email' => null,
            'whatsapp' => $whatsapp,
            'batch_month' => '2026-06',
            'notes' => null,
            'completed_at' => null,
            'journey_bridge_status' => 'belum',
            'status' => 'active',
            'session_numbers' => json_encode([1], JSON_THROW_ON_ERROR),
            'photos' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function createTables(): void
    {
        foreach (['orang', 'cabang'] as $table) {
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
        DB::table('cabang')->insert([
            'id' => 1,
            'label' => 'Kutisari',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
    }
}
