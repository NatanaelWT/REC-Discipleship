<?php

namespace App\Services\Analytics;

use App\Models\ActivityRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class WebsiteAnalyticsRecorder
{
    public function __construct(
        private readonly AnalyticsVisitorIdentity $identities,
        private readonly BrowserLanguageClassifier $languages,
        private readonly WebsiteAnalyticsWriter $writer,
    ) {}

    public function record(string $activityId, Request $request, Response $response): void
    {
        if (! (bool) config('analytics.enabled', true) || ! $this->tablesAvailable()) {
            return;
        }

        $activity = ActivityRequest::query()->find($activityId);
        if (! $activity instanceof ActivityRequest || ! $this->writer->qualifies($activity)) {
            return;
        }

        $identity = $this->identities->resolve($activity, $request, $response);
        $purpose = strtolower(trim(implode(' ', [
            (string) $request->headers->get('purpose'),
            (string) $request->headers->get('sec-purpose'),
            (string) $request->headers->get('x-moz'),
        ])));
        $this->writer->record(
            $activity,
            $identity,
            str_contains($purpose, 'prefetch'),
            $this->languages->classify($request->headers->get('Accept-Language')),
        );
    }

    private function tablesAvailable(): bool
    {
        try {
            return Schema::hasTable('aktivitas') && Schema::hasTable('sesi');
        } catch (Throwable) {
            return false;
        }
    }
}
