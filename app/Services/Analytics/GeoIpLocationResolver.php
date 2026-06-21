<?php

namespace App\Services\Analytics;

use GeoIp2\Database\Reader;
use Throwable;

class GeoIpLocationResolver
{
    private ?Reader $reader = null;

    private bool $attempted = false;

    /** @return array{country_code:?string,country_name:?string,region_name:?string,city_name:?string} */
    public function resolve(?string $ip): array
    {
        $unknown = [
            'country_code' => null,
            'country_name' => null,
            'region_name' => null,
            'city_name' => null,
        ];
        $ip = trim((string) $ip);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return $unknown;
        }

        $reader = $this->reader();
        if (! $reader instanceof Reader) {
            return $unknown;
        }

        try {
            $record = $reader->city($ip);

            return [
                'country_code' => strtoupper(trim((string) $record->country->isoCode)) ?: null,
                'country_name' => trim((string) $record->country->name) ?: null,
                'region_name' => trim((string) $record->mostSpecificSubdivision->name) ?: null,
                'city_name' => trim((string) $record->city->name) ?: null,
            ];
        } catch (Throwable) {
            return $unknown;
        }
    }

    private function reader(): ?Reader
    {
        if ($this->attempted) {
            return $this->reader;
        }
        $this->attempted = true;
        $path = (string) config('analytics.geoip.database');
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        try {
            $this->reader = new Reader($path, ['id', 'en']);
        } catch (Throwable) {
            $this->reader = null;
        }

        return $this->reader;
    }
}
