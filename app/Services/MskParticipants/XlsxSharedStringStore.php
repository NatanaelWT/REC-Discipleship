<?php

namespace App\Services\MskParticipants;

use RuntimeException;
use XMLReader;

final class XlsxSharedStringStore
{
    private const MAX_STRING_BYTES = 65535;

    /** @var resource|null */
    private $dataHandle = null;

    /** @var resource|null */
    private $indexHandle = null;

    private ?string $dataPath = null;

    private ?string $indexPath = null;

    private int $count = 0;

    public function build(string $xmlPath, ?float $deadline = null): void
    {
        $this->count = 0;
        $this->dataPath = tempnam(sys_get_temp_dir(), 'xlsx_strings_data_') ?: null;
        $this->indexPath = tempnam(sys_get_temp_dir(), 'xlsx_strings_idx_') ?: null;
        if ($this->dataPath === null || $this->indexPath === null) {
            throw new RuntimeException('Shared-string storage could not be created.');
        }
        $this->dataHandle = fopen($this->dataPath, 'w+b');
        $this->indexHandle = fopen($this->indexPath, 'w+b');
        if (! is_resource($this->dataHandle) || ! is_resource($this->indexHandle)) {
            throw new RuntimeException('Shared-string storage could not be opened.');
        }

        $reader = import_xlsx_xml_reader($xmlPath);
        try {
            while ($reader->read()) {
                if ($deadline !== null && microtime(true) >= $deadline) {
                    throw new MskImportException('import_timeout');
                }
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                    continue;
                }
                $value = import_xlsx_shared_string_text($reader);
                if (strlen($value) > self::MAX_STRING_BYTES) {
                    throw new MskImportException('import_cell_too_large', [
                        'max_bytes' => self::MAX_STRING_BYTES,
                    ]);
                }
                $offset = ftell($this->dataHandle);
                if ($offset === false
                    || fwrite($this->dataHandle, $value) !== strlen($value)
                    || fwrite($this->indexHandle, pack('J', $offset).pack('N', strlen($value))) !== 12) {
                    throw new RuntimeException('Shared-string storage write failed.');
                }
                $this->count++;
            }
        } finally {
            $reader->close();
        }
    }

    public function has(int $index): bool
    {
        return $index >= 0
            && $index < $this->count
            && is_resource($this->dataHandle)
            && is_resource($this->indexHandle);
    }

    public function get(int $index): string
    {
        if ($index < 0 || ! is_resource($this->dataHandle) || ! is_resource($this->indexHandle)) {
            return '';
        }
        if (fseek($this->indexHandle, $index * 12) !== 0) {
            return '';
        }
        $packed = fread($this->indexHandle, 12);
        if (! is_string($packed) || strlen($packed) !== 12) {
            return '';
        }
        $position = unpack('Joffset/Nlength', $packed);
        $offset = (int) ($position['offset'] ?? -1);
        $length = (int) ($position['length'] ?? 0);
        if ($offset < 0 || $length < 1 || fseek($this->dataHandle, $offset) !== 0) {
            return '';
        }

        $value = fread($this->dataHandle, $length);

        return is_string($value) ? $value : '';
    }

    public function close(): void
    {
        foreach (['dataHandle', 'indexHandle'] as $property) {
            if (is_resource($this->{$property})) {
                fclose($this->{$property});
            }
            $this->{$property} = null;
        }
        foreach (['dataPath', 'indexPath'] as $property) {
            if (is_string($this->{$property}) && is_file($this->{$property})) {
                @unlink($this->{$property});
            }
            $this->{$property} = null;
        }
        $this->count = 0;
    }

    public function __destruct()
    {
        $this->close();
    }
}
