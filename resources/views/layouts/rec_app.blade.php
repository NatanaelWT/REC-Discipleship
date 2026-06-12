@php
    \App\Support\LegacyRuntimeBootstrap::boot(request());
    page_header(
        $title ?? 'Reformed Exodus Community',
        $settings ?? ['church_name' => CHURCH_NAME],
        $currentPage ?? '',
        $showTitle ?? true,
        $bodyClass ?? '',
    );
@endphp

@yield('content')

@php
    page_footer();
@endphp
