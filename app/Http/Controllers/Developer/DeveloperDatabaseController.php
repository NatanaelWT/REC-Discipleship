<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\Developer\DeveloperDatabaseService;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DeveloperDatabaseController extends Controller
{
    public function index(Request $request, DeveloperDatabaseService $database): RedirectResponse|View
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return $this->databaseView($request, $database, null);
    }

    public function table(Request $request, string $table, DeveloperDatabaseService $database): RedirectResponse|View
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        if ($database->normalizeTable($table) === null) {
            return redirect()->route('developer.database', ['error' => 'table_invalid']);
        }

        return $this->databaseView($request, $database, $table);
    }

    public function storeRow(Request $request, string $table, DeveloperDatabaseService $database): RedirectResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $result = $database->createRow($table, $request->all());

        return $this->redirectTable($database, $table, $result);
    }

    public function updateRow(Request $request, string $table, string $key, DeveloperDatabaseService $database): RedirectResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $result = $database->updateRow($table, $key, $request->all());

        return $this->redirectTable($database, $table, $result);
    }

    public function deleteRow(Request $request, string $table, string $key, DeveloperDatabaseService $database): RedirectResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $result = $database->deleteRow($table, $key, $request->input('confirm_danger') === '1');

        return $this->redirectTable($database, $table, $result);
    }

    public function query(Request $request, DeveloperDatabaseService $database): RedirectResponse|View
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $table = $database->normalizeTable((string) $request->input('table', ''));
        $result = $database->executeSql(
            (string) $request->input('sql', ''),
            $request->input('confirm_danger') === '1',
        );

        return $this->databaseView($request, $database, $table, $result, 'sql');
    }

    public function export(Request $request, DeveloperDatabaseService $database): RedirectResponse|BinaryFileResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        try {
            $table = $request->query('table');
            $table = is_string($table) && trim($table) !== '' && $table !== 'all' ? $table : null;
            $result = $database->exportSql($table);
        } catch (Throwable $exception) {
            $result = ['error' => 'export_failed', 'message' => $exception->getMessage()];
        }

        if (($result['path'] ?? null) !== null && is_file((string) $result['path'])) {
            return response()
                ->download((string) $result['path'], (string) ($result['filename'] ?? 'rec-database.sql'), [
                    'Content-Type' => 'application/sql; charset=UTF-8',
                ])
                ->deleteFileAfterSend(true);
        }

        return redirect()
            ->route('developer.database', ['error' => $result['error'] ?? 'export_failed'])
            ->with('developer_database_message', $result['message'] ?? null);
    }

    public function import(Request $request, DeveloperDatabaseService $database): RedirectResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $result = $database->importSql(
            $request->file('sql_file'),
            $request->input('confirm_danger') === '1',
        );

        $params = isset($result['error'])
            ? ['error' => $result['error']]
            : ['status' => $result['status'] ?? 'imported'];

        return redirect()
            ->route('developer.database', $params)
            ->with('developer_database_message', $result['message'] ?? null);
    }

    /**
     * @param array<string, mixed>|null $sqlResult
     */
    private function databaseView(
        Request $request,
        DeveloperDatabaseService $database,
        ?string $table,
        ?array $sqlResult = null,
        ?string $activeTab = null,
    ): View {
        $table = $table !== null ? $database->normalizeTable($table) : null;
        $tableInfo = null;
        $browse = null;
        if ($table !== null) {
            $tableInfo = $database->tableInfo($table);
            $browse = $database->browse($table, $request->query());
        }

        return view('developer.database', [
            'settings' => ['church_name' => app_church_name()],
            'currentPage' => 'developer_database',
            'summary' => $database->summary(),
            'tables' => $database->tables(),
            'selectedTable' => $table,
            'tableInfo' => $tableInfo,
            'browse' => $browse,
            'activeTab' => $activeTab ?? trim((string) $request->query('tab', 'browse')),
            'statusCode' => trim((string) $request->query('status', '')),
            'errorCode' => trim((string) $request->query('error', '')),
            'message' => session('developer_database_message'),
            'sqlResult' => $sqlResult,
            'sqlInput' => (string) ($sqlResult['sql'] ?? $request->query('sql', '')),
            'errorMessages' => $this->errorMessages(),
        ]);
    }

    /**
     * @param array{status?:string,error?:string,message?:string} $result
     */
    private function redirectTable(DeveloperDatabaseService $database, string $table, array $result): RedirectResponse
    {
        $table = $database->normalizeTable($table) ?? $table;
        $params = isset($result['error'])
            ? ['error' => $result['error'], 'tab' => 'browse']
            : ['status' => $result['status'] ?? 'saved', 'tab' => 'browse'];

        return redirect()
            ->route('developer.database.table', ['table' => $table] + $params)
            ->with('developer_database_message', $result['message'] ?? null);
    }

    private function guard(Request $request): ?RedirectResponse
    {
        RuntimeBootstrap::boot($request);
        if (! is_logged_in()) {
            return redirect()->route('auth.login');
        }
        if (! can_manage_users()) {
            abort(403, 'Akses developer diperlukan.');
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function errorMessages(): array
    {
        return [
            'table_invalid' => 'Tabel tidak valid atau tidak ditemukan.',
            'table_missing' => 'Belum ada tabel yang bisa diproses.',
            'row_empty' => 'Tidak ada nilai row yang dikirim.',
            'row_key_invalid' => 'Kunci row tidak valid.',
            'row_not_found' => 'Row tidak ditemukan.',
            'primary_key_missing' => 'Tabel ini tidak punya primary key sehingga row tidak bisa diedit dari UI.',
            'confirm_required' => 'Konfirmasi diperlukan sebelum menjalankan aksi berbahaya.',
            'write_failed' => 'Perubahan database gagal.',
            'sql_empty' => 'SQL wajib diisi.',
            'sql_multiple' => 'Jalankan satu statement SQL per request.',
            'sql_failed' => 'SQL gagal dijalankan.',
            'sql_file_operation_denied' => 'Operasi file dari SQL ditolak.',
            'export_unsupported' => 'Export SQL belum didukung untuk driver database ini.',
            'export_failed' => 'Export SQL gagal dibuat.',
            'import_unsupported' => 'Import SQL belum didukung untuk driver database ini.',
            'import_missing_file' => 'Pilih file SQL untuk import.',
            'import_invalid_file_type' => 'File import harus berekstensi .sql.',
            'import_file_too_large' => 'Ukuran file SQL terlalu besar. Maksimal 25 MB.',
            'import_empty' => 'File SQL kosong.',
            'import_failed' => 'Import SQL gagal dijalankan.',
        ];
    }
}
