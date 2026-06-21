<?php

namespace App\Console\Commands;

use App\Models\ActivityRequest;
use App\Services\Analytics\AnalyticsVisitorIdentity;
use App\Services\Analytics\WebsiteAnalyticsWriter;
use Illuminate\Console\Command;
use Throwable;

class BackfillWebsiteAnalytics extends Command
{
    protected $signature = 'analytics:backfill {--from= : Waktu UTC awal} {--to= : Waktu UTC akhir} {--chunk=500}';

    protected $description = 'Isi statistik website dari audit request lama secara idempotent';

    public function handle(WebsiteAnalyticsWriter $writer, AnalyticsVisitorIdentity $identities): int
    {
        $query = ActivityRequest::query()
            ->where('method', 'GET')
            ->whereBetween('http_status', [200, 299])
            ->where('response_content_type', 'like', 'text/html%')
            ->whereDoesntHave('websitePageView');
        if (trim((string) $this->option('from')) !== '') {
            $query->where('started_at', '>=', (string) $this->option('from'));
        }
        if (trim((string) $this->option('to')) !== '') {
            $query->where('started_at', '<=', (string) $this->option('to'));
        }

        $processed = 0;
        $failed = 0;
        $chunk = max(10, min(5000, (int) $this->option('chunk')));
        $query->orderBy('id')->chunkById($chunk, function ($activities) use ($writer, $identities, &$processed, &$failed): void {
            foreach ($activities as $activity) {
                try {
                    if ($writer->record($activity, $identities->legacy($activity)) !== null) {
                        $processed++;
                    }
                } catch (Throwable) {
                    $failed++;
                }
            }
        }, 'id');

        $this->info("Backfill selesai: {$processed} page view, {$failed} gagal.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
