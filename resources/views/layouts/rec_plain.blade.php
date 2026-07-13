@php
    page_header_plain($title ?? 'Reformed Exodus Community', $settings ?? ['church_name' => app_church_name()], $bodyClass ?? '');
@endphp

@yield('content')

@php
    page_footer_plain();
@endphp
