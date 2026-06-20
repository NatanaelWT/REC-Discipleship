<div class="discipleship-overdue-head">
  <div><span class="discipleship-overdue-kicker">Tindak Lanjut MSK</span><h2>Belum Selesai MSK</h2><p>Peserta yang masih perlu pemantauan lanjutan.</p></div>
  <span class="discipleship-overdue-count">{{ number_format($participants->total(), 0, ',', '.') }}</span>
</div>
@if ($participants->isEmpty())
  <div class="chart-empty-inline">Semua peserta sudah menyelesaikan 12 sesi MSK.</div>
@else
  <div class="discipleship-overdue-list-wrap"><div class="discipleship-overdue-list">
    @foreach ($participants as $participant)
      <?php $monthLabel = $participant['batch_month'] !== '' ? format_indo_month($participant['batch_month']) : '-'; ?>
      <div class="discipleship-overdue-item">
        <div class="discipleship-overdue-top">
          <span class="name">{{ $participant['name'] }}</span>
          <span class="discipleship-overdue-actions">
            <span class="badge warning">{{ $participant['session_count'] }}/12 sesi</span>
            @if (! $centralReadOnly)
              <button class="btn tiny secondary icon-btn" type="button" data-msk-edit-open="{{ $participant['id'] }}" aria-label="Edit sesi MSK" title="Edit sesi MSK"><?php echo icon_svg('edit'); ?></button>
            @endif
          </span>
        </div>
        <div class="discipleship-overdue-meta"><span>Cabang</span><strong>{{ $participant['branch_label'] }}</strong></div>
        <div class="discipleship-overdue-meta"><span>Batch Mulai MSK</span><strong>{{ $monthLabel }}</strong></div>
        <div class="discipleship-overdue-meta"><span>WhatsApp</span><strong>{{ $participant['phone'] }}</strong></div>
      </div>
      @if (! $centralReadOnly)
        <template data-msk-edit-template="{{ $participant['id'] }}" data-msk-edit-template-title="Edit Sesi MSK: {{ $participant['name'] }}">
          <form method="post" action="{{ route('discipleship.dashboard.msk-sessions') }}" class="form-grid">
            @csrf
            <div class="panel-note" style="grid-column:1/-1;">Peserta: <strong>{{ $participant['name'] }}</strong><br>Batch Mulai MSK: {{ $monthLabel }}<br>Progress Saat Ini: {{ $participant['session_count'] }}/12 sesi</div>
            <fieldset class="dg-checklist msk-session-checklist" style="grid-column:1/-1;"><legend>Edit Checklist 12 Sesi MSK</legend><div class="msk-session-grid">
              @for ($session = 1; $session <= 12; $session++)
                <label class="check-label"><input type="checkbox" name="session_numbers[]" value="{{ $session }}" @checked(in_array($session, $participant['session_numbers'], true))>Sesi {{ $session }}</label>
              @endfor
            </div></fieldset>
            <input type="hidden" name="action" value="save_msk_sessions"><input type="hidden" name="id" value="{{ $participant['id'] }}">
            <div class="modal-actions" style="grid-column:1/-1;"><button class="btn" type="submit">Simpan Sesi</button><button class="btn ghost" type="button" data-msk-edit-close>Batal</button></div>
          </form>
        </template>
      @endif
    @endforeach
  </div></div>
  @include('discipleship.dashboard.sections.pagination', ['paginator' => $participants])
@endif
