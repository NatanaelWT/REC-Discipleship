<section
  class="discipleship-tab-panel discipleship-workspace__panel discipleship-journal-panel member-feedback-recap-panel"
  id="discipleship-tabpanel-feedback"
  role="tabpanel"
  aria-labelledby="discipleship-tab-feedback"
  tabindex="0"
  data-discipleship-tab-panel
  data-member-feedback-recap-panel
  data-tab-key="feedback"
  data-page-title="{{ $pageTitle ?? 'Jurnal Umpan Balik' }}"
  data-body-class="page-member-feedback-recap"
>
  @php
      $summary = is_array($summary ?? null) ? $summary : [];
      $groupRows = is_array($group_rows ?? null) ? $group_rows : [];
      $detailRows = is_array($detail_rows ?? null) ? $detail_rows : [];
      $filters = is_array($filters ?? null) ? $filters : [];
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
      $memberFeedbackProgressFilterCounts = ['all' => count($groupRows), 'dg1' => 0, 'dg2' => 0, 'dg3' => 0];
      foreach ($groupRows as $groupRow) {
          $feedbackProgressKey = $progressKey((string) ($groupRow['group_progress'] ?? ''));
          if (isset($memberFeedbackProgressFilterCounts[$feedbackProgressKey])) {
              $memberFeedbackProgressFilterCounts[$feedbackProgressKey]++;
          }
      }
  @endphp

  @include('discipleship.partials.page-header', [
      'header' => [
          'tools' => [
              'element' => 'div',
              'attributes' => ['class' => 'table-tools member-feedback-recap-tools'],
              'partial' => 'discipleship.partials.page-header-controls.member-feedback-recap',
              'data' => compact('filters', 'memberFeedbackProgressFilterCounts'),
          ],
      ],
  ])

  <section class="card dg-recap-section-card member-feedback-recap-group-card">
    <div class="table-wrap member-feedback-recap-group-table-wrap" data-member-feedback-summary-scroll data-table-horizontal-scroll>
      <table class="table member-feedback-recap-group-table" id="member-feedback-recap-group-table">
        <caption class="table-caption-accessible">Pengisi Feedback per Kelompok - {{ (string) count($groupRows) }} kelompok aktif</caption>
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

  @include('partials.modal', [
      'id' => 'member-feedback-group-modal',
      'size' => 'standard',
      'modalAttrs' => ['data-member-feedback-group-modal' => true],
      'title' => 'Feedback Kelompok',
      'titleAttrs' => ['data-member-feedback-group-title' => true],
      'closeAttrs' => ['data-member-feedback-group-close' => true],
      'closeLabel' => 'Tutup',
      'bodyClass' => 'member-feedback-recap-session-modal-body',
      'bodyAttrs' => ['data-member-feedback-group-body' => true],
      'bodyHtml' => '<p class="panel-note">Klik jumlah pengisi pada sesi 3 atau sesi 12 untuk melihat feedback kelompok.</p>',
  ])

  @include('partials.modal', [
      'id' => 'member-feedback-detail-modal',
      'size' => 'standard',
      'modalAttrs' => ['data-member-feedback-detail-modal' => true],
      'title' => 'Detail Feedback',
      'titleAttrs' => ['data-member-feedback-detail-title' => true],
      'closeAttrs' => ['data-member-feedback-detail-close' => true],
      'closeLabel' => 'Tutup',
      'bodyClass' => 'member-feedback-recap-modal-body',
      'bodyAttrs' => ['data-member-feedback-detail-body' => true],
      'bodyHtml' => '<p class="panel-note">Pilih tombol Detail pada daftar feedback untuk melihat isi lengkapnya.</p>',
  ])
</section>
