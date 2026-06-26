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
      $feedbackRowKey = fn (array $row): string => (string) ($row['id'] ?? md5(json_encode($row) ?: 'feedback'));
      $sectionShortLabels = [
          'leadership' => 'Kepemimpinan',
          'meeting' => 'Teknis',
          'teaching' => 'Pengajaran',
          'personal_growth' => 'Pertumbuhan',
          'relationships' => 'Relasi',
      ];
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
          $sectionKey = (string) ($section['section_key'] ?? '');
          $sectionLabel = (string) ($section['label'] ?? '-');
          $sectionShortLabel = $sectionShortLabels[$sectionKey] ?? $sectionLabel;
      @endphp
      <article class="card member-feedback-recap-score-card">
        <div class="member-feedback-recap-score-title">
          <span class="member-feedback-recap-kicker" title="{{ $sectionLabel }}">{{ $sectionShortLabel }}</span>
          <h2>{{ $scoreLabel($score) }}/10</h2>
        </div>
        <div class="member-feedback-recap-score-ring" style="--score-percent: {{ $scorePercent }}%;">
          <strong>{{ $scoreLabel($score) }}</strong>
          <span>/10</span>
        </div>
        <div class="member-feedback-recap-score-copy">
          <div class="member-feedback-recap-score-meta">
            <span>{{ (string) ($section['rating_count'] ?? 0) }} rating</span>
            <span>{{ (string) ($section['note_count'] ?? 0) }} catatan</span>
          </div>
          @if ($directionalScore !== null || $balanceScore !== null)
            <div class="member-feedback-recap-score-sub">
              @if ($directionalScore !== null)<span>Puas {{ $scoreLabel($directionalScore) }}</span>@endif
              @if ($balanceScore !== null)<span>Seimbang {{ $scoreLabel($balanceScore) }}</span>@endif
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
        <colgroup>
          <col class="member-feedback-recap-col-date">
          <col class="member-feedback-recap-col-branch">
          <col class="member-feedback-recap-col-session">
          <col class="member-feedback-recap-col-progress">
          <col class="member-feedback-recap-col-group">
          <col class="member-feedback-recap-col-respondent">
          <col class="member-feedback-recap-col-score">
          <col class="member-feedback-recap-col-note">
          <col class="member-feedback-recap-col-action">
        </colgroup>
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
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($detailPageRows as $row)
            @php $detailKey = $feedbackRowKey($row); @endphp
            <tr data-member-feedback-progress="{{ $progressKey((string) ($row['group_progress'] ?? '')) }}" data-member-feedback-session="{{ (string) ($row['feedback_session'] ?? '') }}">
              <td>{{ format_datetime_id((string) ($row['submitted_at'] ?? '')) }}</td>
              <td>{{ (string) ($row['branch_label'] ?? '-') }}</td>
              <td>{{ (string) ($row['session_label'] ?? '-') }}</td>
              <td><span class="group-progress-badge is-{{ $progressKey((string) ($row['group_progress'] ?? '')) }}">{{ (string) ($row['group_progress'] ?? '-') }}</span></td>
              <td>
                <div class="member-feedback-recap-main-cell">
                  <strong>{{ (string) ($row['leader_name'] ?? '-') }}</strong>
                  <span>{{ (string) ($row['group_name'] ?? 'Kelompok') }}</span>
                </div>
              </td>
              <td>{{ (string) ($row['respondent_name'] ?? '-') }}</td>
              <td><span class="member-feedback-recap-score-pill">{{ $row['score'] !== null ? $scoreLabel($row['score']) : '-' }}</span></td>
              <td><span class="member-feedback-recap-note-cell">{{ (string) ($row['note_summary'] ?? '-') }}</span></td>
              <td>
                <button class="btn tiny ghost member-feedback-recap-detail-button" type="button" data-member-feedback-detail-open="{{ $detailKey }}">
                  Detail
                </button>
              </td>
            </tr>
          @empty
            <tr><td colspan="9">Belum ada jurnal umpan balik anggota pada scope ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @foreach ($detailPageRows as $row)
      @php
          $detailKey = $feedbackRowKey($row);
          $ratingRows = is_array($row['rating_rows'] ?? null) ? $row['rating_rows'] : [];
          $noteDetailRows = is_array($row['note_rows'] ?? null) ? $row['note_rows'] : [];
      @endphp
      <template data-member-feedback-detail-template="{{ $detailKey }}" data-member-feedback-detail-template-title="Feedback {{ (string) ($row['respondent_name'] ?? '-') }}">
        <div class="member-feedback-recap-modal-summary">
          <div><span>Tanggal</span><strong>{{ format_datetime_id((string) ($row['submitted_at'] ?? '')) }}</strong></div>
          <div><span>Cabang</span><strong>{{ (string) ($row['branch_label'] ?? '-') }}</strong></div>
          <div><span>Sesi</span><strong>{{ (string) ($row['session_label'] ?? '-') }}</strong></div>
          <div><span>Progress</span><strong>{{ (string) ($row['group_progress'] ?? '-') }}</strong></div>
          <div><span>Pemimpin</span><strong>{{ (string) ($row['leader_name'] ?? '-') }}</strong></div>
          <div><span>Kelompok</span><strong>{{ (string) ($row['group_name'] ?? '-') }}</strong></div>
          <div><span>Pengisi</span><strong>{{ (string) ($row['respondent_name'] ?? '-') }}</strong></div>
          <div><span>Skor</span><strong>{{ $row['score'] !== null ? $scoreLabel($row['score']).'/10' : '-' }}</strong></div>
        </div>

        <section class="member-feedback-recap-modal-section">
          <div class="member-feedback-recap-modal-section-head">
            <h3>Skor Pertanyaan</h3>
            <span>{{ (string) count($ratingRows) }} rating</span>
          </div>
          <div class="member-feedback-recap-modal-rating-list">
            @forelse ($ratingRows as $rating)
              @php
                  $normalizedScore = is_numeric($rating['normalized_score'] ?? null) ? (float) $rating['normalized_score'] : 0.0;
                  $normalizedPercent = max(0, min(100, $normalizedScore * 10));
              @endphp
              <article class="member-feedback-recap-modal-rating">
                <div>
                  <span>{{ (string) ($rating['section_label'] ?? '-') }} - {{ (string) ($rating['type_label'] ?? 'Skor') }}</span>
                  <strong>{{ (string) ($rating['label'] ?? '-') }}</strong>
                </div>
                <div class="member-feedback-recap-modal-rating-score">
                  <strong>Jawaban {{ (string) ($rating['score'] ?? '-') }} / {{ (string) ($rating['scale'] ?? '-') }}</strong>
                  <span>{{ $scoreLabel($normalizedScore) }}/10</span>
                  <div class="member-feedback-recap-modal-rating-bar"><i style="width: {{ $normalizedPercent }}%"></i></div>
                </div>
              </article>
            @empty
              <p class="panel-note">Belum ada rating yang tersimpan untuk jurnal ini.</p>
            @endforelse
          </div>
        </section>

        <section class="member-feedback-recap-modal-section">
          <div class="member-feedback-recap-modal-section-head">
            <h3>Catatan Tertulis</h3>
            <span>{{ (string) count($noteDetailRows) }} catatan</span>
          </div>
          <div class="member-feedback-recap-modal-note-list">
            @forelse ($noteDetailRows as $note)
              <article class="member-feedback-recap-modal-note">
                <span>{{ (string) ($note['section_label'] ?? 'Catatan') }}</span>
                <strong>{{ (string) ($note['label'] ?? 'Catatan') }}</strong>
                <p>{{ (string) ($note['content'] ?? '') }}</p>
              </article>
            @empty
              <p class="panel-note">Tidak ada catatan tertulis pada jurnal ini.</p>
            @endforelse
          </div>
        </section>
      </template>
    @endforeach

    @if ($totalPages > 1)
      <div class="member-feedback-recap-pagination">
        <a class="btn tiny ghost" href="{{ $pageHref($currentPage - 1) }}" @if ($currentPage <= 1) aria-disabled="true" @endif>Sebelumnya</a>
        <span>Halaman {{ (string) $currentPage }} dari {{ (string) $totalPages }}</span>
        <a class="btn tiny ghost" href="{{ $pageHref($currentPage + 1) }}" @if ($currentPage >= $totalPages) aria-disabled="true" @endif>Berikutnya</a>
      </div>
    @endif
  </section>

  <div class="modal" id="member-feedback-detail-modal" data-member-feedback-detail-modal aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-card member-feedback-recap-modal-card">
      <div class="modal-head">
        <div class="modal-title" data-member-feedback-detail-title>Detail Feedback</div>
        <button class="btn tiny ghost" type="button" data-member-feedback-detail-close>Tutup</button>
      </div>
      <div class="modal-body member-feedback-recap-modal-body" data-member-feedback-detail-body>
        <p class="panel-note">Pilih tombol Detail pada tabel untuk melihat isi feedback.</p>
      </div>
    </div>
  </div>
@endsection
