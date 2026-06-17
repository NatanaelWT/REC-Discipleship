<?php

function worship_penatalayan_font_path(bool $bold = false): string {
    $windowsDir = getenv('WINDIR');
    if (!is_string($windowsDir) || trim($windowsDir) === '') {
        $windowsDir = 'C:\\Windows';
    }
    $base = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $windowsDir), DIRECTORY_SEPARATOR);
    $candidates = $bold
        ? [
            $base . DIRECTORY_SEPARATOR . 'Fonts' . DIRECTORY_SEPARATOR . 'arialbd.ttf',
            $base . DIRECTORY_SEPARATOR . 'Fonts' . DIRECTORY_SEPARATOR . 'ARIALBD.TTF',
            $base . DIRECTORY_SEPARATOR . 'Fonts' . DIRECTORY_SEPARATOR . 'segoeuib.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        ]
        : [
            $base . DIRECTORY_SEPARATOR . 'Fonts' . DIRECTORY_SEPARATOR . 'arial.ttf',
            $base . DIRECTORY_SEPARATOR . 'Fonts' . DIRECTORY_SEPARATOR . 'ARIAL.TTF',
            $base . DIRECTORY_SEPARATOR . 'Fonts' . DIRECTORY_SEPARATOR . 'segoeui.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
            return $candidate;
        }
    }
    return '';
}
