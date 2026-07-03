@php
    $stage = (string) ($profile['current_stage'] ?? '');
    $stageClass = $stage !== '' ? 'is-'.strtolower(str_replace(' ', '', $stage)) : 'is-muted';
    $subtitle = trim((string) ($profile['subtitle'] ?? ''));
    if ($subtitle === '') {
        $subtitle = 'Peserta Kelas MSK';
    }
    $batchBadgeLabel = trim((string) ($profile['batch_badge_label'] ?? ''));
    if ($batchBadgeLabel === '') {
        $batchBadgeLabel = 'Batch '.(string) ($profile['batch'] ?? '-');
    }
    $isExternalContext = ! empty($profile['is_external_context']);
    $renderHistory = static function (array $items): string {
        if ($items === []) {
            return '';
        }
        ob_start();
        echo '<div class="journey-history-timeline">';
        foreach ($items as $item) {
            $itemStage = normalize_dg_progress_value((string) ($item['stage'] ?? ''));
            echo '<article class="journey-history-item">';
            echo '<div class="journey-history-item-head"><div class="journey-history-item-title">'.h((string) ($item['title'] ?? 'Kelompok')).'</div><div class="journey-history-item-date">'.h((string) ($item['date'] ?? '-')).'</div></div>';
            echo '<div class="journey-history-item-meta">';
            if ($itemStage !== '') {
                echo '<span class="journey-track-badge is-'.h(strtolower(str_replace(' ', '', $itemStage))).'">'.h($itemStage).'</span>';
            }
            echo '<span class="journey-history-chip">'.h((string) ($item['role'] ?? '-')).'</span>';
            if (trim((string) ($item['mentor'] ?? '')) !== '') {
                echo '<span class="journey-history-chip">Pembina: '.h((string) $item['mentor']).'</span>';
            }
            if (! empty($item['active'])) {
                echo '<span class="journey-history-chip is-active">Aktif</span>';
            }
            echo '</div>';
            $members = is_array($item['members'] ?? null) ? array_filter($item['members']) : [];
            if ($members !== []) {
                echo '<div class="journey-history-item-members">Anggota: '.h(implode(', ', $members)).'</div>';
            }
            if (trim((string) ($item['note'] ?? '')) !== '') {
                echo '<div class="journey-history-item-note">Catatan: '.h((string) $item['note']).'</div>';
            }
            echo '</article>';
        }
        echo '</div>';

        return (string) ob_get_clean();
    };
