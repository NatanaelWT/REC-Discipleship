@extends('layouts.rec_plain', [
    'title' => $previewTitle,
    'settings' => $settings,
    'bodyClass' => 'page-public-material-text-preview',
])

@section('content')
    @php
        $maintenanceMode = function_exists('app_maintenance_mode_enabled') && app_maintenance_mode_enabled();
    @endphp
    <section class="public-material-text-shell" aria-label="Teks materi DG">
      <div class="public-material-text-main">
        <header class="public-material-text-header">
          <a class="btn ghost public-material-text-back-btn" href="{{ $backUrl }}">
            @include('developer._icon', ['name' => 'arrow-left'])
            <span>Kembali</span>
          </a>
          <a class="btn secondary public-material-text-download-btn" href="{{ $downloadUrl }}">
            @include('developer._icon', ['name' => 'download'])
            <span>Unduh PDF</span>
          </a>
          <div class="public-material-text-kicker">{{ $textKicker }}</div>
          <h1>{{ $previewTitle }}</h1>
        </header>
        <article class="public-material-text-document">
          @foreach ($textBlocks as $block)
            @if (($block['type'] ?? '') === 'heading')
              <h2 @class(['public-material-text-heading', $block['class'] ?? '' => ($block['class'] ?? '') !== ''])>{{ $block['text'] ?? '' }}</h2>
            @else
              <p @class(['public-material-text-paragraph', $block['class'] ?? '' => ($block['class'] ?? '') !== ''])>
                @if (($block['lead'] ?? '') !== '')
                  <strong>{{ $block['lead'] }}</strong>@if (($block['rest'] ?? '') !== '') {{ $block['rest'] }}@endif
                @else
                  {{ $block['text'] ?? '' }}
                @endif
              </p>
            @endif
          @endforeach
        </article>
      </div>

      <div class="public-material-preview-actions public-material-text-actions">
        @if (! $maintenanceMode)
          <a class="btn public-material-journal-btn" href="{{ route('public.dg.branch') }}">Isi Jurnal Temu DG</a>
        @endif
        @if (! $maintenanceMode && $showFeedbackButton)
          @php
              $feedbackParams = [];
              if (in_array((int) $feedbackSessionNumber, [3, 12], true)) {
                  $feedbackParams['feedback_session'] = (string) $feedbackSessionNumber;
              }
          @endphp
          <a class="btn secondary public-material-feedback-btn" href="{{ route('public.member-feedback.branch', $feedbackParams) }}">Isi Jurnal Umpan Balik Anggota</a>
        @endif
      </div>
    </section>
@endsection
