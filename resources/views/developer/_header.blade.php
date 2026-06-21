@php
  $developerPages = [
      ['key' => 'developer_dashboard', 'label' => 'Dashboard', 'description' => 'Status sistem', 'route' => 'developer.dashboard', 'icon' => 'dashboard'],
      ['key' => 'developer_users', 'label' => 'User', 'description' => 'Akun dan akses', 'route' => 'developer.users', 'icon' => 'users'],
      ['key' => 'developer_config', 'label' => 'Config', 'description' => 'Konfigurasi aplikasi', 'route' => 'developer.config', 'icon' => 'config'],
      ['key' => 'developer_statistics', 'label' => 'Statistik', 'description' => 'Trafik halaman publik', 'route' => 'developer.statistics', 'icon' => 'statistics'],
      ['key' => 'developer_activities', 'label' => 'Aktivitas', 'description' => 'Audit seluruh request', 'route' => 'developer.activities', 'icon' => 'activities'],
  ];
  $activeDeveloperPage = $activePage ?? $currentPage ?? 'developer_dashboard';
@endphp

<section class="developer-hero" data-developer-hub>
  <div class="developer-hero-glow" aria-hidden="true"></div>
  <div class="developer-hero-copy">
    <span class="developer-hero-kicker">{{ $eyebrow ?? 'Developer Console' }}</span>
    <h1>{{ $title ?? 'Developer' }}</h1>
    <p>{{ $description ?? 'Kelola konfigurasi, akses, statistik, dan audit aplikasi dari satu tempat.' }}</p>
  </div>
  <div class="developer-hero-profile">
    <span class="developer-hero-profile-icon">@include('developer._icon', ['name' => 'config'])</span>
    <span class="developer-hero-profile-copy"><small>Akses aktif</small><strong>{{ current_username() ?: 'Developer' }}</strong><span>Developer · {{ app_timezone()->getName() }}</span></span>
  </div>
</section>

<nav class="developer-hub-nav" aria-label="Navigasi halaman developer">
  @foreach ($developerPages as $developerPage)
    @php($isDeveloperPageActive = $activeDeveloperPage === $developerPage['key'])
    <a class="developer-hub-link{{ $isDeveloperPageActive ? ' is-active' : '' }}" href="{{ route($developerPage['route']) }}" @if ($isDeveloperPageActive) aria-current="page" @endif>
      <span class="developer-hub-link-icon">@include('developer._icon', ['name' => $developerPage['icon']])</span>
      <span class="developer-hub-link-copy"><strong>{{ $developerPage['label'] }}</strong><small>{{ $developerPage['description'] }}</small></span>
      <span class="developer-hub-link-arrow" aria-hidden="true">→</span>
    </a>
  @endforeach
</nav>
