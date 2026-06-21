<div class="analytics-bar-row">
  <div class="analytics-bar-label">
    <strong>{{ $row['label'] }}</strong>
    <span>{{ number_format($row['count'], 0, ',', '.') }} akses · {{ number_format($row['visitors'], 0, ',', '.') }} pengunjung</span>
  </div>
  <div class="analytics-bar-track" aria-hidden="true"><span style="width: {{ max(2, round(($row['count'] / $rowMax) * 100, 2)) }}%"></span></div>
</div>
