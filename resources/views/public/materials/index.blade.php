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

      @if (count($materialRows) > 0)
        <div class="public-material-list">
          @foreach ($materialRows as $row)
            @include('public.materials.partials.material-item', ['row' => $row, 'menu' => $menu])
          @endforeach
        </div>
      @endif

      <div class="form-actions public-material-footer">
        <a class="btn ghost" href="{{ url('/index.php') }}">Kembali</a>
      </div>
    </section>
@endsection
