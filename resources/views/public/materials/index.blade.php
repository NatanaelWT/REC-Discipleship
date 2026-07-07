@extends('layouts.rec_plain', [
    'title' => $menuLabel,
    'settings' => $settings,
    'bodyClass' => 'page-dg-public',
])

@section('content')
    <section class="card public-material-card">
      <div class="card-row public-material-head">
        <div>
          <h2>{{ $menuLabel }}</h2>
          <p class="public-material-subtitle">{{ $menuSubtitle }}</p>
        </div>
        <span class="public-material-count">{{ (string) count($materialRows) }} file</span>
      </div>

      @if (function_exists('app_maintenance_mode_enabled') && app_maintenance_mode_enabled())
        <div class="public-material-message is-danger">Aplikasi sedang maintenance. Materi DG tetap bisa dibaca, tetapi pelaporan dan form publik sementara ditutup.</div>
      @endif

      @if ($materialStatus === 'uploaded')
        <div class="public-material-message is-success">File berhasil diupload.</div>
      @elseif ($materialStatus === 'renamed')
        <div class="public-material-message is-success">Nama file berhasil disimpan.</div>
      @elseif ($materialError !== '')
        <div class="public-material-message is-danger">
          @switch($materialError)
            @case('missing_file')
              Pilih file yang akan diupload.
              @break
            @case('file_too_large')
              Ukuran file terlalu besar.
              @break
            @case('invalid_file_type')
              Tipe file tidak diizinkan.
              @break
            @case('missing_title')
              Isi nama file.
              @break
            @case('invalid_folder')
              Folder materi tidak valid.
              @break
            @default
              Perubahan file belum bisa disimpan.
          @endswitch
        </div>
      @endif

      @if ($canManageMaterials)
        <form class="public-material-admin-form" method="post" action="{{ route('materials.upload', ['menu' => $menu]) }}" enctype="multipart/form-data">
          @csrf
          <input class="public-material-admin-input" type="text" name="title" maxlength="180" placeholder="Nama file">
          <input class="public-material-admin-file" type="file" name="material_file" required>
          <button class="btn secondary" type="submit">Upload</button>
        </form>
      @endif

      @if (count($materialRows) > 0)
        <div class="public-material-list">
          @foreach ($materialRows as $row)
            @include('public.materials.partials.material-item', ['row' => $row, 'menu' => $menu, 'canManageMaterials' => $canManageMaterials])
          @endforeach
        </div>
      @endif

      <div class="form-actions public-material-footer">
        <a class="btn ghost" href="{{ route('home', [], false) }}">Kembali</a>
      </div>
    </section>
@endsection
