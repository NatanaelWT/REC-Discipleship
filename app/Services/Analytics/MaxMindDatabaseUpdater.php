<?php

namespace App\Services\Analytics;

use GeoIp2\Database\Reader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PharData;
use RuntimeException;
use Throwable;

class MaxMindDatabaseUpdater
{
    public function update(?string $source = null): string
    {
        $target = (string) config('analytics.geoip.database');
        if ($target === '') {
            throw new RuntimeException('Lokasi database GeoLite2 belum dikonfigurasi.');
        }

        $temporaryDirectory = storage_path('app/geoip/update-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($temporaryDirectory);

        try {
            $database = $source !== null && trim($source) !== ''
                ? $this->localSource($source)
                : $this->download($temporaryDirectory);
            $this->validate($database);
            $this->replace($database, $target);

            return $target;
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    private function localSource(string $source): string
    {
        $source = realpath(trim($source)) ?: '';
        if ($source === '' || ! is_file($source)) {
            throw new RuntimeException('File GeoLite2 sumber tidak ditemukan.');
        }

        return $source;
    }

    private function download(string $directory): string
    {
        $licenseKey = trim((string) config('analytics.geoip.license_key'));
        if ($licenseKey === '') {
            throw new RuntimeException('MAXMIND_LICENSE_KEY belum diisi.');
        }

        $url = (string) config('analytics.geoip.download_url');
        $params = ['edition_id' => 'GeoLite2-City', 'license_key' => $licenseKey, 'suffix' => 'tar.gz'];
        $archiveResponse = Http::timeout(180)->retry(2, 1000)->get($url, $params)->throw();
        $checksumResponse = Http::timeout(60)->retry(2, 1000)->get($url, [...$params, 'suffix' => 'tar.gz.sha256'])->throw();
        $archive = $directory.DIRECTORY_SEPARATOR.'GeoLite2-City.tar.gz';
        File::put($archive, $archiveResponse->body());

        $expected = strtolower(substr(trim($checksumResponse->body()), 0, 64));
        $actual = hash_file('sha256', $archive);
        if ($expected === '' || ! hash_equals($expected, $actual)) {
            throw new RuntimeException('Checksum database GeoLite2 tidak valid.');
        }

        $tar = substr($archive, 0, -3);
        (new PharData($archive))->decompress();
        $extractDirectory = $directory.DIRECTORY_SEPARATOR.'extracted';
        File::ensureDirectoryExists($extractDirectory);
        (new PharData($tar))->extractTo($extractDirectory, null, true);

        foreach (File::allFiles($extractDirectory) as $file) {
            if ($file->getFilename() === 'GeoLite2-City.mmdb') {
                return $file->getPathname();
            }
        }

        throw new RuntimeException('GeoLite2-City.mmdb tidak ditemukan dalam arsip.');
    }

    private function validate(string $database): void
    {
        try {
            $reader = new Reader($database, ['id', 'en']);
            $reader->metadata();
            $reader->close();
        } catch (Throwable $exception) {
            throw new RuntimeException('Database GeoLite2 tidak valid.', previous: $exception);
        }
    }

    private function replace(string $source, string $target): void
    {
        File::ensureDirectoryExists(dirname($target));
        $pending = $target.'.new';
        $backup = $target.'.bak';
        File::delete([$pending, $backup]);
        if (! File::copy($source, $pending)) {
            throw new RuntimeException('Database GeoLite2 gagal disalin.');
        }

        if (File::exists($target) && ! File::move($target, $backup)) {
            File::delete($pending);
            throw new RuntimeException('Database GeoLite2 lama gagal dicadangkan.');
        }

        try {
            if (! File::move($pending, $target)) {
                throw new RuntimeException('Database GeoLite2 baru gagal diaktifkan.');
            }
            File::delete($backup);
        } catch (Throwable $exception) {
            File::delete($pending);
            if (File::exists($backup)) {
                File::move($backup, $target);
            }
            throw $exception;
        }
    }
}
