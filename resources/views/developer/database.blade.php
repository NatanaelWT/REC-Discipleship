@extends('layouts.rec_app', [
    'title' => 'Developer Database',
    'settings' => $settings,
    'currentPage' => 'developer_database',
    'bodyClass' => 'page-developer page-developer-database',
    'showTitle' => false,
])

@section('content')
  @php
    $tables = collect(is_array($tables ?? null) ? $tables : []);
    $summary = is_array($summary ?? null) ? $summary : [];
    $tableInfo = is_array($tableInfo ?? null) ? $tableInfo : null;
    $browse = is_array($browse ?? null) ? $browse : null;
    $selectedTable = trim((string) ($selectedTable ?? ''));
    $activeTab = in_array((string) ($activeTab ?? 'browse'), ['browse', 'structure', 'sql', 'export', 'import'], true) ? (string) $activeTab : 'browse';
    if ($selectedTable === '' && in_array($activeTab, ['browse', 'structure'], true)) {
      $activeTab = 'sql';
    }
    $columns = is_array($tableInfo['columns'] ?? null) ? $tableInfo['columns'] : [];
    $primaryKey = is_array($tableInfo['primary_key'] ?? null) ? $tableInfo['primary_key'] : [];
    $canEditRows = (bool) ($tableInfo['can_edit_rows'] ?? false);
    $statusMessages = [
      'row_created' => 'Row berhasil ditambahkan.',
      'row_updated' => 'Row berhasil diperbarui.',
      'row_deleted' => 'Row berhasil dihapus.',
      'imported' => 'Import SQL selesai.',
    ];
    $valueText = static function (mixed $value, int $max = 140): string {
      if ($value === null) {
        return 'NULL';
      }
      if (is_bool($value)) {
        return $value ? '1' : '0';
      }
      if (is_array($value) || is_object($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
      }
      $value = (string) $value;
      if (function_exists('mb_strlen') && mb_strlen($value) > $max) {
        return mb_substr($value, 0, $max).'...';
      }
      if (! function_exists('mb_strlen') && strlen($value) > $max) {
        return substr($value, 0, $max).'...';
      }

      return $value;
    };
    $formValue = static function (mixed $value): string {
      if ($value === null) {
        return '';
      }
      if (is_array($value) || is_object($value)) {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
      }

      return (string) $value;
    };
    $tabUrl = function (string $tab) use ($selectedTable): string {
      return $selectedTable !== ''
        ? route('developer.database.table', ['table' => $selectedTable, 'tab' => $tab])
        : route('developer.database', ['tab' => $tab]);
    };
    $browseUrl = function (array $params = []) use ($selectedTable, $browse): string {
      $query = [
        'table' => $selectedTable,
        'tab' => 'browse',
        'search' => (string) ($browse['search'] ?? ''),
        'sort' => (string) ($browse['sort'] ?? ''),
        'dir' => (string) ($browse['dir'] ?? 'asc'),
      ];
      if (! empty($browse['count_total'])) {
        $query['count_total'] = '1';
      }
      $query = array_merge($query, $params);
      foreach (['search', 'sort', 'dir', 'count_total'] as $key) {
        if (($query[$key] ?? '') === '') {
          unset($query[$key]);
        }
      }

      return route('developer.database.table', $query);
    };
  @endphp

  @include('developer._header', [
    'title' => 'Database Admin',
    'description' => 'Kelola tabel database aktif dari aplikasi. Gunakan dengan hati-hati karena perubahan langsung berlaku.',
    'eyebrow' => 'Developer Database',
    'stats' => [
      ['label' => 'Connection', 'value' => $summary['connection'] ?? '-'],
      ['label' => 'Driver', 'value' => $summary['driver'] ?? '-'],
      ['label' => 'Database', 'value' => $summary['database'] ?? '-'],
      ['label' => 'Tabel', 'value' => number_format((int) ($summary['table_count'] ?? 0), 0, ',', '.')],
    ],
  ])

  @if (($statusCode ?? '') !== '' && isset($statusMessages[$statusCode]))
    <div class="alert success">{{ $statusMessages[$statusCode] }}</div>
  @endif
  @if (($errorCode ?? '') !== '')
    <div class="alert danger">{{ ($errorMessages[$errorCode] ?? 'Operasi database ditolak.') }}@if (! empty($message)) <br><small>{{ $message }}</small>@endif</div>
  @elseif (! empty($message))
    <div class="alert danger">{{ $message }}</div>
  @endif

  <section class="developer-db-shell">
    <aside class="card developer-db-sidebar">
      <div class="developer-db-sidebar-head">
        <span class="developer-section-kicker">Tables</span>
        <strong>{{ number_format($tables->count(), 0, ',', '.') }} tabel</strong>
      </div>
      <div class="developer-db-table-list">
        @forelse ($tables as $tableRow)
          @php $tableName = (string) ($tableRow['name'] ?? ''); @endphp
          <a class="developer-db-table-link {{ $tableName === $selectedTable ? 'active' : '' }}" href="{{ route('developer.database.table', ['table' => $tableName]) }}">
            <strong>{{ $tableName }}</strong>
            <small>{{ $tableRow['engine'] ?? ($tableRow['type'] ?? 'table') }}</small>
          </a>
        @empty
          <div class="developer-dashboard-empty">Tidak ada tabel ditemukan.</div>
        @endforelse
      </div>
    </aside>

    <div class="developer-db-main">
      <section class="card developer-panel developer-section-card developer-db-toolbar">
        <div class="developer-section-head">
          <span class="developer-section-icon is-slate">@include('developer._icon', ['name' => 'config'])</span>
          <div>
            <span class="developer-section-kicker">Database</span>
            <h2>{{ $selectedTable !== '' ? $selectedTable : 'Pilih Tabel' }}</h2>
            <p>{{ $selectedTable !== '' ? 'Browse, edit, export, dan cek struktur tabel ini.' : 'Pilih tabel di kiri, atau gunakan SQL/export/import database.' }}</p>
          </div>
        </div>
        <nav class="developer-db-tabs" aria-label="Database admin tabs">
          @if ($selectedTable !== '')
            <a class="{{ $activeTab === 'browse' ? 'active' : '' }}" href="{{ $tabUrl('browse') }}">Browse</a>
            <a class="{{ $activeTab === 'structure' ? 'active' : '' }}" href="{{ $tabUrl('structure') }}">Structure</a>
          @endif
          <a class="{{ $activeTab === 'sql' ? 'active' : '' }}" href="{{ $tabUrl('sql') }}">SQL</a>
          <a class="{{ $activeTab === 'export' ? 'active' : '' }}" href="{{ $tabUrl('export') }}">Export</a>
          <a class="{{ $activeTab === 'import' ? 'active' : '' }}" href="{{ $tabUrl('import') }}">Import</a>
        </nav>
      </section>

      @if ($activeTab === 'browse' && $selectedTable !== '' && $browse !== null)
        <section class="card developer-panel developer-section-card">
          <div class="developer-db-section-head">
            <div><span class="developer-section-kicker">Browse</span><h2>Data Row</h2></div>
            <form method="get" action="{{ route('developer.database.table', ['table' => $selectedTable]) }}" class="developer-db-search">
              <input type="hidden" name="tab" value="browse">
              <input type="search" name="search" value="{{ $browse['search'] ?? '' }}" placeholder="Cari data...">
              <button class="btn tiny ghost" type="submit">Cari</button>
            </form>
          </div>

          <details class="developer-db-editor">
            <summary>Tambah row baru</summary>
            <form method="post" action="{{ route('developer.database.rows.store', ['table' => $selectedTable]) }}" class="developer-db-row-form">
              @csrf
              @foreach ($columns as $column)
                @php
                  $name = (string) ($column['name'] ?? '');
                  $type = (string) ($column['type'] ?? '');
                  $isLong = str_contains(strtolower($type), 'text') || str_contains(strtolower($type), 'json');
                @endphp
                <label>
                  <span>{{ $name }} <small>{{ $type }}</small></span>
                  @if ($isLong)
                    <textarea name="values[{{ $name }}]" rows="3" @if (($column['auto_increment'] ?? false)) placeholder="auto" @endif></textarea>
                  @else
                    <input type="text" name="values[{{ $name }}]" @if (($column['auto_increment'] ?? false)) placeholder="auto" @endif>
                  @endif
                  @if (($column['nullable'] ?? false))
                    <span class="developer-db-null"><input type="checkbox" name="nulls[{{ $name }}]" value="1"> NULL</span>
                  @endif
                </label>
              @endforeach
              <div class="developer-form-actions"><button class="btn developer-primary-action" type="submit">Tambah Row</button></div>
            </form>
          </details>

          @php
            $loadedRows = count($browse['rows'] ?? []);
            $currentPage = (int) ($browse['page'] ?? 1);
            $totalKnown = (bool) ($browse['total_known'] ?? false);
            $hasMoreRows = (bool) ($browse['has_more'] ?? false);
          @endphp
          <div class="developer-db-meta-row">
            @if ($totalKnown)
              <span>{{ number_format((int) ($browse['total'] ?? 0), 0, ',', '.') }} row</span>
              <span>Page {{ number_format($currentPage, 0, ',', '.') }} / {{ number_format((int) ($browse['last_page'] ?? 1), 0, ',', '.') }}</span>
            @else
              <span>{{ number_format($loadedRows, 0, ',', '.') }} row dimuat@if ($hasMoreRows) dari banyak row@endif</span>
              <span>Page {{ number_format($currentPage, 0, ',', '.') }}</span>
              <a class="btn tiny ghost" href="{{ $browseUrl(['db_page' => 1, 'count_total' => '1']) }}">Hitung total</a>
            @endif
            @if (! $canEditRows)<span class="badge warning">Primary key tidak ada: edit/delete nonaktif</span>@endif
          </div>

          <div class="table-wrap developer-db-table-wrap">
            <table class="table developer-db-data-table">
              <thead>
                <tr>
                  @foreach ($columns as $column)
                    @php
                      $name = (string) ($column['name'] ?? '');
                      $nextDir = (($browse['sort'] ?? '') === $name && ($browse['dir'] ?? 'asc') === 'asc') ? 'desc' : 'asc';
                    @endphp
                    <th><a href="{{ $browseUrl(['sort' => $name, 'dir' => $nextDir, 'db_page' => 1]) }}">{{ $name }}</a></th>
                  @endforeach
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                @forelse (($browse['rows'] ?? []) as $row)
                  @php $rowValues = is_array($row['values'] ?? null) ? $row['values'] : []; @endphp
                  <tr>
                    @foreach ($columns as $column)
                      @php $name = (string) ($column['name'] ?? ''); @endphp
                      <td class="{{ ($rowValues[$name] ?? null) === null ? 'is-null' : '' }}">{{ $valueText($rowValues[$name] ?? null) }}</td>
                    @endforeach
                    <td>
                      @if ($canEditRows && ! empty($row['key']))
                        <details class="developer-db-row-actions">
                          <summary>Edit</summary>
                          <form method="post" action="{{ route('developer.database.rows.update', ['table' => $selectedTable, 'key' => $row['key']]) }}" class="developer-db-row-form">
                            @csrf
                            @foreach ($columns as $column)
                              @php
                                $name = (string) ($column['name'] ?? '');
                                $type = (string) ($column['type'] ?? '');
                                $current = $rowValues[$name] ?? null;
                                $isLong = str_contains(strtolower($type), 'text') || str_contains(strtolower($type), 'json') || strlen($formValue($current)) > 90;
                              @endphp
                              <label>
                                <span>{{ $name }} <small>{{ $type }}</small></span>
                                @if ($isLong)
                                  <textarea name="values[{{ $name }}]" rows="3">{{ $formValue($current) }}</textarea>
                                @else
                                  <input type="text" name="values[{{ $name }}]" value="{{ $formValue($current) }}">
                                @endif
                                @if (($column['nullable'] ?? false))
                                  <span class="developer-db-null"><input type="checkbox" name="nulls[{{ $name }}]" value="1" @checked($current === null)> NULL</span>
                                @endif
                              </label>
                            @endforeach
                            <div class="developer-form-actions"><button class="btn secondary" type="submit">Simpan Row</button></div>
                          </form>
                          <form method="post" action="{{ route('developer.database.rows.delete', ['table' => $selectedTable, 'key' => $row['key']]) }}" onsubmit="return confirm('Hapus row ini? Aksi ini langsung mengubah database.')" class="developer-db-delete-form">
                            @csrf
                            <input type="hidden" name="confirm_danger" value="1">
                            <button class="btn ghost" type="submit">Hapus Row</button>
                          </form>
                        </details>
                      @else
                        <span class="muted">Readonly</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="{{ count($columns) + 1 }}">Tidak ada row.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

          @if ($currentPage > 1 || $hasMoreRows)
            <div class="developer-db-pagination">
              @if ($currentPage > 1)
                <a class="btn tiny ghost" href="{{ $browseUrl(['db_page' => $currentPage - 1]) }}">Sebelumnya</a>
              @endif
              @if ($hasMoreRows)
                <a class="btn tiny ghost" href="{{ $browseUrl(['db_page' => $currentPage + 1]) }}">Berikutnya</a>
              @endif
            </div>
          @endif
        </section>
      @elseif ($activeTab === 'structure' && $selectedTable !== '' && $tableInfo !== null)
        <section class="card developer-panel developer-section-card">
          <div class="developer-db-section-head"><div><span class="developer-section-kicker">Structure</span><h2>Kolom</h2></div></div>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Kolom</th><th>Type</th><th>Nullable</th><th>Default</th><th>Auto</th><th>Comment</th></tr></thead>
              <tbody>
                @foreach ($columns as $column)
                  <tr>
                    <td>{{ $column['name'] ?? '' }} @if (in_array($column['name'] ?? '', $primaryKey, true))<span class="badge success">PK</span>@endif</td>
                    <td><code>{{ $column['type'] ?? '' }}</code></td>
                    <td>{{ ($column['nullable'] ?? false) ? 'YES' : 'NO' }}</td>
                    <td><code>{{ $column['default'] ?? '' }}</code></td>
                    <td>{{ ($column['auto_increment'] ?? false) ? 'YES' : 'NO' }}</td>
                    <td>{{ $column['comment'] ?? '' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="developer-db-structure-grid">
            <div class="developer-runtime-panel table-wrap">
              <h3>Indexes</h3>
              <table class="table developer-runtime-table">
                <tbody>
                  @forelse (($tableInfo['indexes'] ?? []) as $index)
                    <tr><th>{{ $index['name'] ?? '-' }}</th><td>{{ implode(', ', $index['columns'] ?? []) }} {{ ($index['primary'] ?? false) ? '(primary)' : '' }} {{ ($index['unique'] ?? false) ? '(unique)' : '' }}</td></tr>
                  @empty
                    <tr><td>Tidak ada index.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
            <div class="developer-runtime-panel table-wrap">
              <h3>Foreign Keys</h3>
              <table class="table developer-runtime-table">
                <tbody>
                  @forelse (($tableInfo['foreign_keys'] ?? []) as $foreignKey)
                    <tr><th>{{ $foreignKey['name'] ?? '-' }}</th><td>{{ implode(', ', $foreignKey['columns'] ?? []) }} &rarr; {{ $foreignKey['foreign_table'] ?? '' }}.{{ implode(', ', $foreignKey['foreign_columns'] ?? []) }}</td></tr>
                  @empty
                    <tr><td>Tidak ada foreign key.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </section>
      @elseif ($activeTab === 'sql')
        <section class="card developer-panel developer-section-card">
          <div class="developer-db-section-head"><div><span class="developer-section-kicker">SQL Console</span><h2>Jalankan SQL</h2></div></div>
          <form method="post" action="{{ route('developer.database.query') }}" class="developer-db-sql-form">
            @csrf
            <input type="hidden" name="table" value="{{ $selectedTable }}">
            <textarea name="sql" rows="8" placeholder="SELECT * FROM {{ $selectedTable !== '' ? $selectedTable : 'nama_tabel' }} LIMIT 50">{{ $sqlInput ?? '' }}</textarea>
            <label class="developer-db-confirm"><input type="checkbox" name="confirm_danger" value="1"> Konfirmasi query mutasi database</label>
            <button class="btn developer-primary-action" type="submit">Jalankan SQL</button>
          </form>

          @if (is_array($sqlResult ?? null))
            @if (isset($sqlResult['error']))
              <div class="alert danger">{{ $errorMessages[$sqlResult['error']] ?? 'SQL ditolak.' }}@if (! empty($sqlResult['message'])) <br><small>{{ $sqlResult['message'] }}</small>@endif</div>
            @elseif (($sqlResult['status'] ?? '') === 'sql_mutation')
              <div class="alert success">Query mutasi berhasil.@if (array_key_exists('affected_rows', $sqlResult) && $sqlResult['affected_rows'] !== null) Affected rows: {{ number_format((int) $sqlResult['affected_rows'], 0, ',', '.') }}.@endif</div>
            @else
              <div class="developer-db-meta-row"><span>{{ number_format((int) ($sqlResult['row_count'] ?? 0), 0, ',', '.') }} row</span>@if ($sqlResult['truncated'] ?? false)<span class="badge warning">Ditampilkan maksimal 200 row</span>@endif</div>
              <div class="table-wrap">
                <table class="table developer-db-data-table">
                  <thead><tr>@foreach (($sqlResult['columns'] ?? []) as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
                  <tbody>
                    @forelse (($sqlResult['rows'] ?? []) as $resultRow)
                      <tr>@foreach (($sqlResult['columns'] ?? []) as $column)<td>{{ $valueText($resultRow[$column] ?? null) }}</td>@endforeach</tr>
                    @empty
                      <tr><td>Query tidak mengembalikan row.</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            @endif
          @endif
        </section>
      @elseif ($activeTab === 'export')
        <section class="card developer-panel developer-section-card">
          <div class="developer-db-section-head"><div><span class="developer-section-kicker">Export</span><h2>Export SQL</h2></div></div>
          @if (! ($summary['export_supported'] ?? false))
            <div class="alert danger">Export SQL belum didukung untuk driver ini.</div>
          @endif
          <form method="get" action="{{ route('developer.database.export') }}" class="developer-db-utility-form">
            <label>
              <span>Tabel</span>
              <select name="table">
                <option value="all">Semua tabel</option>
                @foreach ($tables as $tableRow)
                  @php $tableName = (string) ($tableRow['name'] ?? ''); @endphp
                  <option value="{{ $tableName }}" @selected($tableName === $selectedTable)>{{ $tableName }}</option>
                @endforeach
              </select>
            </label>
            <button class="btn developer-primary-action" type="submit">Download SQL</button>
          </form>
        </section>
      @elseif ($activeTab === 'import')
        <section class="card developer-panel developer-section-card">
          <div class="developer-db-section-head"><div><span class="developer-section-kicker">Import</span><h2>Import SQL</h2></div></div>
          @if (! ($summary['export_supported'] ?? false))
            <div class="alert danger">Import SQL belum didukung untuk driver ini.</div>
          @endif
          <form method="post" action="{{ route('developer.database.import') }}" enctype="multipart/form-data" class="developer-db-utility-form">
            @csrf
            <label>
              <span>File SQL</span>
              <input type="file" name="sql_file" accept=".sql" required>
            </label>
            <label class="developer-db-confirm"><input type="checkbox" name="confirm_danger" value="1" required> Saya paham import SQL akan mengubah database.</label>
            <button class="btn developer-primary-action" type="submit">Import SQL</button>
          </form>
        </section>
      @endif
    </div>
  </section>
@endsection
