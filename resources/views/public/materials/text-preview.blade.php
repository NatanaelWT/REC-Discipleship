@extends('layouts.rec_plain', [
    'title' => $previewTitle,
    'settings' => $settings,
    'bodyClass' => 'page-public-material-text-preview',
])

@section('content')
    <section class="public-material-text-shell" aria-label="Teks materi DG-1">
      <div class="public-material-text-main">
        <header class="public-material-text-header">
          <div class="public-material-text-kicker">Materi DG-1</div>
          <h1>{{ $previewTitle }}</h1>
        </header>
        <article class="public-material-text-document">{{ $textContent }}</article>
      </div>

      <div class="public-material-preview-actions public-material-text-actions">
        <a class="btn secondary public-material-download-btn" href="{{ $downloadUrl }}">Unduh PDF</a>
        <a class="btn public-material-journal-btn" href="{{ route('public.dg.branch') }}">Isi Jurnal Temu DG</a>
        @if ($showFeedbackButton)
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
