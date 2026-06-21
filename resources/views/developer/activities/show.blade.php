@extends('layouts.rec_app', [
    'title' => 'Detail Aktivitas',
    'settings' => $settings,
    'currentPage' => 'developer_activities',
    'bodyClass' => 'page-developer page-activities',
    'showTitle' => false,
])

@php
  $pretty = static fn ($value): string => is_array($value)
      ? (json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}')
      : '-';
  $backUrl = route('developer.activities').($backQuery !== '' ? '?'.$backQuery : '');
  $started = $activity->started_at?->setTimezone(app_timezone());
  $completed = $activity->completed_at?->setTimezone(app_timezone());
  $hasError = trim((string) $activity->error_type) !== '' || trim((string) $activity->error_message) !== '';
@endphp

@section('content')
  @include('developer._header', [
    'activePage' => 'developer_activities',
    'title' => 'Detail Aktivitas',
    'description' => 'Periksa konteks request, perubahan record, metadata teknis, dan detail kegagalan.',
    'eyebrow' => 'Audit Detail',
  ])

  <section class="card developer-panel developer-section-card activity-request-summary">
    <div class="card-row">
      <div><h2>Request {{ $activity->id }}</h2><p class="developer-muted">{{ $activity->method }} {{ $activity->path }}</p></div>
      <a class="button secondary developer-link-button" href="{{ $backUrl }}"><span aria-hidden="true">←</span><span>Kembali ke Aktivitas</span></a>
    </div>
    <div class="activity-facts">
      <div><span>Waktu</span><strong>{{ $started?->format('d-m-Y H:i:s.u') ?? '-' }}</strong></div>
      <div><span>Actor</span><strong>{{ $activity->username ?: 'Anonim' }}</strong></div>
      <div><span>Role / Cabang</span><strong>{{ $activity->role ?: '-' }} / {{ $activity->branch_label ?: '-' }}</strong></div>
      <div><span>Route</span><strong>{{ $activity->route_name ?: '-' }}</strong></div>
      <div><span>Kategori / Action</span><strong>{{ $activity->category }} / {{ $activity->action }}</strong></div>
      <div><span>Hasil</span><strong>{{ $activity->outcome }} · HTTP {{ $activity->http_status ?? '-' }}</strong></div>
      <div><span>Durasi</span><strong>{{ $activity->duration_ms ?? '-' }} ms</strong></div>
      <div><span>IP</span><strong>{{ $activity->ip_address ?: '-' }}</strong></div>
      <div><span>Subject</span><strong>{{ $activity->subject_type ?: '-' }} #{{ $activity->subject_id ?: '-' }}</strong></div>
    </div>
  </section>

  <details class="card developer-panel activity-request-more">
    <summary><span>Informasi request lainnya</span><small>Selesai, response, visitor, dan redirect</small><span class="activity-disclosure-icon" aria-hidden="true"></span></summary>
    <div class="activity-facts">
      <div><span>Selesai</span><strong>{{ $completed?->format('d-m-Y H:i:s.u') ?? '-' }}</strong></div>
      <div><span>Ukuran response</span><strong>{{ $activity->response_size ?? '-' }} byte</strong></div>
      <div><span>Content-Type</span><strong>{{ $activity->response_content_type ?: '-' }}</strong></div>
      <div><span>Visitor hash</span><strong class="activity-mono">{{ $activity->visitor_hash ?: '-' }}</strong></div>
      <div><span>Redirect</span><strong>{{ $activity->redirect_to ?: '-' }}</strong></div>
    </div>
  </details>

  <section class="activity-technical-grid">
    <details class="card activity-technical-panel" data-activity-technical="query">
      <summary><span>Query</span><small>Parameter URL tersanitasi</small><span class="activity-disclosure-icon" aria-hidden="true"></span></summary>
      <pre>{{ $pretty($activity->query_data) }}</pre>
    </details>
    <details class="card activity-technical-panel" data-activity-technical="input">
      <summary><span>Input</span><small>Payload request tersanitasi</small><span class="activity-disclosure-icon" aria-hidden="true"></span></summary>
      <pre>{{ $pretty($activity->input_data) }}</pre>
    </details>
    <details class="card activity-technical-panel" data-activity-technical="client">
      <summary><span>Client</span><small>User-agent dan referer</small><span class="activity-disclosure-icon" aria-hidden="true"></span></summary>
      <pre>{{ $activity->user_agent ?: '-' }}
Referer: {{ $activity->referer ?: '-' }}</pre>
    </details>
    <details class="card activity-technical-panel is-error" data-activity-technical="error" @if ($activity->outcome === 'failed' && $hasError) open data-auto-open-error @endif>
      <summary><span>Error</span><small>{{ $hasError ? 'Detail kegagalan tersedia' : 'Tidak ada error' }}</small><span class="activity-disclosure-icon" aria-hidden="true"></span></summary>
      <pre>{{ $activity->error_type ?: '-' }}
{{ $activity->error_message ?: '-' }}</pre>
    </details>
  </section>

  <section class="card developer-panel">
    <h2>Event dalam request</h2>
    <div class="activity-event-list">
      @forelse ($activity->events as $event)
        <details class="activity-event">
          <summary><span class="badge">{{ $event->category }}</span><strong>{{ $event->action }}</strong><span>{{ $event->subject_label ?: ($event->subject_type ? $event->subject_type.' #'.$event->subject_id : '') }}</span></summary>
          <div class="activity-event-body">
            @if ($event->description)<p>{{ $event->description }}</p>@endif
            <div class="activity-event-change"><h4>Perubahan</h4><pre>{{ $pretty($event->changed_values) }}</pre></div>
            <details class="activity-event-more">
              <summary><span>Sebelum, sesudah, dan metadata</span><span class="activity-disclosure-icon" aria-hidden="true"></span></summary>
              <div class="activity-detail-grid">
                <div><h4>Sebelum</h4><pre>{{ $pretty($event->before_values) }}</pre></div>
                <div><h4>Sesudah</h4><pre>{{ $pretty($event->after_values) }}</pre></div>
                <div><h4>Metadata</h4><pre>{{ $pretty($event->metadata) }}</pre></div>
              </div>
            </details>
          </div>
        </details>
      @empty
        <p class="developer-muted">Request ini tidak mengubah record dan tidak memiliki event tambahan.</p>
      @endforelse
    </div>
  </section>
@endsection
