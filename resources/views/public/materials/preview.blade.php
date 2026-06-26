@extends('layouts.rec_plain', [
    'title' => $previewTitle,
    'settings' => $settings,
    'bodyClass' => 'page-file-preview-standalone page-public-material-preview',
])

@section('content')
    <section class="public-material-preview-shell" aria-label="Preview materi DG" data-public-material-pdf-viewer data-pdf-url="{{ $rawUrl }}">
      <div class="public-material-native-pdf" data-native-pdf>
        <iframe class="file-page-embed public-material-preview-embed" src="{{ $rawUrl }}" loading="eager" referrerpolicy="same-origin" title="{{ $previewTitle }}"></iframe>
      </div>
      <div class="public-material-pdfjs-viewer" data-pdfjs-viewer hidden>
        <div class="public-material-pdfjs-status" data-pdfjs-status>Memuat PDF...</div>
        <div class="public-material-pdfjs-pages" data-pdfjs-pages></div>
        <div class="public-material-pdfjs-fallback" data-pdfjs-fallback hidden>PDF belum bisa ditampilkan di browser ini. <a href="{{ $rawUrl }}">Buka PDF</a></div>
      </div>
      <div class="public-material-preview-actions">
        <a class="btn ghost public-material-back-btn" href="{{ $backUrl }}">Kembali</a>
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

    @include('public.materials.partials.pdf-viewer-script')
@endsection
