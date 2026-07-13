<?php

function create_msk_import_export_xlsx(iterable $participants, string &$errorCode): ?string
{
    $errorCode = '';
    if (! class_exists('ZipArchive')) {
        $errorCode = 'zip_unavailable';

        return null;
    }

    $templatePath = rec_runtime_path('templates/import_pemuridan_template.xlsx');
    if (! is_file($templatePath)) {
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
    $xlsxPath = $tempBasePath.'.xlsx';

    $worksheetPath = null;
    $destZip = null;
    $zipOpen = false;
    $completed = false;

    try {
        $worksheetPath = write_msk_import_export_sheet_file($participants, $errorCode);
        if ($worksheetPath === null) {
            return null;
        }

        if (! @copy($templatePath, $xlsxPath)) {
            $errorCode = 'export_failed';

            return null;
        }

        $destZip = new ZipArchive;
        if ($destZip->open($xlsxPath) !== true) {
            $errorCode = 'export_failed';

            return null;
        }
        $zipOpen = true;

        $sheetEntry = 'xl/worksheets/sheet1.xml';
        if ($destZip->locateName($sheetEntry) === false
            || ! $destZip->deleteName($sheetEntry)
            || ! $destZip->addFile($worksheetPath, $sheetEntry)) {
            $errorCode = 'export_failed';

            return null;
        }

        if (! $destZip->close()) {
            $zipOpen = false;
            $errorCode = 'export_failed';

            return null;
        }
        $zipOpen = false;

        if (! is_file($xlsxPath)) {
            $errorCode = 'export_failed';

            return null;
        }

        $completed = true;

        return $xlsxPath;
    } catch (Throwable) {
        $errorCode = 'export_failed';

        return null;
    } finally {
        if ($zipOpen && $destZip instanceof ZipArchive) {
            $destZip->close();
        }
        if (is_string($worksheetPath) && is_file($worksheetPath)) {
            @unlink($worksheetPath);
        }
        if (! $completed && is_file($xlsxPath)) {
            @unlink($xlsxPath);
        }
    }
}
