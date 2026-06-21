@foreach ($rows as $visitor)
  <tr @class([$rowClass ?? null])>
    <td><strong>{{ $visitor['label'] }}</strong><small>{{ substr($visitor['visitor_hash'], 0, 12) }}…</small></td>
    <td>{{ $visitor['language'] }}<small>{{ $visitor['device'] }}</small></td>
    <td>{{ number_format($visitor['page_views'], 0, ',', '.') }}</td>
    <td>{{ number_format($visitor['sessions'], 0, ',', '.') }}</td>
    <td>{{ $visitor['last_seen_at']->format('d-m-Y H:i') }}</td>
    <td><a class="button secondary small developer-detail-link" href="{{ route('developer.statistics', array_merge(request()->except('visitor'), ['visitor' => $visitor['visitor_hash']])) }}"><span>Lihat</span><span aria-hidden="true">→</span></a></td>
  </tr>
@endforeach
