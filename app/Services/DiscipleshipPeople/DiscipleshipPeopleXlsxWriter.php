<?php

namespace App\Services\DiscipleshipPeople;

use Throwable;
use ZipArchive;

class DiscipleshipPeopleXlsxWriter
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, string|int|float|null>>  $rows
     */
    public function create(array $headers, iterable $rows, string $subtitle, string &$errorCode): ?string
    {
        $errorCode = '';
        if (! class_exists(ZipArchive::class)) {
            $errorCode = 'zip_unavailable';

            return null;
        }

        $tempBasePath = tempnam(sys_get_temp_dir(), 'dgpeople_');
        if ($tempBasePath === false) {
            $errorCode = 'export_failed';

            return null;
        }
        @unlink($tempBasePath);
        $xlsxPath = $tempBasePath.'.xlsx';
        $worksheetPath = null;
        $zip = null;
        $zipOpen = false;
        $completed = false;

        try {
            $worksheetPath = $this->writeWorksheetFile($headers, $rows, $subtitle);
            if ($worksheetPath === null) {
                $errorCode = 'export_failed';

                return null;
            }

            $zip = new ZipArchive;
            if ($zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $errorCode = 'export_failed';

                return null;
            }
            $zipOpen = true;

            $entries = [
                '[Content_Types].xml' => $this->contentTypesXml(),
                '_rels/.rels' => $this->rootRelationshipsXml(),
                'docProps/app.xml' => $this->appPropertiesXml(),
                'docProps/core.xml' => $this->corePropertiesXml(),
                'xl/workbook.xml' => $this->workbookXml(),
                'xl/_rels/workbook.xml.rels' => $this->workbookRelationshipsXml(),
                'xl/styles.xml' => $this->stylesXml(),
            ];

            foreach ($entries as $path => $contents) {
                if (! $zip->addFromString($path, $contents)) {
                    $errorCode = 'export_failed';

                    return null;
                }
            }

            if (! $zip->addFile($worksheetPath, 'xl/worksheets/sheet1.xml')) {
                $errorCode = 'export_failed';

                return null;
            }

            if (! $zip->close()) {
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
            if ($zipOpen && $zip instanceof ZipArchive) {
                $zip->close();
            }
            if (is_string($worksheetPath) && is_file($worksheetPath)) {
                @unlink($worksheetPath);
            }
            if (! $completed && is_file($xlsxPath)) {
                @unlink($xlsxPath);
            }
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, string|int|float|null>>  $rows
     */
    private function writeWorksheetFile(array $headers, iterable $rows, string $subtitle): ?string
    {
        $worksheetPath = tempnam(sys_get_temp_dir(), 'dgpeople_sheet_');
        if ($worksheetPath === false) {
            return null;
        }

        $handle = @fopen($worksheetPath, 'wb');
        if ($handle === false) {
            @unlink($worksheetPath);

            return null;
        }

        $lastColumn = export_xlsx_column_ref(max(0, count($headers) - 1));
        $lastRow = 4;
        $success = false;

        try {
            if (! $this->writeAll($handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                .'<sheetFormatPr defaultRowHeight="20"/>'
                .'<cols>'
                .'<col min="1" max="1" width="7" customWidth="1"/>'
                .'<col min="2" max="2" width="30" customWidth="1"/>'
                .'<col min="3" max="3" width="18" customWidth="1"/>'
                .'<col min="4" max="4" width="16" customWidth="1"/>'
                .'<col min="5" max="7" width="14" customWidth="1"/>'
                .'<col min="8" max="8" width="32" customWidth="1"/>'
                .'</cols><sheetData>'
                .'<row r="1" ht="28" customHeight="1">'.$this->inlineCell('A1', 'Daftar Anggota DG', 1).'</row>'
                .'<row r="2" ht="24" customHeight="1">'.$this->inlineCell('A2', $subtitle, 2).'</row>'
                .'<row r="4" ht="26" customHeight="1">')) {
                return null;
            }

            foreach (array_values($headers) as $column => $header) {
                if (! $this->writeAll($handle, $this->inlineCell(export_xlsx_column_ref($column).'4', $header, 3))) {
                    return null;
                }
            }
            if (! $this->writeAll($handle, '</row>')) {
                return null;
            }

            foreach ($rows as $row) {
                $excelRow = ++$lastRow;
                if (! $this->writeAll($handle, '<row r="'.$excelRow.'" ht="30" customHeight="1">')) {
                    return null;
                }
                foreach (array_values($row) as $column => $value) {
                    if (! $this->writeAll($handle, $this->inlineCell(export_xlsx_column_ref($column).$excelRow, (string) ($value ?? ''), 4))) {
                        return null;
                    }
                }
                if (! $this->writeAll($handle, '</row>')) {
                    return null;
                }
            }

            if (! $this->writeAll($handle, '</sheetData>'
                .'<autoFilter ref="A4:'.$lastColumn.$lastRow.'"/>'
                .'<mergeCells count="2"><mergeCell ref="A1:'.$lastColumn.'1"/><mergeCell ref="A2:'.$lastColumn.'2"/></mergeCells>'
                .'<pageMargins left="0.35" right="0.35" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
                .'</worksheet>')) {
                return null;
            }

            if (! fflush($handle)) {
                return null;
            }

            $success = true;

            return $worksheetPath;
        } finally {
            fclose($handle);
            if (! $success && is_file($worksheetPath)) {
                @unlink($worksheetPath);
            }
        }
    }

    /**
     * @param  resource  $handle
     */
    private function writeAll($handle, string $contents): bool
    {
        $length = strlen($contents);
        $offset = 0;

        while ($offset < $length) {
            $written = fwrite($handle, substr($contents, $offset));
            if ($written === false || $written === 0) {
                return false;
            }
            $offset += $written;
        }

        return true;
    }

    private function inlineCell(string $reference, string $value, int $style): string
    {
        return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
            .export_xlsx_inline_text($value)
            .'</t></is></c>';
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="18000" windowHeight="10000"/></bookViews>'
            .'<sheets><sheet name="Anggota DG" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="4">'
            .'<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="16"/><color rgb="FF0F172A"/><name val="Calibri"/></font>'
            .'<font><i/><sz val="10"/><color rgb="FF64748B"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD9E2EC"/></left><right style="thin"><color rgb="FFD9E2EC"/></right><top style="thin"><color rgb="FFD9E2EC"/></top><bottom style="thin"><color rgb="FFD9E2EC"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="5">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function corePropertiesXml(): string
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:creator>REC</dc:creator><cp:lastModifiedBy>REC</cp:lastModifiedBy>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$timestamp.'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$timestamp.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    private function appPropertiesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>REC</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop>'
            .'<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            .'<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>Anggota DG</vt:lpstr></vt:vector></TitlesOfParts>'
            .'</Properties>';
    }
}
