@extends('layouts.rec_app', [
    'title' => 'Rekap Jurnal Umpan Balik Anggota',
    'settings' => $settings,
    'currentPage' => 'member_feedback_recap',
    'showTitle' => false,
    'bodyClass' => 'page-discipleship-table-scroll page-member-feedback-recap',
])

@section('content')
  @php
      $summary = is_array($summary ?? null) ? $summary : [];
      $sectionScores = is_array($section_scores ?? null) ? $section_scores : [];
      $questionScores = is_array($question_scores ?? null) ? $question_scores : [];
      $coverageRows = is_array($coverage ?? null) ? $coverage : [];
      $groupRows = is_array($group_rows ?? null) ? $group_rows : [];
      $noteRows = is_array($note_rows ?? null) ? $note_rows : [];
      $detailRows = is_array($detail_rows ?? null) ? $detail_rows : [];
      $filters = is_array($filters ?? null) ? $filters : [];
      $totalRows = count($detailRows);
      $perPage = max(1, min(100, (int) request()->query('per_page', 25)));
      $currentPage = max(1, (int) request()->query('feedback_page', 1));
      $totalPages = max(1, (int) ceil($totalRows / $perPage));
      if ($currentPage > $totalPages) {
          $currentPage = $totalPages;
      }
      $offset = ($currentPage - 1) * $perPage;
      $detailPageRows = array_slice($detailRows, $offset, $perPage);
      $pageHref = function (int $targetPage) use ($totalPages, $perPage): string {
          $params = request()->query();
          unset($params['page']);
          $params['feedback_page'] = max(1, min($totalPages, $targetPage));
          $params['per_page'] = $perPage;

          return route('discipleship.member-feedback-recap', $params).'#member-feedback-recap-detail';
      };
      $scoreLabel = function ($value): string {
          if (! is_numeric($value)) {
              return '-';
          }

          return number_format((float) $value, 1, ',', '.');
      };
      $percentLabel = fn ($value): string => (string) max(0, min(100, (int) round((float) $value))).'%';
      $progressKey = fn (string $value): string => strtolower(str_replace(' ', '', normalize_dg_progress_value($value) ?: $value));
  @endphp

  @include('discipleship.partials.page-header', [
      'header' => [
          'kicker' => 'Umpan Balik Anggota',
          'title' => 'Rekap Feedback Anggota',
          'description' => 'Pantau kesehatan kelompok DG dari jurnal umpan balik anggota, mulai dari skor tiap dimensi, coverage pengisian, prioritas tindak lanjut, sampai catatan detail.',
          'stats_aria_label' => 'Ringkasan rekap feedback anggota',
          'stats' => [
              ['label' => 'Total Jurnal', 'value' => (string) ($summary['total_journals'] ?? 0)],
              ['label' => 'Skor Rata-rata', 'value' => $scoreLabel($summary['overall_score'] ?? 0).'/10'],
              ['label' => 'Kelompok Terisi', 'value' => (string) ($summary['feedback_group_count'] ?? 0)],
              ['label' => 'Coverage Aktif', 'value' => $percentLabel($summary['coverage_percent'] ?? 0)],
              ['label' => 'Update Terakhir', 'value' => format_datetime_id((string) ($summary['latest_submitted_at'] ?? ''))],
          ],
          'tools' => [
              'element' => 'div',
              'attributes' => ['class' => 'table-tools member-feedback-recap-tools'],
              'partial' => 'discipleship.partials.page-header-controls.member-feedback-recap',
              'data' => compact('filters'),
          ],
      ],
  ])

  @if ($totalRows === 0)
    <section class="card member-feedback-recap-empty">
      <div>
        <span class="member-feedback-recap-kicker">Belum Ada Data</span>
        <h2>Belum ada jurnal umpan balik anggota pada scope ini.</h2>
        <p>Bagikan form publik kepada anggota DG pada pertemuan 3 dan 12 agar rekap mulai terisi.</p>
      </div>
      <a class="btn secondary" href="{{ route('public.member-feedback.branch') }}">Buka Form Publik</a>
    </section>
  @endif

  <section class="member-feedback-recap-score-grid" aria-label="Skor dimensi feedback anggota">
    @foreach ($sectionScores as $section)
      @php
          $score = (float) ($section['score'] ?? 0);
          $scorePercent = max(0, min(100, $score * 10));
          $directionalScore = $section['directional_score'] ?? null;
          $balanceScore = $section['balance_score'] ?? null;
      @endphp
      <article class="card member-feedback-recap-score-card">
        <div class="member-feedback-recap-score-ring" style="--score-percent: {{ $scorePercent }}%;">
          <strong>{{ $scoreLabel($score) }}</strong>
          <span>/10</span>
        </div>
        <div class="member-feedback-recap-score-copy">
          <span class="member-feedback-recap-kicker">{{ (string) ($section['label'] ?? '-') }}</span>
          <h2>{{ $scoreLabel($score) }} dari 10</h2>
          <div class="member-feedback-recap-score-meta">
            <span>{{ (string) ($section['rating_count'] ?? 0) }} rating</span>
            <span>{{ (string) ($section['note_count'] ?? 0) }} catatan</span>
          </div>
          @if ($directionalScore !== null || $balanceScore !== null)
            <div class="member-feedback-recap-score-sub">
              @if ($directionalScore !== null)<span>Kepuasan {{ $scoreLabel($directionalScore) }}</span>@endif
              @if ($balanceScore !== null)<span>Keseimbangan {{ $scoreLabel($balanceScore) }}</span>@endif
            </div>
          @endif
        </div>
      </article>
    @endforeach
  </section>

  <section class="member-feedback-recap-panel-grid">
    <article class="card member-feedback-recap-panel">
      <div class="member-feedback-recap-panel-head">
        <div>
          <span class="member-feedback-recap-kicker">Coverage</span>
          <h2>Pengisian per Pertemuan</h2>
        </div>
        <span class="member-feedback-recap-muted">{{ (string) ($summary['active_member_count'] ?? 0) }} anggota aktif</span>
      </div>
      <div class="member-feedback-recap-coverage-list">
        @foreach ($coverageRows as $coverageRow)
          @php $coveragePercent = max(0, min(100, (int) ($coverageRow['percent'] ?? 0))); @endphp
          <div class="member-feedback-recap-coverage-row">
            <div class="member-feedback-recap-coverage-copy">
              <strong>{{ (string) ($coverageRow['label'] ?? '-') }}</strong>
              <span>{{ (string) ($coverageRow['submitted'] ?? 0) }} dari {{ (string) ($coverageRow['total'] ?? 0) }} anggota aktif</span>
            </div>
            <div class="member-feedback-recap-coverage-track" aria-label="{{ $coveragePercent }} persen">
              <span style="width: {{ $coveragePercent }}%"></span>
            </div>
            <strong class="member-feedback-recap-coverage-percent">{{ $coveragePercent }}%</strong>
          </div>
        @endforeach
      </div>
    </article>

    <article class="card member-feedback-recap-panel">
      <div class="member-feedback-recap-panel-head">
        <div>
          <span class="member-feedback-recap-kicker">Prioritas</span>
          <h2>Skor Pertanyaan Terendah</h2>
        </div>
        <span class="member-feedback-recap-muted">Top {{ (string) min(6, count($questionScores)) }}</span>
      </div>
      <div class="member-feedback-recap-question-list">
        @forelse (array_slice($questionScores, 0, 6) as $question)
          @php
              $questionScore = (float) ($question['score'] ?? 0);
              $questionPercent = max(0, min(100, $questionScore * 10));
          @endphp
          <div class="member-feedback-recap-question-row">
            <div>
              <span>{{ (string) ($question['section_label'] ?? '-') }}{{ ! empty($question['is_balance']) ? ' - Keseimbangan' : '' }}</span>
              <strong>{{ (string) ($question['label'] ?? '-') }}</strong>
            </div>
            <div class="member-feedback-recap-question-score">
              <span style="width: {{ $questionPercent }}%"></span>
            </div>
            <strong>{{ $scoreLabel($questionScore) }}</strong>
          </div>
        @empty
          <p class="panel-note">Belum ada skor pertanyaan yang bisa dihitung.</p>
        @endforelse
      </div>
    </article>
  </section>

  <section class="card member-feedback-recap-notes-card">
    <div class="member-feedback-recap-panel-head">
      <div>
        <span class="member-feedback-recap-kicker">Catatan Tematik</span>
        <h2>Masukan Anggota per Dimensi</h2>
      </div>
      <span class="member-feedback-recap-muted">Nama pengisi disembunyikan di bagian ini</span>
    </div>
    <div class="member-feedback-recap-note-grid">
      @forelse ($noteRows as $note)
        <article class="member-feedback-recap-note">
          <div class="member-feedback-recap-note-head">
            <span>{{ (string) ($note['section_label'] ?? 'Catatan') }}</span>
            <strong>{{ (string) ($note['branch_label'] ?? '-') }}</strong>
          </div>
          <p>{{ (string) ($note['content'] ?? '') }}</p>
          <div class="member-feedback-recap-note-meta">
            <span>{{ (string) ($note['group_progress'] ?? '-') }}</span>
            <span>{{ (string) ($note['leader_name'] ?? '-') }}</span>
            <span>{{ format_datetime_id((string) ($note['submitted_at'] ?? '')) }}</span>
          </div>
        </article>
      @empty
        <p class="panel-note">Belum ada catatan tertulis dari anggota pada scope ini.</p>
      @endforelse
    </div>
  </section>

  <section class="card member-feedback-recap-group-card">
    <div class="member-feedback-recap-panel-head">
      <div>
        <span class="member-feedback-recap-kicker">Kelompok</span>
        <h2>Kelompok yang Sudah Memiliki Feedback</h2>
      </div>
      <span class="member-feedback-recap-muted">{{ (string) count($groupRows) }} kelompok</span>
    </div>
    <div class="member-feedback-recap-group-grid">
      @forelse (array_slice($groupRows, 0, 8) as $group)
        @php $groupScore = (float) ($group['score'] ?? 0); @endphp
        <article class="member-feedback-recap-group-item">
          <div>
            <span>{{ (string) ($group['branch_label'] ?? '-') }} - {{ (string) ($group['group_progress'] ?? '-') }}</span>
            <strong>{{ (string) ($group['leader_name'] ?? '-') }}</strong>
            <small>{{ (string) ($group['group_name'] ?? 'Kelompok') }}</small>
          </div>
          <div class="member-feedback-recap-group-side">
            <strong>{{ $scoreLabel($groupScore) }}</strong>
            <span>P3 {{ (string) ($group['session_3_count'] ?? 0) }} - P12 {{ (string) ($group['session_12_count'] ?? 0) }}</span>
          </div>
        </article>
      @empty
        <p class="panel-note">Belum ada kelompok dengan feedback pada scope ini.</p>
      @endforelse
    </div>
  </section>

  <section class="card member-feedback-recap-detail-card" id="member-feedback-recap-detail">
    <div class="member-feedback-recap-panel-head">
      <div>
        <span class="member-feedback-recap-kicker">Detail Jurnal</span>
        <h2>Daftar Jurnal Umpan Balik Anggota</h2>
      </div>
      <span class="member-feedback-recap-muted">{{ (string) $totalRows }} jurnal</span>
    </div>
    <div class="table-wrap member-feedback-recap-table-wrap">
      <table class="table member-feedback-recap-table" id="member-feedback-recap-detail-table">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Cabang</th>
            <th>Sesi</th>
            <th>Progress</th>
            <th>Pemimpin / Kelompok</th>
            <th>Pengisi</th>
            <th>Skor</th>
            <th>Catatan</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($detailPageRows as $row)
            <tr data-member-feedback-progress="{{ $progressKey((string) ($row['group_progress'] ?? '')) }}" data-member-feedback-session="{{ (string) ($row['feedback_session'] ?? '') }}">
              <td>{{ format_datetime_id((string) ($row['submitted_at'] ?? '')) }}</td>
              <td>{{ (string) ($row['branch_label'] ?? '-') }}</td>
              <td>{{ (string) ($row['session_label'] ?? '-') }}</td>
              <td><span class="group-progress-badge is-{{ $progressKey((string) ($row['group_progress'] ?? '')) }}">{{ (string) ($row['group_progress'] ?? '-') }}</span></td>
              <td class="member-feedback-recap-main-cell">
                <strong>{{ (string) ($row['leader_name'] ?? '-') }}</strong>
                <span>{{ (string) ($row['group_name'] ?? 'Kelompok') }}</span>
              </td>
              <td>{{ (string) ($row['respondent_name'] ?? '-') }}</td>
              <td><span class="member-feedback-recap-score-pill">{{ $row['score'] !== null ? $scoreLabel($row['score']) : '-' }}</span></td>
              <td class="member-feedback-recap-note-cell">{{ (string) ($row['note_summary'] ?? '-') }}</td>
            </tr>
          @empty
            <tr><td colspan="8">Belum ada jurnal umpan balik anggota pada scope ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($totalPages > 1)
      <div class="member-feedback-recap-pagination">
        <a class="btn tiny ghost" href="{{ $pageHref($currentPage - 1) }}" @if ($currentPage <= 1) aria-disabled="true" @endif>Sebelumnya</a>
        <span>Halaman {{ (string) $currentPage }} dari {{ (string) $totalPages }}</span>
        <a class="btn tiny ghost" href="{{ $pageHref($currentPage + 1) }}" @if ($currentPage >= $totalPages) aria-disabled="true" @endif>Berikutnya</a>
      </div>
    @endif
  </section>
@endsection
