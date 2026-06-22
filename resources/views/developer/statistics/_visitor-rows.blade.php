@foreach ($rows as $visitor)
  <tr @class([$rowClass ?? null])>
    <td data-label="Pengunjung"><strong>{{ $visitor['label'] }}</strong><small>{{ substr($visitor['visitor_hash'], 0, 12) }}&hellip;</small></td>
    <td data-label="Bahasa / perangkat">{{ $visitor['language'] }}<small>{{ $visitor['device'] }}</small></td>
    <td data-label="Page view">{{ number_format($visitor['page_views'], 0, ',', '.') }}</td>
    <td data-label="Sesi">{{ number_format($visitor['sessions'], 0, ',', '.') }}</td>
    <td data-label="Terakhir">{{ $visitor['last_seen_at']->format('d-m-Y H:i') }}</td>
    <td data-label="Aksi"><a class="btn tiny ghost developer-detail-link" href="{{ route('developer.statistics', array_merge(request()->except('visitor'), ['visitor' => $visitor['visitor_hash']])) }}">@include('developer._icon', ['name' => 'eye'])<span>Lihat</span></a></td>
  </tr>
@endforeach
