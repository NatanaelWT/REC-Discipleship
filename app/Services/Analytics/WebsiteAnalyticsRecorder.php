<?php

namespace App\Services\Analytics;

use App\Models\ActivityRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebsiteAnalyticsRecorder
{
    public function __construct(
        private readonly AnalyticsVisitorIdentity $identities,
        private readonly BrowserLanguageClassifier $languages,
        private readonly WebsiteAnalyticsWriter $writer,
    ) {}

    public function record(string $activityId, Request $request, Response $response): void
    {
        if (! (bool) config('analytics.enabled', true)) {
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

    /**
     * Build analytics fields without reading or updating the activity row.
     *
     * @return array<string, mixed>
     */
    public function attributes(ActivityRequest $activity, Request $request, Response $response): array
    {
        if (! (bool) config('analytics.enabled', true) || ! $this->writer->qualifies($activity)) {
            return [];
        }

        $identity = $this->identities->resolve($activity, $request, $response);
        $purpose = strtolower(trim(implode(' ', [
            (string) $request->headers->get('purpose'),
            (string) $request->headers->get('sec-purpose'),
            (string) $request->headers->get('x-moz'),
        ])));

        return $this->writer->attributes(
            $activity,
            $identity,
            str_contains($purpose, 'prefetch'),
            $this->languages->classify($request->headers->get('Accept-Language')),
        );
    }
}
