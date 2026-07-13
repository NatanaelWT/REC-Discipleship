<?php

namespace Tests\Feature;

use App\Services\DiscipleshipPeople\DiscipleshipPeopleXlsxWriter;
use App\Services\MskParticipants\XlsxSharedStringStore;
use App\Support\RuntimeBootstrap;
use stdClass;
use Tests\TestCase;
use ZipArchive;

class XlsxStreamingTest extends TestCase
{
    /** @var array<int, string> */
    private array $temporaryPaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        RuntimeBootstrap::load();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_xlsx_reader_streams_shared_and_inline_strings_and_cleans_temporary_xml(): void
    {
        $path = $this->createXlsxFixture([
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/sharedStrings.xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="2" uniqueCount="2">'
                .'<si><t>Plain</t></si>'
                .'<si><r><t>Rich </t></r><r><t>Text</t></r><rPh><t>Ignored</t></rPh></si>'
                .'</sst>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
                .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="C1" t="inlineStr"><is><r><t>Inline </t></r><r><t>Rich</t></r></is></c></row>'
                .'<row r="3"><c r="B3" t="s"><v>1</v></c><c r="C3"><v>42</v></c></row>'
                .'</sheetData></worksheet>',
        ]);
        $before = $this->temporaryFiles('xlsx_xml_*');

        $errorCode = '';
        $sheets = import_read_xlsx_sheets($path, $errorCode);

        $this->assertSame('', $errorCode);
        $this->assertSame([
            ['Plain', '', 'Inline Rich'],
            ['', 'Rich Text', '42'],
        ], $sheets['Data'] ?? null);
        $this->assertSame($before, $this->temporaryFiles('xlsx_xml_*'));
    }

    public function test_xlsx_reader_cleans_extracted_files_when_workbook_is_invalid(): void
    {
        $path = $this->createXlsxFixture([
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook/>',
        ]);
        $before = $this->temporaryFiles('xlsx_xml_*');

        $errorCode = '';
        $sheets = import_read_xlsx_sheets($path, $errorCode);

        $this->assertSame([], $sheets);
        $this->assertSame('invalid_excel', $errorCode);
        $this->assertSame($before, $this->temporaryFiles('xlsx_xml_*'));
    }

    public function test_shared_strings_use_bounded_disk_backing_with_random_access(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'shared_strings_fixture_');
        $this->assertNotFalse($path);
        $this->temporaryPaths[] = $path;
        file_put_contents($path, '<?xml version="1.0" encoding="UTF-8"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<si><t>Pertama</t></si><si><r><t>Kedua </t></r><r><t>Panjang</t></r></si>'
            .'</sst>');
        $beforeData = $this->temporaryFiles('xlsx_strings_data_*');
        $beforeIndex = $this->temporaryFiles('xlsx_strings_idx_*');

        $store = new XlsxSharedStringStore;
        $store->build($path);
        $this->assertSame('Pertama', $store->get(0));
        $this->assertSame('Kedua Panjang', $store->get(1));
        $this->assertSame('', $store->get(999));
        $store->close();

