<?php

namespace App\Services\Developer;

use App\Services\Activity\ActivityRecorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DeveloperDatabaseService
{
    private const PAGE_SIZE = 50;
    private const MAX_QUERY_ROWS = 200;
    private const MAX_IMPORT_BYTES = 26214400;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $tableCache = null;

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $columnCache = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $indexCache = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $foreignKeyCache = [];

    /** @var array<string, array<int, string>> */
    private array $primaryKeyCache = [];

    public function __construct(private readonly ActivityRecorder $activity) {}

    /**
     * @return array{connection:string,driver:string,database:string,table_count:int,export_supported:bool}
     */
    public function summary(): array
    {
        return [
            'connection' => (string) config('database.default', 'default'),
            'driver' => $this->driver(),
            'database' => (string) (DB::connection()->getDatabaseName() ?? ''),
            'table_count' => count($this->tables()),
            'export_supported' => $this->supportsSqlDump(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tables(): array
    {
        if ($this->tableCache !== null) {
            return $this->tableCache;
        }

        try {
            $tables = array_map(function (array $table): array {
                $name = trim((string) ($table['name'] ?? $table['table'] ?? $table['TABLE_NAME'] ?? ''));

                return [
                    'name' => $name,
                    'type' => trim((string) ($table['type'] ?? $table['TABLE_TYPE'] ?? 'table')),
                    'engine' => trim((string) ($table['engine'] ?? '')),
                    'size' => isset($table['size']) ? (int) $table['size'] : null,
                ];
            }, Schema::getTables());
        } catch (Throwable) {
            $tables = $this->fallbackTables();
        }

        $tables = array_values(array_filter($tables, static fn (array $table): bool => ($table['name'] ?? '') !== ''));
        usort($tables, static fn (array $left, array $right): int => strcmp((string) $left['name'], (string) $right['name']));

        return $this->tableCache = $tables;
    }

    public function normalizeTable(string $table): ?string
    {
        $table = trim($table);
        if ($table === '') {
            return null;
        }

        foreach ($this->tables() as $knownTable) {
            $name = (string) ($knownTable['name'] ?? '');
            if (hash_equals($name, $table)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function tableInfo(string $table): array
    {
        $table = $this->requireTable($table);
        $columns = $this->columns($table);
        $primaryKey = $this->primaryKey($table);

        return [
            'name' => $table,
            'columns' => $columns,
            'indexes' => $this->indexes($table),
            'foreign_keys' => $this->foreignKeys($table),
            'primary_key' => $primaryKey,
            'can_edit_rows' => $primaryKey !== [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function browse(string $table, array $input = []): array
    {
        $table = $this->requireTable($table);
        $columns = $this->columns($table);
        $columnNames = array_values(array_map(static fn (array $column): string => (string) $column['name'], $columns));
        $primaryKey = $this->primaryKey($table);
        $page = max(1, (int) ($input['db_page'] ?? 1));
        $perPage = max(10, min(100, (int) ($input['per_page'] ?? self::PAGE_SIZE)));
        $search = $this->shortString((string) ($input['search'] ?? ''), 120);
        $sort = in_array((string) ($input['sort'] ?? ''), $columnNames, true) ? (string) $input['sort'] : '';
        $dir = strtolower((string) ($input['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $countTotal = (string) ($input['count_total'] ?? '') === '1';

        $query = DB::table($table);
        if ($search !== '' && $columnNames !== []) {
            $query->where(function ($nested) use ($columnNames, $search): void {
                foreach ($columnNames as $column) {
                    $nested->orWhere($column, 'like', '%'.$this->escapeLike($search).'%');
                }
            });
        }

        $total = null;
        $lastPage = null;
        if ($countTotal) {
            $total = (int) (clone $query)->count();
            $lastPage = max(1, (int) ceil($total / $perPage));
        }

        if ($sort !== '') {
            $query->orderBy($sort, $dir);
        } elseif ($primaryKey !== []) {
            foreach ($primaryKey as $column) {
                $query->orderBy($column);
            }
        }

        $rows = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get()
            ->map(fn (object $row): array => $this->rowPayload((array) $row, $primaryKey))
            ->all();
        $hasMore = count($rows) > $perPage;
        if ($hasMore) {
            $rows = array_slice($rows, 0, $perPage);
        }
        if ($countTotal && $lastPage !== null) {
            $hasMore = $page < $lastPage;
        }

        return [
            'table' => $table,
            'columns' => $columns,
            'primary_key' => $primaryKey,
            'can_edit_rows' => $primaryKey !== [],
            'rows' => $rows,
            'total' => $total,
            'total_known' => $countTotal,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'has_more' => $hasMore,
            'count_total' => $countTotal,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{status?:string,error?:string,message?:string}
     */
    public function createRow(string $table, array $input): array
    {
        $table = $this->requireTable($table);
        $attributes = $this->rowAttributes($table, $input, true);
        if ($attributes === []) {
            return ['error' => 'row_empty'];
        }

        try {
            DB::table($table)->insert($attributes);
            $this->record('database.row_created', $table, null, null, $attributes, [
                'columns' => array_keys($attributes),
            ]);

            return ['status' => 'row_created'];
        } catch (Throwable $exception) {
            return ['error' => 'write_failed', 'message' => $this->shortString($exception->getMessage(), 220)];
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array{status?:string,error?:string,message?:string}
     */
    public function updateRow(string $table, string $encodedKey, array $input): array
    {
        $table = $this->requireTable($table);
        $primaryKey = $this->primaryKey($table);
        if ($primaryKey === []) {
            return ['error' => 'primary_key_missing'];
        }

        $key = $this->decodeRowKey($encodedKey, $primaryKey);
        if ($key === null) {
            return ['error' => 'row_key_invalid'];
        }

        $before = $this->findRowByKey($table, $key);
        if ($before === null) {
            return ['error' => 'row_not_found'];
        }

        $attributes = $this->rowAttributes($table, $input, false);
        if ($attributes === []) {
            return ['error' => 'row_empty'];
        }

        try {
            $updated = $this->wherePrimary(DB::table($table), $key)->update($attributes);
            $after = $this->findRowByKey($table, $this->keyAfterUpdate($key, $attributes));
            $this->record('database.row_updated', $table, $encodedKey, $before, $after ?? $attributes, [
                'affected_rows' => $updated,
                'columns' => array_keys($attributes),
            ]);

            return ['status' => 'row_updated'];
        } catch (Throwable $exception) {
            return ['error' => 'write_failed', 'message' => $this->shortString($exception->getMessage(), 220)];
        }
    }

    /**
     * @return array{status?:string,error?:string,message?:string}
     */
    public function deleteRow(string $table, string $encodedKey, bool $confirmed): array
    {
        $table = $this->requireTable($table);
        $primaryKey = $this->primaryKey($table);
        if ($primaryKey === []) {
            return ['error' => 'primary_key_missing'];
        }
        if (! $confirmed) {
            return ['error' => 'confirm_required'];
        }

        $key = $this->decodeRowKey($encodedKey, $primaryKey);
        if ($key === null) {
            return ['error' => 'row_key_invalid'];
        }

        $before = $this->findRowByKey($table, $key);
        if ($before === null) {
            return ['error' => 'row_not_found'];
        }

        try {
            $deleted = $this->wherePrimary(DB::table($table), $key)->delete();
            $this->record('database.row_deleted', $table, $encodedKey, $before, null, [
                'affected_rows' => $deleted,
            ]);

            return ['status' => 'row_deleted'];
        } catch (Throwable $exception) {
            return ['error' => 'write_failed', 'message' => $this->shortString($exception->getMessage(), 220)];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function executeSql(string $sql, bool $confirmed): array
    {
        $statements = $this->splitSqlStatements($sql);
        if (count($statements) !== 1) {
            return ['error' => count($statements) === 0 ? 'sql_empty' : 'sql_multiple'];
        }

        $statement = $statements[0];
        $keyword = $this->firstKeyword($statement);
        if ($keyword === '') {
            return ['error' => 'sql_empty'];
        }

        $readOnly = in_array($keyword, ['select', 'show', 'describe', 'desc', 'explain', 'pragma'], true);
        if (! $readOnly && ! $confirmed) {
            return ['error' => 'confirm_required', 'sql' => $sql];
        }
        if (preg_match('/\b(outfile|dumpfile|load_file)\b/i', $statement) === 1) {
            return ['error' => 'sql_file_operation_denied', 'sql' => $sql];
        }

        try {
            if ($readOnly) {
                $resultRows = array_map(static fn (object|array $row): array => (array) $row, DB::select($statement));
                $displayRows = array_slice($resultRows, 0, self::MAX_QUERY_ROWS);
                $this->record('database.sql_read', null, null, null, null, [
                    'sql' => $this->sqlSummary($statement),
                    'rows' => count($resultRows),
                ]);

                return [
                    'status' => 'sql_read',
                    'sql' => $sql,
                    'columns' => $this->resultColumns($displayRows),
                    'rows' => $displayRows,
                    'row_count' => count($resultRows),
                    'truncated' => count($resultRows) > self::MAX_QUERY_ROWS,
                ];
            }

            $affected = in_array($keyword, ['insert', 'update', 'delete', 'replace'], true)
                ? DB::affectingStatement($statement)
                : (DB::statement($statement) ? null : 0);
            $this->record('database.sql_mutation', null, null, null, null, [
                'sql' => $this->sqlSummary($statement),
                'keyword' => $keyword,
                'affected_rows' => $affected,
            ]);

            return [
                'status' => 'sql_mutation',
                'sql' => $sql,
                'affected_rows' => $affected,
                'keyword' => $keyword,
            ];
        } catch (Throwable $exception) {
            return [
                'error' => 'sql_failed',
                'sql' => $sql,
                'message' => $this->shortString($exception->getMessage(), 260),
            ];
        }
    }

    /**
     * @return array{status?:string,error?:string,path?:string,filename?:string,table_count?:int,row_count?:int,message?:string}
     */
    public function exportSql(?string $table = null): array
    {
        if (! $this->supportsSqlDump()) {
            return ['error' => 'export_unsupported'];
        }

        $tables = $table !== null && trim($table) !== ''
            ? [$this->requireTable($table)]
            : array_values(array_map(static fn (array $row): string => (string) $row['name'], $this->tables()));
        if ($tables === []) {
            return ['error' => 'table_missing'];
        }

        $base = tempnam(sys_get_temp_dir(), 'recdb_');
        if ($base === false) {
            return ['error' => 'export_failed'];
        }
        @unlink($base);
        $path = $base.'.sql';
        $handle = @fopen($path, 'wb');
        if (! is_resource($handle)) {
            return ['error' => 'export_failed'];
        }

        $rowCount = 0;
        try {
            fwrite($handle, "-- REC database export\n");
            fwrite($handle, '-- Connection: '.$this->driver()."\n");
            fwrite($handle, '-- Generated: '.gmdate('Y-m-d H:i:s')." UTC\n\n");
            foreach ($tables as $tableName) {
                fwrite($handle, "\n-- Table: ".$tableName."\n");
                $create = $this->createTableSql($tableName);
                if ($create !== '') {
                    fwrite($handle, rtrim($create, ";\r\n").";\n\n");
                }

                $columns = array_values(array_map(static fn (array $column): string => (string) $column['name'], $this->columns($tableName)));
                if ($columns === []) {
                    continue;
                }
                $query = DB::table($tableName);
                foreach ($this->primaryKey($tableName) as $keyColumn) {
                    $query->orderBy($keyColumn);
                }
                foreach ($query->cursor() as $row) {
                    $values = (array) $row;
                    fwrite($handle, 'INSERT INTO '.$this->quoteIdentifier($tableName).' ('
                        .implode(', ', array_map($this->quoteIdentifier(...), $columns))
                        .') VALUES ('
                        .implode(', ', array_map(fn (string $column): string => $this->sqlLiteral($values[$column] ?? null), $columns))
                        .");\n");
                    $rowCount++;
                }
                fwrite($handle, "\n");
            }
        } catch (Throwable $exception) {
            fclose($handle);
            @unlink($path);

            return ['error' => 'export_failed', 'message' => $this->shortString($exception->getMessage(), 220)];
        }
        fclose($handle);

        $filename = 'rec-database-'.($table !== null && trim($table) !== '' ? preg_replace('/[^A-Za-z0-9_-]+/', '-', $tables[0]) : 'all').'-'.date('Ymd-His').'.sql';
        $this->record('database.sql_exported', null, null, null, null, [
            'tables' => $tables,
            'table_count' => count($tables),
            'row_count' => $rowCount,
            'bytes' => is_file($path) ? filesize($path) : null,
        ]);

        return [
            'status' => 'exported',
            'path' => $path,
            'filename' => $filename,
            'table_count' => count($tables),
            'row_count' => $rowCount,
        ];
    }

    /**
     * @return array{status?:string,error?:string,message?:string,statement_count?:int}
     */
    public function importSql(?UploadedFile $file, bool $confirmed): array
    {
        if (! $this->supportsSqlDump()) {
            return ['error' => 'import_unsupported'];
        }
        if (! $confirmed) {
            return ['error' => 'confirm_required'];
        }
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return ['error' => 'import_missing_file'];
        }
        if ($file->getSize() !== null && $file->getSize() > self::MAX_IMPORT_BYTES) {
            return ['error' => 'import_file_too_large'];
        }
        if (strtolower($file->getClientOriginalExtension()) !== 'sql') {
            return ['error' => 'import_invalid_file_type'];
        }

        $contents = file_get_contents($file->getRealPath());
        if (! is_string($contents) || trim($contents) === '') {
            return ['error' => 'import_empty'];
        }

        $statements = $this->splitSqlStatements($contents);
        if ($statements === []) {
            return ['error' => 'import_empty'];
        }

        try {
            $runner = function () use ($statements): void {
                foreach ($statements as $statement) {
                    DB::unprepared($statement);
                }
            };
            if (in_array($this->driver(), ['sqlite', 'pgsql', 'sqlsrv'], true)) {
                DB::transaction($runner);
            } else {
                $runner();
            }
        } catch (Throwable $exception) {
            return ['error' => 'import_failed', 'message' => $this->shortString($exception->getMessage(), 260)];
        }

        $this->record('database.sql_imported', null, null, null, null, [
            'statement_count' => count($statements),
            'bytes' => $file->getSize(),
            'filename' => $file->getClientOriginalName(),
        ]);

        return ['status' => 'imported', 'statement_count' => count($statements)];
    }

    /**
     * @return array<int, string>
     */
    public function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($lineComment) {
                $buffer .= $char;
                if ($char === "\n") {
                    $lineComment = false;
                }
                continue;
            }

            if ($blockComment) {
                $buffer .= $char;
                if ($char === '*' && $next === '/') {
                    $buffer .= $next;
                    $i++;
                    $blockComment = false;
                }
                continue;
            }

            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $next !== '') {
                    $buffer .= $next;
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    if ($next === $quote) {
                        $buffer .= $next;
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }

            if (($char === '-' && $next === '-') || $char === '#') {
                $lineComment = true;
                $buffer .= $char;
                if ($char === '-') {
                    $buffer .= $next;
                    $i++;
                }
                continue;
            }
            if ($char === '/' && $next === '*') {
                $blockComment = true;
                $buffer .= $char.$next;
                $i++;
                continue;
            }
            if (in_array($char, ["'", '"', '`'], true)) {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                $statement = trim($buffer);
                if ($this->statementHasSql($statement)) {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ($this->statementHasSql($statement)) {
            $statements[] = $statement;
        }

        return $statements;
    }

    private function driver(): string
    {
        return strtolower((string) DB::connection()->getDriverName());
    }

    private function supportsSqlDump(): bool
    {
        return in_array($this->driver(), ['mysql', 'mariadb', 'sqlite'], true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fallbackTables(): array
    {
        try {
            if ($this->driver() === 'sqlite') {
                return array_map(static fn (object $row): array => ['name' => (string) $row->name, 'type' => 'table'], DB::select(
                    "select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name"
                ));
            }
            if (in_array($this->driver(), ['mysql', 'mariadb'], true)) {
                return array_map(static fn (object $row): array => ['name' => (string) array_values((array) $row)[0], 'type' => 'table'], DB::select('show full tables where Table_type = ?', ['BASE TABLE']));
            }
        } catch (Throwable) {
            return [];
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function columns(string $table): array
    {
        if (array_key_exists($table, $this->columnCache)) {
            return $this->columnCache[$table];
        }

        try {
            $columns = Schema::getColumns($table);
        } catch (Throwable) {
            $columns = array_map(static fn (string $name): array => ['name' => $name], Schema::getColumnListing($table));
        }

        return $this->columnCache[$table] = array_values(array_map(static fn (array $column): array => [
            'name' => trim((string) ($column['name'] ?? '')),
            'type' => trim((string) ($column['type'] ?? $column['type_name'] ?? '')),
            'type_name' => trim((string) ($column['type_name'] ?? '')),
            'nullable' => (bool) ($column['nullable'] ?? false),
            'default' => array_key_exists('default', $column) ? $column['default'] : null,
            'auto_increment' => (bool) ($column['auto_increment'] ?? false),
            'comment' => $column['comment'] ?? null,
        ], array_filter($columns, static fn (array $column): bool => trim((string) ($column['name'] ?? '')) !== '')));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function indexes(string $table): array
    {
        if (array_key_exists($table, $this->indexCache)) {
            return $this->indexCache[$table];
        }

        try {
            return $this->indexCache[$table] = array_values(Schema::getIndexes($table));
        } catch (Throwable) {
            return $this->indexCache[$table] = [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function foreignKeys(string $table): array
    {
        if (array_key_exists($table, $this->foreignKeyCache)) {
            return $this->foreignKeyCache[$table];
        }

        try {
            return $this->foreignKeyCache[$table] = array_values(Schema::getForeignKeys($table));
        } catch (Throwable) {
            return $this->foreignKeyCache[$table] = [];
        }
    }

    /**
     * @return array<int, string>
     */
    private function primaryKey(string $table): array
    {
        if (array_key_exists($table, $this->primaryKeyCache)) {
            return $this->primaryKeyCache[$table];
        }

        foreach ($this->indexes($table) as $index) {
            if ((bool) ($index['primary'] ?? false)) {
                return $this->primaryKeyCache[$table] = array_values(array_map('strval', $index['columns'] ?? []));
            }
        }

        return $this->primaryKeyCache[$table] = [];
    }

    private function requireTable(string $table): string
    {
        $normalized = $this->normalizeTable($table);
        if ($normalized === null) {
            throw new \InvalidArgumentException('Table tidak valid.');
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $primaryKey
     * @return array{values:array<string, mixed>,key:?string}
     */
    private function rowPayload(array $row, array $primaryKey): array
    {
        $key = null;
        if ($primaryKey !== []) {
            $keyValues = [];
            foreach ($primaryKey as $column) {
                if (! array_key_exists($column, $row)) {
                    $keyValues = [];
                    break;
                }
                $keyValues[$column] = $row[$column];
            }
            if ($keyValues !== []) {
                $key = $this->encodeRowKey($keyValues);
            }
        }

        return ['values' => $row, 'key' => $key];
    }

    /**
     * @param array<string, mixed> $values
     */
    private function encodeRowKey(array $values): string
    {
        return rtrim(strtr(base64_encode((string) json_encode($values)), '+/', '-_'), '=');
    }

    /**
     * @param array<int, string> $primaryKey
     * @return array<string, mixed>|null
     */
    private function decodeRowKey(string $encodedKey, array $primaryKey): ?array
    {
        $encoded = strtr($encodedKey, '-_', '+/');
        $padding = strlen($encoded) % 4;
        if ($padding > 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            return null;
        }

        $values = json_decode($decoded, true);
        if (! is_array($values)) {
            return null;
        }

        $key = [];
        foreach ($primaryKey as $column) {
            if (! array_key_exists($column, $values)) {
                return null;
            }
            $key[$column] = $values[$column];
        }

        return $key;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function rowAttributes(string $table, array $input, bool $forInsert): array
    {
        $values = is_array($input['values'] ?? null) ? $input['values'] : [];
        $nulls = is_array($input['nulls'] ?? null) ? $input['nulls'] : [];
        $attributes = [];

        foreach ($this->columns($table) as $column) {
            $name = (string) $column['name'];
            if (! array_key_exists($name, $values)) {
                continue;
            }
            $value = $values[$name];
            if (array_key_exists($name, $nulls)) {
                $attributes[$name] = null;
                continue;
            }
            if ($forInsert && (bool) ($column['auto_increment'] ?? false) && trim((string) $value) === '') {
                continue;
            }
            $attributes[$name] = is_array($value) ? json_encode($value) : (string) $value;
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $key
     */
    private function wherePrimary(\Illuminate\Database\Query\Builder $query, array $key): \Illuminate\Database\Query\Builder
    {
        foreach ($key as $column => $value) {
            $query->where($column, $value);
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $key
     * @return array<string, mixed>|null
     */
    private function findRowByKey(string $table, array $key): ?array
    {
        $row = $this->wherePrimary(DB::table($table), $key)->first();

        return $row !== null ? (array) $row : null;
    }

    /**
     * @param array<string, mixed> $key
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function keyAfterUpdate(array $key, array $attributes): array
    {
        foreach ($key as $column => $value) {
            if (array_key_exists($column, $attributes)) {
                $key[$column] = $attributes[$column];
            }
        }

        return $key;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function resultColumns(array $rows): array
    {
        return $rows !== [] ? array_keys($rows[0]) : [];
    }

    private function firstKeyword(string $statement): string
    {
        $statement = $this->stripLeadingComments($statement);
        if (preg_match('/^([A-Za-z_]+)/', ltrim($statement), $matches) !== 1) {
            return '';
        }

        return strtolower($matches[1]);
    }

    private function stripLeadingComments(string $statement): string
    {
        $statement = ltrim($statement);
        do {
            $original = $statement;
            $statement = preg_replace('/^--[^\r\n]*(\r\n|\r|\n)?/', '', $statement) ?? $statement;
            $statement = preg_replace('/^#[^\r\n]*(\r\n|\r|\n)?/', '', $statement) ?? $statement;
            $statement = preg_replace('/^\/\*.*?\*\//s', '', $statement) ?? $statement;
            $statement = ltrim($statement);
        } while ($statement !== $original);

        return $statement;
    }

    private function statementHasSql(string $statement): bool
    {
        return trim($this->stripLeadingComments($statement)) !== '';
    }

    private function createTableSql(string $table): string
    {
        if (in_array($this->driver(), ['mysql', 'mariadb'], true)) {
            $row = (array) DB::selectOne('SHOW CREATE TABLE '.$this->quoteIdentifier($table));

            return (string) ($row['Create Table'] ?? array_values($row)[1] ?? '');
        }
        if ($this->driver() === 'sqlite') {
            $row = DB::selectOne("select sql from sqlite_master where type in ('table', 'view') and name = ?", [$table]);

            return (string) ($row->sql ?? '');
        }

        return '';
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (in_array($this->driver(), ['mysql', 'mariadb'], true)) {
            return '`'.str_replace('`', '``', $identifier).'`';
        }

        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function sqlLiteral(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'".str_replace("'", "''", (string) $value)."'";
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function sqlSummary(string $sql): string
    {
        return $this->shortString(preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql), 500);
    }

    private function shortString(string $value, int $max): string
    {
        $value = trim($value);

        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     * @param array<string, mixed> $metadata
     */
    private function record(string $action, ?string $table, ?string $rowKey, ?array $before, ?array $after, array $metadata = []): void
    {
        $this->activity->record(
            'database',
            $action,
            $table !== null ? 'database_table' : 'database',
            $rowKey,
            $table,
            'Developer database admin: '.$action.($table !== null ? ' '.$table : ''),
            $before,
            $after,
            $metadata,
        );
    }
}
