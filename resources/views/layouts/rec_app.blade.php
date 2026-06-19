@php
    \App\Support\RuntimeBootstrap::boot(request());
    page_header(
        $title ?? 'Reformed Exodus Community',
        $settings ?? ['church_name' => app_church_name()],
        $currentPage ?? '',
        $showTitle ?? true,
        $bodyClass ?? '',
    );
@endphp

@yield('content')

@php
    page_footer();
@endphp