        $this->assertSame($beforeData, $this->temporaryFiles('xlsx_strings_data_*'));
        $this->assertSame($beforeIndex, $this->temporaryFiles('xlsx_strings_idx_*'));
    }

    public function test_people_writer_streams_a_large_worksheet_and_cleans_success_and_failure_files(): void
    {
        $writer = app(DiscipleshipPeopleXlsxWriter::class);
        $rows = (static function (): \Generator {
            for ($index = 1; $index <= 2000; $index++) {
                yield [$index, 'Anggota '.$index, 'Kutisari'];
            }
        })();
        $sheetBefore = $this->temporaryFiles('dgpeople_sheet_*');
        $outputBefore = $this->temporaryFiles('dgpeople_*.xlsx');

        $errorCode = '';
        $path = $writer->create(['No.', 'Nama', 'Cabang'], $rows, '2.000 anggota', $errorCode);

        $this->assertNotNull($path);
        $this->assertSame('', $errorCode);
        $this->temporaryPaths[] = $path;
        $sheets = import_read_xlsx_sheets($path, $errorCode);
        $this->assertSame('', $errorCode);
        $this->assertSame('Anggota 1', $sheets['Anggota DG'][3][1] ?? null);
        $this->assertSame('Anggota 2000', $sheets['Anggota DG'][2002][1] ?? null);
        @unlink($path);
        $this->assertSame($sheetBefore, $this->temporaryFiles('dgpeople_sheet_*'));
        $this->assertSame($outputBefore, $this->temporaryFiles('dgpeople_*.xlsx'));

        $errorCode = '';
        $failedPath = $writer->create(['Nama'], (static function (): \Generator {
            yield [new stdClass];
        })(), 'invalid', $errorCode);
        $this->assertNull($failedPath);
        $this->assertSame('export_failed', $errorCode);
        $this->assertSame($sheetBefore, $this->temporaryFiles('dgpeople_sheet_*'));
        $this->assertSame($outputBefore, $this->temporaryFiles('dgpeople_*.xlsx'));
    }

    public function test_msk_writer_replaces_template_sheet_by_file_and_cleans_success_and_failure_files(): void
    {
        $sheetBefore = $this->temporaryFiles('msk_sheet_*');
        $outputBefore = $this->temporaryFiles('mskxlsx_*.xlsx');
        $participants = (static function (): \Generator {
            for ($index = 1; $index <= 1000; $index++) {
                yield [
                    'id' => $index,
                    'full_name' => 'Peserta MSK '.$index,
                    'whatsapp' => '08120000'.$index,
                    'gender' => $index % 2 === 0 ? 'Laki-laki' : 'Perempuan',
                    'birth_date' => '2000-01-01',
                    'birth_place' => 'Surabaya',
                    'address' => 'Alamat '.$index,
                    'email' => 'PESERTA'.$index.'@EXAMPLE.TEST',
                    'msk_month' => '2026-07',
                    'session_numbers' => [3, 1, 3],
                    'notes' => 'Catatan & tindak lanjut',
                ];
            }
        })();

        $errorCode = '';
        $path = create_msk_import_export_xlsx($participants, $errorCode);

        $this->assertNotNull($path);
        $this->assertSame('', $errorCode);
        $this->temporaryPaths[] = $path;
        $sheets = import_read_xlsx_sheets($path, $errorCode);
        $this->assertSame('', $errorCode);
        $this->assertSame('participant_id', $sheets['Kelas MSK'][0][0] ?? null);
        $this->assertSame('Peserta MSK 1', $sheets['Kelas MSK'][1][1] ?? null);
        $this->assertSame('peserta1@example.test', $sheets['Kelas MSK'][1][7] ?? null);
        $this->assertSame('1,3', $sheets['Kelas MSK'][1][9] ?? null);
        $this->assertSame('Peserta MSK 1000', $sheets['Kelas MSK'][1000][1] ?? null);
        @unlink($path);
        $this->assertSame($sheetBefore, $this->temporaryFiles('msk_sheet_*'));
        $this->assertSame($outputBefore, $this->temporaryFiles('mskxlsx_*.xlsx'));

        $errorCode = '';
        $failedPath = create_msk_import_export_xlsx((static function (): \Generator {
            yield ['full_name' => new stdClass];
        })(), $errorCode);
        $this->assertNull($failedPath);
        $this->assertSame('export_failed', $errorCode);
        $this->assertSame($sheetBefore, $this->temporaryFiles('msk_sheet_*'));
        $this->assertSame($outputBefore, $this->temporaryFiles('mskxlsx_*.xlsx'));
    }

    /** @param array<string, string> $entries */
    private function createXlsxFixture(array $entries): string
    {
        $basePath = tempnam(sys_get_temp_dir(), 'xlsx_fixture_');
        $this->assertNotFalse($basePath);
        @unlink($basePath);
        $path = $basePath.'.xlsx';
        $this->temporaryPaths[] = $path;

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        foreach ($entries as $entry => $contents) {
            $this->assertTrue($zip->addFromString($entry, $contents));
        }
        $this->assertTrue($zip->close());

        return $path;
    }

    /** @return array<int, string> */
    private function temporaryFiles(string $pattern): array
    {
        $files = glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.$pattern) ?: [];
        sort($files, SORT_STRING);

        return array_values($files);
    }
}
