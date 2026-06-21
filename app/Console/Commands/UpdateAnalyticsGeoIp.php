<?php

namespace App\Console\Commands;

use App\Services\Analytics\MaxMindDatabaseUpdater;
use Illuminate\Console\Command;
use Throwable;

class UpdateAnalyticsGeoIp extends Command
{
    protected $signature = 'analytics:geoip-update {--source= : Gunakan file GeoLite2-City.mmdb lokal}';

    protected $description = 'Unduh atau pasang database GeoLite2 City secara aman';

    public function handle(MaxMindDatabaseUpdater $updater): int
    {
        try {
            $target = $updater->update($this->option('source'));
            $this->info('Database GeoLite2 aktif: '.$target);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
