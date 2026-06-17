@extends('layouts.rec_plain', [
    'title' => 'Menu Publik',
    'settings' => $settings,
    'bodyClass' => 'page-public-menu',
])

@section('content')
    <section class="card public-empty-card">
      <h2>{{ $menuLabel }}</h2>
      <p>Halaman ini masih kosong dan akan diisi berikutnya.</p>
      <div class="form-actions">
        <a class="btn ghost" href="{{ route('home', [], false) }}">Kembali ke Halaman Awal</a>
      </div>
    </section>
@endsection
