<?php

$page = 'discipleship_dashboard';
page_header('Dashboard Pemuridan', $settings, $page, false, 'page-discipleship-dashboard');
$branchParam = $allBranches ? 'all' : ($selectedBranchId ?? 'all');
$formatPercent = static function (float $value): string {
    $label = number_format(max(0, min(100, $value)), 1, ',', '.');

    return str_ends_with($label, ',0') ? substr($label, 0, -2) : $label;
};
?>

@if (request()->has('msk_session_saved'))
  <div class="alert success">Progress sesi MSK berhasil diperbarui.</div>
@endif
@if (request()->has('converted'))
  <div class="alert success">Peserta yang menyelesaikan 12 sesi otomatis ditambahkan ke data pemuridan.</div>
@endif
@if (request()->query('error') === 'invalid_msk_participant')
  <div class="alert danger">Data peserta kelas MSK tidak ditemukan.</div>
@endif
@if (! $centralReadOnly)
  <?php render_pemuridan_import_feedback(); ?>
@endif

<section class="card discipleship-dashboard-hero-card">
  <div class="discipleship-dashboard-hero-head">
    <div class="discipleship-dashboard-hero-copy">
      <span class="discipleship-dashboard-hero-kicker">Dashboard Pemuridan</span>
      <h2>Monitor Pemuridan <span class="discipleship-dashboard-hero-branch">REC {{ $selectedBranchLabel }}</span></h2>
      <p>Ringkasan capaian target, kesehatan kelompok, dan area yang perlu segera ditindaklanjuti.</p>
      <p class="discipleship-dashboard-hero-actions"><a class="btn" href="/">Buka Halaman Public Link</a></p>
    </div>
    <div class="discipleship-dashboard-hero-summary">
      <div class="discipleship-dashboard-hero-summary-ring" style="--pct:{{ $overallProgress }};"><span>{{ $formatPercent($overallProgress) }}%</span></div>
      <div class="discipleship-dashboard-hero-summary-copy">
        <span class="discipleship-dashboard-hero-summary-label">Rata-rata Achievement</span>
        <strong class="discipleship-dashboard-hero-summary-value">{{ $formatPercent($overallProgress) }}%</strong>
        <span class="discipleship-dashboard-hero-summary-sub">Gabungan capaian MSK, DG, Kamp GAP, dan RG.</span>
      </div>
    </div>
  </div>
</section>

<section class="card discipleship-dashboard-progress-card">
  <div class="card-row discipleship-dashboard-section-head"><h2>Achievement Target</h2><span class="badge muted">Ringkasan terkini</span></div>
  <div class="journey-progress-grid journey-progress-grid-standalone">
    @foreach ($journeyProgressRows as $row)
      <?php $percent = $row['target'] > 0 ? min(100, ($row['value'] / $row['target']) * 100) : 0; ?>
      <div class="journey-progress-chip">
        <div class="journey-progress-ring" style="--pct:{{ $percent }};--ring-color:{{ $row['color'] }};"><span>{{ $formatPercent($percent) }}%</span></div>
        <div class="journey-progress-copy"><div class="journey-progress-label">{{ $row['label'] }}</div><div class="journey-progress-value">{{ number_format($row['value'], 0, ',', '.') }} / {{ number_format($row['target'], 0, ',', '.') }}</div></div>
      </div>
    @endforeach
  </div>

  <div class="card-row discipleship-dashboard-section-head"><h2>Kelompok Berjalan</h2><span class="badge muted">Kelompok aktif</span></div>
  <div class="journey-progress-grid journey-progress-grid-standalone">
    @foreach ($groupProgressRows as $row)
      <?php $percent = $row['target'] > 0 ? min(100, ($row['value'] / $row['target']) * 100) : 0; ?>
      <div class="journey-progress-chip">
        <div class="journey-progress-ring" style="--pct:{{ $percent }};--ring-color:{{ $row['color'] }};"><span>{{ $formatPercent($percent) }}%</span></div>
        <div class="journey-progress-copy"><div class="journey-progress-label">{{ $row['label'] }}</div><div class="journey-progress-value">{{ number_format($row['value'], 0, ',', '.') }} / {{ number_format($row['target'], 0, ',', '.') }} kelompok aktif</div></div>
      </div>
    @endforeach
  </div>

  <div class="discipleship-dashboard-data-stats">
    @foreach ($summaryStats as $stat)
      <article class="discipleship-dashboard-data-stat {{ $stat['tone'] }}">
        <span class="discipleship-dashboard-data-stat-label">{{ $stat['label'] }}</span>
        <strong class="discipleship-dashboard-data-stat-value">{{ number_format($stat['value'], 0, ',', '.') }}</strong>
        <span class="discipleship-dashboard-data-stat-sub">{{ $stat['sub'] }}</span>
      </article>
    @endforeach
  </div>
</section>

