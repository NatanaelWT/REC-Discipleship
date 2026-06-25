<div class="card-row discipleship-dashboard-section-head"><h2>Rincian Tiap Cabang</h2><span class="badge muted">Semua cabang</span></div>
<div class="discipleship-branch-breakdown">
  @foreach ($branches as $branch)
    <article class="discipleship-branch-card">
      <div class="discipleship-branch-card-head">
        <div class="discipleship-branch-card-title"><span class="badge warning">Cabang</span><h3>{{ $branch['branch_label'] }}</h3><p>{{ number_format($branch['active_people_count'], 0, ',', '.') }} peserta selama ini dalam {{ number_format($branch['group_count'], 0, ',', '.') }} kelompok.</p></div>
        <div class="discipleship-branch-progress-ring" style="--pct:{{ $branch['overall_progress'] }};"><strong>{{ number_format($branch['overall_progress'], 0, ',', '.') }}%</strong></div>
      </div>
      <div class="discipleship-branch-targets">
        @foreach ($branch['journey_rows'] as $row)
          <?php $percent = $row['target'] > 0 ? min(100, ($row['value'] / $row['target']) * 100) : 0; ?>
          <div class="discipleship-branch-target"><div class="discipleship-branch-target-top"><span>{{ $row['label'] }}</span><strong>{{ $row['value'] }} / {{ $row['target'] }}</strong></div><div class="discipleship-branch-target-bar"><span style="width:{{ $percent }}%;background:{{ $row['color'] }};"></span></div></div>
        @endforeach
      </div>
    </article>
  @endforeach
</div>
