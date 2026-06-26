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
      $feedbackRowsByGroupSession = [];
      foreach ($detailRows as $detailRow) {
          $groupSessionKey = (string) ((int) ($detailRow['group_id'] ?? 0)).'-'.(string) ((int) ($detailRow['feedback_session'] ?? 0));
          $feedbackRowsByGroupSession[$groupSessionKey] ??= [];
          $feedbackRowsByGroupSession[$groupSessionKey][] = $detailRow;
      }
      $scoreLabel = function ($value): string {
          if (! is_numeric($value)) {
              return '-';
          }

          return number_format((float) $value, 1, ',', '.');
      };
      $percentLabel = fn ($value): string => (string) max(0, min(100, (int) round((float) $value))).'%';
      $progressKey = fn (string $value): string => strtolower(str_replace(' ', '', normalize_dg_progress_value($value) ?: $value));
      $feedbackRowKey = fn (array $row): string => (string) ($row['id'] ?? md5(json_encode($row) ?: 'feedback'));
      $groupSessionKey = fn ($groupId, int $session): string => (string) ((int) $groupId).'-'.(string) $session;
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
        <h2>Pengisi Feedback per Kelompok</h2>
      </div>
      <span class="member-feedback-recap-muted">{{ (string) count($groupRows) }} kelompok aktif</span>
    </div>
    <div class="member-feedback-recap-group-table-wrap">
      <table class="table member-feedback-recap-group-table" id="member-feedback-recap-group-table">
        <thead>
          <tr>
            <th>Cabang</th>
            <th>Progress</th>
            <th>Pemimpin / Kelompok</th>
            <th>Anggota Aktif</th>
            <th>Sesi 3</th>
            <th>Sesi 12</th>
            <th>Terakhir</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($groupRows as $group)
            @php
                $session3Count = (int) ($group['session_3_count'] ?? 0);
                $session12Count = (int) ($group['session_12_count'] ?? 0);
                $sessionTokens = trim(($session3Count > 0 ? '3 ' : '').($session12Count > 0 ? '12' : ''));
                $session3Key = $groupSessionKey($group['group_id'] ?? 0, 3);
                $session12Key = $groupSessionKey($group['group_id'] ?? 0, 12);
                $groupSearchText = implode(' ', array_map(
                    static fn (array $feedbackRow): string => (string) ($feedbackRow['respondent_name'] ?? '').' '.(string) ($feedbackRow['note_summary'] ?? ''),
                    array_merge($feedbackRowsByGroupSession[$session3Key] ?? [], $feedbackRowsByGroupSession[$session12Key] ?? []),
                ));
            @endphp
            <tr data-member-feedback-progress="{{ $progressKey((string) ($group['group_progress'] ?? '')) }}" data-member-feedback-session="{{ $sessionTokens }}">
              <td>{{ (string) ($group['branch_label'] ?? '-') }}</td>
              <td><span class="group-progress-badge is-{{ $progressKey((string) ($group['group_progress'] ?? '')) }}">{{ (string) ($group['group_progress'] ?? '-') }}</span></td>
              <td>
                <div class="member-feedback-recap-main-cell">
                  <strong>{{ (string) ($group['leader_name'] ?? '-') }}</strong>
                  <span>{{ (string) ($group['group_name'] ?? 'Kelompok') }}</span>
                  @if ($groupSearchText !== '')
                    <small class="member-feedback-recap-search-shadow">{{ $groupSearchText }}</small>
                  @endif
                </div>
              </td>
              <td>{{ (string) ((int) ($group['active_member_count'] ?? 0)) }} orang</td>
              <td>
                @if ($session3Count > 0)
                  <button class="member-feedback-recap-count-pill" type="button" data-member-feedback-group-open="{{ $session3Key }}">{{ (string) $session3Count }} orang</button>
                @else
                  <span class="member-feedback-recap-count-pill is-empty">0 orang</span>
                @endif
              </td>
              <td>
                @if ($session12Count > 0)
                  <button class="member-feedback-recap-count-pill" type="button" data-member-feedback-group-open="{{ $session12Key }}">{{ (string) $session12Count }} orang</button>
                @else
                  <span class="member-feedback-recap-count-pill is-empty">0 orang</span>
                @endif
              </td>
              <td>{{ (string) ($group['latest_submitted_at'] ?? '') !== '' ? format_datetime_id((string) ($group['latest_submitted_at'] ?? '')) : '-' }}</td>
            </tr>
          @empty
            <tr><td colspan="7">Belum ada kelompok aktif pada scope ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  @foreach ($groupRows as $group)
    @foreach ([3, 12] as $sessionNumber)
      @php
          $groupSessionModalKey = $groupSessionKey($group['group_id'] ?? 0, $sessionNumber);
          $groupSessionRows = $feedbackRowsByGroupSession[$groupSessionModalKey] ?? [];
      @endphp
      <template data-member-feedback-group-template="{{ $groupSessionModalKey }}" data-member-feedback-group-template-title="{{ (string) ($group['group_name'] ?? 'Kelompok') }} - Pertemuan {{ (string) $sessionNumber }}">
        <div class="member-feedback-recap-session-list">
          @forelse ($groupSessionRows as $row)
            @php $detailKey = $feedbackRowKey($row); @endphp
            <article class="member-feedback-recap-session-item">
              <div class="member-feedback-recap-session-copy">
                <span>{{ format_datetime_id((string) ($row['submitted_at'] ?? '')) }}</span>
                <strong>{{ (string) ($row['respondent_name'] ?? '-') }}</strong>
                <p>{{ (string) ($row['note_summary'] ?? '-') }}</p>
              </div>
              <div class="member-feedback-recap-session-action">
                <span class="member-feedback-recap-score-pill">{{ $row['score'] !== null ? $scoreLabel($row['score']) : '-' }}</span>
                <button class="btn tiny ghost member-feedback-recap-detail-button" type="button" data-member-feedback-detail-open="{{ $detailKey }}">
                  Detail
                </button>
              </div>
            </article>
          @empty
            <p class="panel-note">Belum ada feedback pada sesi ini.</p>
          @endforelse
        </div>
      </template>
    @endforeach
  @endforeach

  @foreach ($detailRows as $row)
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

  <div class="modal" id="member-feedback-group-modal" data-member-feedback-group-modal aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-card member-feedback-recap-session-modal-card">
      <div class="modal-head">
        <div class="modal-title" data-member-feedback-group-title>Feedback Kelompok</div>
        <button class="btn tiny ghost" type="button" data-member-feedback-group-close>Tutup</button>
      </div>
      <div class="modal-body member-feedback-recap-session-modal-body" data-member-feedback-group-body>
        <p class="panel-note">Klik jumlah pengisi pada sesi 3 atau sesi 12 untuk melihat feedback kelompok.</p>
      </div>
    </div>
  </div>

  <div class="modal" id="member-feedback-detail-modal" data-member-feedback-detail-modal aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-card member-feedback-recap-modal-card">
      <div class="modal-head">
        <div class="modal-title" data-member-feedback-detail-title>Detail Feedback</div>
        <button class="btn tiny ghost" type="button" data-member-feedback-detail-close>Tutup</button>
      </div>
      <div class="modal-body member-feedback-recap-modal-body" data-member-feedback-detail-body>
        <p class="panel-note">Pilih tombol Detail pada daftar feedback untuk melihat isi lengkapnya.</p>
      </div>
    </div>
  </div>
@endsection
