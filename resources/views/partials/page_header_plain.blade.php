<?php

function page_header_plain(string $title, array $settings, string $bodyClass = ''): void {
    $app = h($settings['church_name'] ?? CHURCH_NAME);
    $bodyClasses = ['app-page'];
    append_body_classes($bodyClasses, $bodyClass);
    $classAttr = body_class_attr($bodyClasses);
    render_app_document_head($app);
    echo "<body" . $classAttr . ">\n";
    echo "<main class=\"container\">\n";
}
