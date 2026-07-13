@extends('layouts.rec_plain', [
    'title' => $previewTitle,
    'settings' => $settings,
    'bodyClass' => 'page-file-preview-standalone page-public-material-preview',
])

@section('content')
    @php
        $maintenanceMode = function_exists('app_maintenance_mode_enabled') && app_maintenance_mode_enabled();
    @endphp
    <section
      class="public-material-preview-shell"
      aria-label="Preview materi DG"
      data-public-material-pdf-viewer
      data-pdf-url="{{ $rawUrl }}"
      data-pdf-title="{{ $previewTitle }}"
      data-pdfjs-url="{{ asset('assets/vendor/pdfjs/pdf.min.js') }}"
      data-pdfjs-worker-url="{{ asset('assets/vendor/pdfjs/pdf.worker.min.js') }}"
    >
      <div class="public-material-native-pdf" data-native-pdf hidden>
        <div class="public-material-pdfjs-status">Menyiapkan PDF...</div>
      </div>
      <div class="public-material-pdfjs-viewer" data-pdfjs-viewer hidden>
        <div class="public-material-pdfjs-status" data-pdfjs-status>Memuat PDF...</div>
        <div class="public-material-pdfjs-pages" data-pdfjs-pages></div>
        <div class="public-material-pdfjs-fallback" data-pdfjs-fallback hidden>PDF belum bisa ditampilkan di browser ini. <a href="{{ $rawUrl }}">Buka PDF</a></div>
      </div>
      <noscript><p class="public-material-pdfjs-fallback"><a href="{{ $rawUrl }}">Buka PDF</a></p></noscript>
      <div class="public-material-preview-actions">
        <a class="btn ghost public-material-back-btn" href="{{ $backUrl }}">Kembali</a>
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

    @include('public.materials.partials.pdf-viewer-script')
@endsection
