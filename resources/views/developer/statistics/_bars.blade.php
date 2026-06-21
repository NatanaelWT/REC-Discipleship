<section class="card analytics-breakdown-card {{ $wide ?? false ? 'is-wide' : '' }}">
  <div class="analytics-section-head">
    <div><span>{{ $kicker ?? 'Distribusi' }}</span><h3>{{ $title }}</h3></div>
    @if (! empty($note))<small>{{ $note }}</small>@endif
  </div>
  @php $rowMax = max(1, collect($rows)->max('count') ?? 1); @endphp
  <div class="analytics-bars">
    @forelse ($rows as $row)
      <div class="analytics-bar-row">
        <div class="analytics-bar-label"><strong>{{ $row['label'] }}</strong><span>{{ number_format($row['count'], 0, ',', '.') }} akses · {{ number_format($row['visitors'], 0, ',', '.') }} pengunjung</span></div>
        <div class="analytics-bar-track" aria-hidden="true"><span style="width: {{ max(2, round(($row['count'] / $rowMax) * 100, 2)) }}%"></span></div>
      </div>
    @empty
      <div class="analytics-empty">Belum ada data pada periode ini.</div>
    @endforelse
  </div>
</section>
