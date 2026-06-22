@php
  $developerHeaderIcon = $icon ?? 'dashboard';
  $developerHeaderMetaLabel = $metaLabel ?? 'Akses aktif';
  $developerHeaderMetaValue = $metaValue ?? (current_username() ?: 'Developer');
  $developerHeaderMetaHint = $metaHint ?? ('Developer / '.app_timezone()->getName());
@endphp

<section class="card developer-page-hero" data-developer-header>
  <div class="developer-page-hero-main">
    <span class="developer-page-hero-icon">@include('developer._icon', ['name' => $developerHeaderIcon])</span>
    <div class="developer-page-hero-copy">
      <span class="developer-page-hero-kicker">{{ $eyebrow ?? 'Developer' }}</span>
      <h1>{{ $title ?? 'Developer' }}</h1>
      <p>{{ $description ?? 'Kelola aplikasi melalui alat administrasi yang tersedia.' }}</p>
    </div>
  </div>
  <div class="developer-page-hero-meta">
    <span>{{ $developerHeaderMetaLabel }}</span>
    <strong>{{ $developerHeaderMetaValue }}</strong>
    <small>{{ $developerHeaderMetaHint }}</small>
  </div>
</section>
