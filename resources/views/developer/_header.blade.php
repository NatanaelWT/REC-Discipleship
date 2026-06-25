@php
  $developerHeaderMetaLabel = $metaLabel ?? 'Akses aktif';
  $developerHeaderMetaValue = $metaValue ?? (current_username() ?: 'Developer');
  $developerHeaderStats = is_array($stats ?? null) && $stats !== []
      ? $stats
      : [['label' => $developerHeaderMetaLabel, 'value' => $developerHeaderMetaValue]];
@endphp

@include('discipleship.partials.page-header', [
    'header' => [
        'kicker' => $eyebrow ?? 'Developer',
        'title' => $title ?? 'Developer',
        'description' => $description ?? 'Kelola aplikasi melalui alat administrasi yang tersedia.',
        'stats' => $developerHeaderStats,
        'attributes' => ['data-developer-header' => true],
    ],
])
