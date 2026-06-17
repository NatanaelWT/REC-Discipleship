@extends('layouts.rec_plain', [
    'title' => 'Portal Publik',
    'settings' => $settings,
    'bodyClass' => 'page-dg-public page-public-menu-home',
])

@section('content')
    <section class="card public-menu-card">
      <div class="public-menu-shell">
        <div class="public-menu-head">
          <div class="public-menu-brand">
            <img src="/assets/logo.png" alt="Logo {{ $churchName }}" loading="lazy" decoding="async">
            <h1>{{ $churchName }}</h1>
            <p class="public-menu-tagline">Website Manajemen Pemuridan REC Indonesia</p>
          </div>
        </div>
        <div class="public-menu-grid" role="navigation" aria-label="Menu publik">
          @foreach ($menuCards as $menuCard)
            <a class="{{ $menuCard['tile_class'] }}" href="{{ $menuCard['href'] }}">
              <span class="public-menu-tile-eyebrow">Menu Publik</span>
              <span class="{{ $menuCard['title_class'] }}">
                @if ($menuCard['title_lines'] !== [])
                  @foreach ($menuCard['title_lines'] as $titleLine)
                    @if (! $loop->first)<br>@endif{{ $titleLine }}
                  @endforeach
                @else
                  {{ $menuCard['title'] }}
                @endif
              </span>
              @if ($menuCard['sub'] !== '')
                <span class="public-menu-tile-sub">{{ $menuCard['sub'] }}</span>
              @endif
              <span class="public-menu-tile-cta">{{ $menuCard['cta'] }} <svg viewBox="0 0 20 20" focusable="false" aria-hidden="true"><path d="M7 4l6 6-6 6"/></svg></span>
            </a>
          @endforeach
        </div>
      </div>
    </section>
    <a class="public-login-fab" href="{{ route('auth.login') }}" title="Login Admin" aria-label="Login Admin">
      <span class="public-login-fab-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M7.5 11V8a4.5 4.5 0 0 1 9 0v3"/><rect x="5" y="11" width="14" height="10" rx="2" ry="2"/><path d="M12 15v3"/></svg></span>
    </a>
    <div class="public-social-wrap">
      <div class="public-social-label">Ikuti Kami</div>
      <div class="public-social-row">
        <a class="public-social-link is-instagram" href="https://rec.or.id" target="_blank" rel="noopener">Website</a>
        <span class="public-social-sep" aria-hidden="true"></span>
        <a class="public-social-link is-youtube" href="https://www.youtube.com/@RECIndonesia" target="_blank" rel="noopener">YouTube</a>
      </div>
    </div>
@endsection
