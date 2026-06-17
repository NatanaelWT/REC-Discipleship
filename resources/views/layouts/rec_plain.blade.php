@php
    \App\Support\RuntimeBootstrap::boot(request());
    page_header_plain($title ?? 'Reformed Exodus Community', $settings ?? ['church_name' => CHURCH_NAME], $bodyClass ?? '');
@endphp

@yield('content')

@php
    page_footer_plain();
@endphp
