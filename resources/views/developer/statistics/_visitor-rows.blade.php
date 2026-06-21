@foreach ($rows as $visitor)
  <tr @class([$rowClass ?? null])>
    <td><strong>{{ $visitor['label'] }}</strong><small>{{ substr($visitor['visitor_hash'], 0, 12) }}…</small></td>
    <td>{{ $visitor['language'] }}<small>{{ $visitor['device'] }}</small></td>
    <td>{{ number_format($visitor['page_views'], 0, ',', '.') }}</td>
    <td>{{ number_format($visitor['sessions'], 0, ',', '.') }}</td>
    <td>{{ $visitor['last_seen_at']->format('d-m-Y H:i') }}</td>
    <td><a class="button secondary small" href="{{ route('developer.statistics', array_merge(request()->except('visitor'), ['visitor' => $visitor['visitor_hash']])) }}">Lihat</a></td>
  </tr>
@endforeach
