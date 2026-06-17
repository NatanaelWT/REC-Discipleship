<?php

function create_msk_import_export_xlsx(array $participants, string &$errorCode): ?string {
    $errorCode = '';
    if (!class_exists('ZipArchive')) {
        $errorCode = 'zip_unavailable';
        return null;
    }

    $templatePath = rec_runtime_path('templates/import_pemuridan_template.xlsx');
    if (!is_file($templatePath)) {
        $errorCode = 'template_missing';
        return null;
    }

    $tempBasePath = tempnam(sys_get_temp_dir(), 'mskxlsx_');
    if ($tempBasePath === false) {
        $errorCode = 'export_failed';
        return null;
    }
    if (is_file($tempBasePath)) {
        @unlink($tempBasePath);
    }
    $xlsxPath = $tempBasePath . '.xlsx';

    $sourceZip = new ZipArchive();
    $openSource = $sourceZip->open($templatePath);
    if ($openSource !== true) {
        $errorCode = 'template_missing';
        return null;
    }

    $destZip = new ZipArchive();
    $openDest = $destZip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($openDest !== true) {
        $sourceZip->close();
        if (is_file($xlsxPath)) {
            @unlink($xlsxPath);
        }
        $errorCode = 'export_failed';
        return null;
    }

    $sheetXml = build_msk_import_export_sheet_xml(build_msk_import_export_rows($participants));
    $sheetReplaced = false;
    $copyFailed = false;

    for ($i = 0; $i < $sourceZip->numFiles; $i++) {
        $entryStat = $sourceZip->statIndex($i);
        $entryName = trim((string) ($entryStat['name'] ?? ''));
        if ($entryName === '') {
            continue;
        }

        if (substr($entryName, -1) === '/') {
            if (!$destZip->addEmptyDir(rtrim($entryName, '/'))) {
                $copyFailed = true;
                break;
            }
            continue;
        }

        if ($entryName === 'xl/worksheets/sheet1.xml') {
            $entryContent = $sheetXml;
            $sheetReplaced = true;
        } else {
            $entryContent = $sourceZip->getFromIndex($i);
            if (!is_string($entryContent)) {
                $copyFailed = true;
                break;
            }
        }

        if (!$destZip->addFromString($entryName, $entryContent)) {
            $copyFailed = true;
            break;
        }
    }

    $sourceZip->close();
    $destZip->close();

    if ($copyFailed || !$sheetReplaced || !is_file($xlsxPath)) {
        if (is_file($xlsxPath)) {
            @unlink($xlsxPath);
        }
        $errorCode = 'export_failed';
        return null;
    }

    return $xlsxPath;
}
