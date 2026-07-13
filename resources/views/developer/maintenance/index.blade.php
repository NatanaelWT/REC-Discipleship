@extends('layouts.rec_app', [
    'title' => 'Developer Maintenance',
    'settings' => $settings,
    'currentPage' => 'developer_config',
    'bodyClass' => 'page-developer page-developer-maintenance',
    'showTitle' => false,
])

@section('content')
  @php
    $latestLegacyValidationRun = $runs->first(static fn ($run) => in_array(
        data_get($run->summary, 'activity_retention.legacy_validation_status'),
        ['passed', 'failed'],
        true,
    ));
    $latestLegacyValidationStatus = data_get($latestLegacyValidationRun?->summary, 'activity_retention.legacy_validation_status');
  @endphp
  @include('developer._header', [
    'title' => 'Maintenance Data',
    'description' => 'Jalankan rollup, migrasi activity legacy, replay spool, dan retensi data secara aman dan bertahap.',
    'eyebrow' => 'Resumable Maintenance',
    'stats' => [
      ['label' => 'Retensi Raw', 'value' => config('activity.retention_days', 90).' hari'],
      ['label' => 'Mode Aplikasi', 'value' => app_maintenance_mode_enabled() ? 'Maintenance' : 'Normal'],
      ['label' => 'Run Aktif', 'value' => $activeRun?->status ?? 'Tidak ada'],
      ['label' => 'Task', 'value' => number_format(count($preview), 0, ',', '.')],
    ],
  ])

  @if ($errorCode === 'password_invalid')
    <div class="alert danger">Password developer tidak cocok.</div>
  @elseif ($errorCode === 'maintenance_required')
    <div class="alert danger">Aktifkan maintenance mode dari halaman Config sebelum menjalankan perubahan data.</div>
  @elseif ($errorCode === 'run_mode_mismatch')
    <div class="alert danger">Mode run aktif berbeda. Lanjutkan dengan tombol yang sesuai atau selesaikan run aktif terlebih dahulu.</div>
  @elseif ($errorCode === 'idempotency_conflict')
    <div class="alert danger">Token idempotensi sudah dipakai oleh run dengan pengguna atau mode berbeda.</div>
  @elseif ($errorCode === 'start_busy')
    <div class="alert danger">Run lain sedang dibuat. Coba lagi beberapa saat.</div>
  @elseif ($statusCode === 'started')
    <div class="alert success">Run dikonfirmasi. Browser akan melanjutkan batch secara otomatis.</div>
  @elseif ($statusCode === 'quarantine_restored')
    <div class="alert success">Media berhasil dipulihkan dari quarantine ke lokasi asalnya.</div>
  @elseif ($errorCode === 'quarantine_restore_failed')
    <div class="alert danger">Media tidak dapat dipulihkan. File mungkin berubah, sudah dipulihkan, atau lokasi asal sudah terisi.</div>
  @elseif ($statusCode === 'quarantine_deleted')
    <div class="alert success">Media quarantine yang melewati masa tunggu telah dihapus permanen.</div>
  @elseif ($errorCode === 'quarantine_delete_failed')
    <div class="alert danger">Media tidak dihapus. Masa tunggu, checksum, atau referensi database belum memenuhi syarat.</div>
  @endif

  @if (! app_maintenance_mode_enabled())
    <div class="alert danger">Dry-run tetap tersedia, tetapi perubahan data hanya dapat dijalankan saat maintenance mode aktif.</div>
  @endif
  @if ($maintenanceOverdue)
    <div class="alert danger">
      Maintenance mutasi {{ $lastCompletedMutation ? 'terakhir selesai '.$lastCompletedMutation->completed_at?->setTimezone(app_timezone())->diffForHumans() : 'belum pernah berhasil dijalankan' }}.
      Jalankan maintenance agar rollup, spool, dan retensi tidak tertinggal lebih dari tujuh hari.
    </div>
  @endif
  @if ($latestLegacyValidationStatus === 'passed')
    <div class="alert success">
      Validasi cutover legacy lulus pada {{ data_get($latestLegacyValidationRun?->summary, 'activity_retention.legacy_validated_at', '-') }}.
      Aplikasi tidak me-rename atau menghapus tabel <code>aktivitas</code> secara otomatis. Setelah snapshot database cocok dan release tervalidasi, rename tabel itu menjadi tabel rollback dan pertahankan minimal tujuh hari sebelum penghapusan terpisah.
    </div>
  @elseif ($latestLegacyValidationStatus === 'failed')
    <div class="alert danger">Validasi cutover legacy gagal. Jangan rename atau drop tabel <code>aktivitas</code>; periksa ringkasan run dan jalankan ulang setelah selisih diperbaiki.</div>
  @endif

  <section class="card developer-panel developer-section-card">
    <div class="developer-section-head">
      <span class="developer-section-icon">@include('developer._icon', ['name' => 'config'])</span>
      <div><span class="developer-section-kicker">Safety gate</span><h2>Mulai atau Lanjutkan Run</h2><p>Password diperlukan. Setiap request bekerja maksimal {{ max(1, min(10, (int) config('activity.maintenance.batch_seconds', 8))) }} detik dan dapat dilanjutkan setelah browser ditutup.</p></div>
    </div>

    <form method="post" action="{{ route('developer.maintenance.start') }}" class="developer-form-grid">
      @csrf
      <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
      <label>
        <span>Password developer saat ini</span>
        <input type="password" name="current_password" required autocomplete="current-password" maxlength="255">
      </label>
      <div class="developer-form-actions">
        <button class="btn ghost" type="submit" name="dry_run" value="1">Dry-run</button>
        <button class="btn developer-primary-action" type="submit">{{ $activeRun ? 'Lanjutkan Run' : 'Jalankan Maintenance' }}</button>
      </div>
    </form>
  </section>

  <section class="card table-card-plain developer-panel developer-section-card">
    <div class="developer-section-head">
      <span class="developer-section-icon is-blue">@include('developer._icon', ['name' => 'statistics'])</span>
      <div><span class="developer-section-kicker">Dry-run otomatis</span><h2>Inventaris Saat Ini</h2><p>Angka ini hanya membaca state dan tidak mengubah data.</p></div>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Task</th><th>Detail</th></tr></thead>
        <tbody>
          @forelse ($preview as $task)
            <tr>
              <td><strong>{{ $task['label'] }}</strong><small>{{ $task['key'] }}</small></td>
              <td><pre>{{ json_encode($task['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></td>
            </tr>
          @empty
            <tr><td colspan="2">Belum ada task maintenance terdaftar.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="card table-card-plain developer-panel developer-section-card">
    <div class="developer-section-head">
      <span class="developer-section-icon is-slate">@include('developer._icon', ['name' => 'activities'])</span>
      <div><span class="developer-section-kicker">Quarantine {{ config('media.quarantine_days', 30) }} hari</span><h2>Pemulihan Media</h2><p>File dapat dipulihkan kapan saja. Penghapusan permanen baru tersedia setelah masa tunggu, saat maintenance mode aktif, serta memerlukan password dan konfirmasi eksplisit.</p></div>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Asal</th><th>Ukuran</th><th>Dikarantina</th><th>Tindakan</th></tr></thead>
        <tbody>
          @forelse ($quarantineEntries as $entry)
            <tr>
              <td><strong>{{ $entry['source'] }}</strong><small>{{ $entry['target'] }}</small></td>
              <td>{{ format_file_size((int) $entry['size']) }}</td>
              <td>{{ $entry['quarantined_at'] ?: '-' }}<small>Review setelah {{ $entry['delete_after'] ?: '-' }}</small></td>
              <td>
                <form method="post" action="{{ route('developer.maintenance.quarantine.restore') }}" class="developer-inline-form">
                  @csrf
                  <input type="hidden" name="quarantine_path" value="{{ $entry['target'] }}">
                  <input type="password" name="current_password" required autocomplete="current-password" maxlength="255" placeholder="Password">
                  <button class="btn tiny secondary" type="submit">Pulihkan</button>
                </form>
                @if (app_maintenance_mode_enabled() && ($entry['deletable'] ?? false))
                  <form method="post" action="{{ route('developer.maintenance.quarantine.delete') }}" class="developer-inline-form">
                    @csrf
                    <input type="hidden" name="quarantine_path" value="{{ $entry['target'] }}">
                    <input type="password" name="current_password" required autocomplete="current-password" maxlength="255" placeholder="Password">
                    <input type="text" name="confirmation" required autocomplete="off" placeholder="HAPUS PERMANEN">
                    <button class="btn tiny danger" type="submit">Hapus permanen</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="4">Tidak ada media aktif di quarantine.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  @if ($activeRun)
    <section class="card developer-panel developer-section-card"
      data-maintenance-runner
      data-batch-url="{{ route('developer.maintenance.batch', ['maintenanceRun' => $activeRun->getKey()]) }}"
      data-confirmed="{{ $activeRunConfirmed ? '1' : '0' }}">
      <div class="developer-section-head">
        <span class="developer-section-icon is-slate">@include('developer._icon', ['name' => 'activities'])</span>
        <div><span class="developer-section-kicker">Run {{ $activeRun->id }}</span><h2>Status: <span data-maintenance-status>{{ $activeRun->status }}</span></h2><p data-maintenance-message>{{ $activeRunConfirmed ? 'Menyiapkan batch berikutnya…' : 'Masukkan password untuk melanjutkan run ini.' }}</p></div>
      </div>
      <pre data-maintenance-summary>{{ json_encode($activeRun->summary ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </section>
  @endif

  <section class="card table-card-plain developer-panel developer-section-card">
    <div class="developer-section-head"><div><span class="developer-section-kicker">Audit operasional</span><h2>10 Run Terakhir</h2></div></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Waktu</th><th>Developer</th><th>Mode</th><th>Status</th><th>Ringkasan / Error</th></tr></thead>
        <tbody>
          @forelse ($runs as $run)
            <tr>
              <td>{{ $run->created_at?->setTimezone(app_timezone())->format('d-m-Y H:i:s') ?? '-' }}</td>
              <td>{{ $run->requested_by_username }}</td>
              <td>{{ $run->dry_run ? 'Dry-run' : 'Mutasi' }}</td>
              <td><strong>{{ $run->status }}</strong></td>
              <td><small>{{ $run->error_message ?: json_encode($run->summary ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</small></td>
            </tr>
          @empty
            <tr><td colspan="5">Belum ada run maintenance.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  @if ($activeRun && $activeRunConfirmed)
    <script>
      (() => {
        const root = document.querySelector('[data-maintenance-runner]');
        if (!root || root.dataset.confirmed !== '1') return;
        const status = root.querySelector('[data-maintenance-status]');
        const message = root.querySelector('[data-maintenance-message]');
        const summary = root.querySelector('[data-maintenance-summary]');
        let stopped = false;

        const next = async () => {
          if (stopped) return;
          try {
            const response = await fetch(root.dataset.batchUrl, {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': @json(csrf_token()),
              },
            });
            const payload = await response.json();
            status.textContent = payload.status || (response.ok ? 'running' : 'failed');
            summary.textContent = JSON.stringify(payload.summary || {}, null, 2);
            if (!response.ok) {
              stopped = true;
              message.textContent = payload.message || 'Batch gagal.';
              return;
            }
            if (payload.status === 'completed' || payload.status === 'failed') {
              stopped = true;
              message.textContent = payload.status === 'completed' ? 'Maintenance selesai.' : 'Maintenance gagal.';
              window.setTimeout(() => window.location.reload(), 900);
              return;
            }
            message.textContent = `Menjalankan ${payload.task || 'task berikutnya'}…`;
            window.setTimeout(next, 250);
          } catch (error) {
            stopped = true;
            message.textContent = 'Koneksi terputus. Muat ulang halaman untuk melanjutkan.';
          }
        };

        next();
      })();
    </script>
  @endif
@endsection
