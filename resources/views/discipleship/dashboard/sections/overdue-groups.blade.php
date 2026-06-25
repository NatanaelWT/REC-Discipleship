<div class="discipleship-overdue-head">
  <div><span class="discipleship-overdue-kicker">Tindak Lanjut Jurnal Temu DG</span><h2>Belum Lapor DG 30 Hari Terakhir</h2><p>Kelompok yang belum mengirim laporan dalam 30 hari terakhir.</p></div>
  <span class="discipleship-overdue-count">{{ number_format($groups->total(), 0, ',', '.') }}</span>
</div>
@if ($groups->isEmpty())
  <div class="chart-empty-inline">Semua kelompok sudah melaporkan pertemuan dalam 30 hari terakhir.</div>
@else
  <div class="discipleship-overdue-list-wrap"><div class="discipleship-overdue-list">
    @foreach ($groups as $group)
      <div class="discipleship-overdue-item">
        <div class="discipleship-overdue-top"><span class="name">{{ $group['leader_name'] }}</span><span class="badge muted">{{ $group['progress'] }}</span></div>
        <div class="discipleship-overdue-meta"><span>Peserta</span><strong>{{ $group['members_first_names'] }}</strong></div>
        <div class="discipleship-overdue-meta"><span>Cabang</span><strong>{{ $group['branch_label'] }}</strong></div>
        <div class="discipleship-overdue-meta"><span>Terakhir Lapor</span><strong>{{ $group['last_report_date'] !== '' ? format_indo_date($group['last_report_date']) : 'Belum Pernah Lapor' }}</strong></div>
      </div>
    @endforeach
  </div></div>
  @include('discipleship.dashboard.sections.pagination', ['paginator' => $groups])
@endif
