@extends('layouts.rec_plain', [
    'title' => $title,
    'settings' => $settings,
    'bodyClass' => $bodyClass,
])

@section('content')
    @if ($file->isPdf())
        <iframe class="file-page-embed" src="{{ $rawUrl }}" loading="eager" referrerpolicy="same-origin" title="{{ $previewTitle }}"></iframe>
    @else
        <section class="card">
          <div class="card-row">
            <h2>Preview File</h2>
            <div class="actions">
              <a class="btn tiny secondary" href="{{ $downloadUrl }}">Unduh</a>
              <a class="btn tiny ghost" href="{{ $backUrl }}" onclick="{{ $backOnClick }}">Kembali</a>
            </div>
          </div>
          <div class="panel-note">{{ $previewTitle }}</div>
          @if ($file->isImage())
            <div class="file-view-image-wrap"><img class="file-view-image" src="{{ $rawUrl }}" alt="{{ $previewTitle }}"></div>
          @else
            <div class="file-view-embed-wrap"><iframe class="file-view-embed" src="{{ $rawUrl }}" loading="lazy" referrerpolicy="same-origin"></iframe></div>
          @endif
        </section>
    @endif
@endsection