@endphp
<div class="msk-view-sheet">
  <section class="msk-view-person-hero">
    <div class="msk-view-person-avatar">{{ $profile['initials'] }}</div>
    <div class="msk-view-person-copy">
      <div class="msk-view-person-name">{{ $profile['full_name'] }}</div>
      <div class="msk-view-person-sub">
        {{ $subtitle }}
        @if($profile['person_id'] !== '')
          | ID Pemuridan {{ $profile['person_id'] }}
        @endif
      </div>
    </div>
    <div class="msk-view-person-badges">
      <span class="msk-status-badge {{ $profile['status_class'] }}">{{ $profile['status_label'] }}</span>
      <span class="journey-track-badge is-msk">{{ $batchBadgeLabel }}</span>
      <span class="journey-track-badge {{ $stageClass }}">{{ $stage !== '' ? $stage : 'Belum DG' }}</span>
    </div>
  </section>

  <div class="msk-view-sections">
    <section class="msk-view-section">
      <div class="msk-view-section-head"><span class="msk-view-section-kicker">Identitas</span><h4>Profil peserta</h4></div>
      <dl class="msk-view-details">
        <div class="msk-view-detail"><dt>Nama Peserta</dt><dd>{{ $profile['full_name'] }}</dd></div>
        <div class="msk-view-detail"><dt>Jenis Kelamin</dt><dd>{{ $profile['gender'] }}</dd></div>
        <div class="msk-view-detail"><dt>Tempat Lahir</dt><dd>{{ $profile['birth_place'] }}</dd></div>
        <div class="msk-view-detail"><dt>Tanggal Lahir</dt><dd>{{ $profile['birth_date'] }}</dd></div>
      </dl>
    </section>
    <section class="msk-view-section">
      <div class="msk-view-section-head"><span class="msk-view-section-kicker">Kontak</span><h4>Kontak dan akses</h4></div>
      <dl class="msk-view-details">
        <div class="msk-view-detail is-wide"><dt>Alamat</dt><dd>{{ $profile['address'] }}</dd></div>
        <div class="msk-view-detail"><dt>Email</dt><dd>@if($profile['email'] !== '')<a class="note-link" href="mailto:{{ $profile['email'] }}">{{ $profile['email'] }}</a>@else-@endif</dd></div>
        <div class="msk-view-detail"><dt>WhatsApp</dt><dd>@if($profile['whatsapp_url'] !== '')<a class="note-link" href="{{ $profile['whatsapp_url'] }}" target="_blank" rel="noopener">{{ $profile['whatsapp'] }}</a>@else-@endif</dd></div>
      </dl>
    </section>
    <section class="msk-view-section is-wide">
      <div class="msk-view-section-head"><span class="msk-view-section-kicker">Lampiran</span><h4>Foto dan keterangan</h4></div>
      <div class="msk-view-rich-grid">
        <div class="msk-view-rich-card"><span>Foto</span><div>@forelse($profile['photos'] as $photo)<a class="note-link" href="{{ $photo['url'] }}" target="_blank" rel="noopener">{{ $photo['label'] }}</a>@empty-@endforelse</div></div>
        <div class="msk-view-rich-card"><span>Keterangan</span><div>{{ $profile['notes'] }}</div></div>
      </div>
    </section>
  </div>

  @if(! $isExternalContext)
    <section class="msk-view-summary-card">
      <div class="msk-view-section-head"><span class="msk-view-section-kicker">Perjalanan</span><h4>MSK dan pemuridan aktif</h4></div>
      <div class="msk-view-summary-grid">
        <div class="msk-view-summary-item"><span>Sesi MSK</span><strong>{{ $profile['session_progress'] }}</strong></div>
        <div class="msk-view-summary-item"><span>Mentor Aktif</span><strong>{{ $profile['current_mentors'] !== [] ? implode(', ', $profile['current_mentors']) : '-' }}</strong></div>
        <div class="msk-view-summary-item"><span>Kelompok Aktif</span><strong>{{ $profile['current_groups'] !== [] ? implode(', ', $profile['current_groups']) : '-' }}</strong></div>
        <div class="msk-view-summary-item"><span>Tahap DG</span><strong>{{ $stage !== '' ? $stage : 'Belum DG' }}</strong></div>
      </div>
      <div class="msk-view-progress">
        <div class="msk-progress-top"><span class="msk-progress-value">{{ $profile['session_progress'] }}</span><span class="msk-progress-percent">{{ $profile['session_percent'] }}%</span></div>
        <div class="msk-progress-bar" aria-hidden="true"><span style="width:{{ $profile['session_percent'] }}%"></span></div>
        <div class="msk-progress-meta">{{ $profile['session_label'] }}</div>
      </div>
    </section>
  @endif

  <section class="msk-view-section is-wide msk-view-history-section">
    <div class="msk-view-section-head"><span class="msk-view-section-kicker">Pemuridan</span><h4>Riwayat pemuridan</h4></div>
    @if(! $profile['linked'])
      <div class="journey-history-empty">Peserta ini belum terhubung ke data pemuridan. Setelah peserta dihubungkan ke Anggota DG, riwayat kelompok, mentor, dan kepemimpinan akan muncul di sini.</div>
    @elseif((! $isExternalContext && $profile['member_items'] === []) && $profile['leader_items'] === [])
      <div class="journey-history-empty">Peserta sudah terhubung ke data pemuridan, tetapi belum memiliki riwayat kelompok atau kepemimpinan.</div>
    @else
      @if(! $isExternalContext)
        <div class="journey-history-split-section">
          <div class="journey-history-split-header">Riwayat Sebagai Anggota</div>
          {!! $profile['member_items'] !== [] ? $renderHistory($profile['member_items']) : '<div class="journey-history-empty">Belum ada riwayat sebagai anggota.</div>' !!}
        </div>
        <div class="journey-history-split-divider"></div>
      @endif
      <div class="journey-history-split-section">
        <div class="journey-history-split-header">Riwayat Memimpin</div>
        {!! $profile['leader_items'] !== [] ? $renderHistory($profile['leader_items']) : '<div class="journey-history-empty">Belum ada riwayat memimpin kelompok.</div>' !!}
      </div>
    @endif
  </section>
</div>
