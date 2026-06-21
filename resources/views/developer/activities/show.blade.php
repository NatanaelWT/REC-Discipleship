@extends('layouts.rec_app', [
    'title' => 'Detail Aktivitas',
    'settings' => $settings,
    'currentPage' => 'developer_activities',
    'bodyClass' => 'page-developer page-activities',
])

@php
  $pretty = static fn ($value): string => is_array($value)
      ? (json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}')
      : '-';
  $backUrl = route('developer.activities').($backQuery !== '' ? '?'.$backQuery : '');
  $started = $activity->started_at?->setTimezone(app_timezone());
  $completed = $activity->completed_at?->setTimezone(app_timezone());
@endphp

@section('content')
  <section class="card developer-panel">
    <div class="card-row">
      <div><h2>Request {{ $activity->id }}</h2><p class="developer-muted">{{ $activity->method }} {{ $activity->path }}</p></div>
      <a class="button secondary" href="{{ $backUrl }}">Kembali</a>
    </div>
    <div class="activity-facts">
      <div><span>Waktu</span><strong>{{ $started?->format('d-m-Y H:i:s.u') ?? '-' }}</strong></div>
      <div><span>Selesai</span><strong>{{ $completed?->format('d-m-Y H:i:s.u') ?? '-' }}</strong></div>
      <div><span>Actor</span><strong>{{ $activity->username ?: 'Anonim' }}</strong></div>
      <div><span>Role / Cabang</span><strong>{{ $activity->role ?: '-' }} / {{ $activity->branch_label ?: '-' }}</strong></div>
      <div><span>Route</span><strong>{{ $activity->route_name ?: '-' }}</strong></div>
      <div><span>Kategori / Action</span><strong>{{ $activity->category }} / {{ $activity->action }}</strong></div>
      <div><span>Hasil</span><strong>{{ $activity->outcome }} · HTTP {{ $activity->http_status ?? '-' }}</strong></div>
      <div><span>Durasi / Ukuran</span><strong>{{ $activity->duration_ms ?? '-' }} ms / {{ $activity->response_size ?? '-' }} byte</strong></div>
      <div><span>IP</span><strong>{{ $activity->ip_address ?: '-' }}</strong></div>
      <div><span>Visitor hash</span><strong class="activity-mono">{{ $activity->visitor_hash ?: '-' }}</strong></div>
      <div><span>Subject</span><strong>{{ $activity->subject_type ?: '-' }} #{{ $activity->subject_id ?: '-' }}</strong></div>
      <div><span>Redirect</span><strong>{{ $activity->redirect_to ?: '-' }}</strong></div>
    </div>
  </section>

  <section class="activity-detail-grid">
    <article class="card developer-panel"><h3>Query</h3><pre>{{ $pretty($activity->query_data) }}</pre></article>
    <article class="card developer-panel"><h3>Input tersanitasi</h3><pre>{{ $pretty($activity->input_data) }}</pre></article>
    <article class="card developer-panel"><h3>Client</h3><pre>{{ $activity->user_agent ?: '-' }}\nReferer: {{ $activity->referer ?: '-' }}\nContent-Type: {{ $activity->response_content_type ?: '-' }}</pre></article>
    <article class="card developer-panel"><h3>Error</h3><pre>{{ $activity->error_type ?: '-' }}\n{{ $activity->error_message ?: '-' }}</pre></article>
  </section>

  <section class="card developer-panel">
    <h2>Event dalam request</h2>
    <div class="activity-event-list">
      @forelse ($activity->events as $event)
        <details class="activity-event">
          <summary><span class="badge">{{ $event->category }}</span><strong>{{ $event->action }}</strong><span>{{ $event->subject_label ?: ($event->subject_type ? $event->subject_type.' #'.$event->subject_id : '') }}</span></summary>
          <div class="activity-event-body">
            @if ($event->description)<p>{{ $event->description }}</p>@endif
            <div class="activity-detail-grid">
              <div><h4>Sebelum</h4><pre>{{ $pretty($event->before_values) }}</pre></div>
              <div><h4>Sesudah</h4><pre>{{ $pretty($event->after_values) }}</pre></div>
              <div><h4>Perubahan</h4><pre>{{ $pretty($event->changed_values) }}</pre></div>
              <div><h4>Metadata</h4><pre>{{ $pretty($event->metadata) }}</pre></div>
            </div>
          </div>
        </details>
      @empty
        <p class="developer-muted">Request ini tidak mengubah record dan tidak memiliki event tambahan.</p>
      @endforelse
    </div>
  </section>
@endsection