@if ($allBranches)
  <section class="card dashboard-lazy-shell" data-dashboard-section data-section-url="{{ route('discipleship.dashboard.section', ['section' => 'branch-breakdown', 'branch_id' => $branchParam]) }}">
    <div class="dashboard-section-loading" role="status"><span class="dashboard-loading-bar"></span><span>Memuat rincian tiap cabang...</span></div>
  </section>
@endif

<section class="member-pie-grid discipleship-progress-overdue-grid">
  <article class="card member-pie-card discipleship-overdue-card is-msk dashboard-lazy-shell" data-dashboard-section data-section-url="{{ route('discipleship.dashboard.section', ['section' => 'incomplete-msk', 'branch_id' => $branchParam]) }}" data-auto-edit-id="{{ request()->query('edit_msk_sessions', '') }}">
    <div class="discipleship-overdue-head"><div><span class="discipleship-overdue-kicker">Tindak Lanjut MSK</span><h2>Belum Selesai MSK</h2><p>Peserta yang masih perlu pemantauan lanjutan.</p></div></div>
    <div class="dashboard-section-loading" role="status"><span class="dashboard-loading-bar"></span><span>Memuat peserta...</span></div>
  </article>
  <article class="card member-pie-card discipleship-overdue-card is-report dashboard-lazy-shell" data-dashboard-section data-section-url="{{ route('discipleship.dashboard.section', ['section' => 'overdue-groups', 'branch_id' => $branchParam]) }}">
    <div class="discipleship-overdue-head"><div><span class="discipleship-overdue-kicker">Tindak Lanjut Jurnal Temu DG</span><h2>Belum Lapor DG 30 Hari Terakhir</h2><p>Kelompok yang belum mengirim laporan dalam 30 hari terakhir.</p></div></div>
    <div class="dashboard-section-loading" role="status"><span class="dashboard-loading-bar"></span><span>Memuat kelompok...</span></div>
  </article>
</section>

@if (! $centralReadOnly)
  <div class="modal" id="discipleship-msk-edit-modal" data-msk-edit-modal aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-card member-view-modal-card">
      <div class="modal-head">
        <div class="modal-title" data-msk-edit-title>Edit Sesi MSK</div>
        <button class="btn tiny ghost" type="button" data-msk-edit-close aria-label="Tutup">&times;</button>
      </div>
      <div class="modal-body" data-msk-edit-body></div>
    </div>
  </div>
@endif

<script>
(function () {
  var modal = document.querySelector('[data-msk-edit-modal]');

  function sectionError(section) {
    section.removeAttribute('aria-busy');
    section.innerHTML = '<div class="dashboard-section-error"><strong>Data belum dapat dimuat.</strong><button class="btn tiny secondary" type="button" data-dashboard-section-retry>Coba lagi</button></div>';
  }

  function openMskEditor(template) {
    if (!modal || !template) return;
    modal.querySelector('[data-msk-edit-title]').textContent = template.getAttribute('data-msk-edit-template-title') || 'Edit Sesi MSK';
    modal.querySelector('[data-msk-edit-body]').innerHTML = template.innerHTML;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
  }

  function closeMskEditor() {
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
  }

  function openRequestedEditor(section) {
    var id = section.getAttribute('data-auto-edit-id') || '';
    if (id === '') return;
    var button = section.querySelector('[data-msk-edit-open="' + CSS.escape(id) + '"]');
    section.removeAttribute('data-auto-edit-id');
    if (button) button.click();
  }

  function loadSection(section, url) {
    section.setAttribute('aria-busy', 'true');
    fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'}})
      .then(function (response) {
        if (!response.ok) throw new Error('section');
        return response.text();
      })
      .then(function (html) {
        section.innerHTML = html;
        section.removeAttribute('aria-busy');
        section.setAttribute('data-section-url', url);
        openRequestedEditor(section);
      })
      .catch(function () { sectionError(section); });
  }

  document.querySelectorAll('[data-dashboard-section]').forEach(function (section) {
    loadSection(section, section.getAttribute('data-section-url'));
  });

  document.addEventListener('click', function (event) {
    var pagination = event.target.closest('[data-dashboard-section-link]');
    if (pagination) {
      event.preventDefault();
      var section = pagination.closest('[data-dashboard-section]');
      if (section) loadSection(section, pagination.href);
      return;
    }

    var retry = event.target.closest('[data-dashboard-section-retry]');
    if (retry) {
      var retrySection = retry.closest('[data-dashboard-section]');
      if (retrySection) loadSection(retrySection, retrySection.getAttribute('data-section-url'));
      return;
    }

    var edit = event.target.closest('[data-msk-edit-open]');
    if (edit && modal) {
      var template = document.querySelector('template[data-msk-edit-template="' + CSS.escape(edit.getAttribute('data-msk-edit-open')) + '"]');
      if (!template) return;
      event.preventDefault();
      openMskEditor(template);
      return;
    }

    if (event.target.closest('[data-msk-edit-close]') && modal) {
      closeMskEditor();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && modal) {
      closeMskEditor();
    }
  });
})();
</script>

<?php page_footer(); ?>
