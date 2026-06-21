<?php

namespace App\Services\Analytics;

use DeviceDetector\DeviceDetector;
use Illuminate\Support\Facades\Cache;
use Throwable;

class WebsiteClientClassifier
{
    /** @return array{device_type:string,browser_name:?string,os_name:?string,is_bot:bool} */
    public function classify(?string $userAgent): array
    {
        $userAgent = trim((string) $userAgent);
        if ($userAgent === '') {
            return $this->unknown();
        }

        $key = 'analytics.client.'.sha1($userAgent);

        return Cache::store(app()->environment('testing') ? 'array' : config('cache.default'))
            ->remember($key, now()->addDays(30), fn (): array => $this->parse($userAgent));
    }

    /** @return array{device_type:string,browser_name:?string,os_name:?string,is_bot:bool} */
    private function parse(string $userAgent): array
    {
        try {
            $detector = new DeviceDetector($userAgent);
            $detector->parse();
            $client = $detector->getClient();
            $os = $detector->getOs();
            $isBot = $detector->isBot();
            $device = $isBot ? 'bot' : strtolower(trim((string) $detector->getDeviceName()));

            return [
                'device_type' => $this->normalizeDevice($device),
                'browser_name' => is_array($client) ? (trim((string) ($client['name'] ?? '')) ?: null) : null,
                'os_name' => is_array($os) ? (trim((string) ($os['name'] ?? '')) ?: null) : null,
                'is_bot' => $isBot,
            ];
        } catch (Throwable) {
            return $this->unknown();
        }
    }

    private function normalizeDevice(string $device): string
    {
        return match ($device) {
            'smartphone', 'feature phone', 'phablet' => 'mobile',
            'tablet' => 'tablet',
            'desktop' => 'desktop',
            'bot' => 'bot',
            'tv', 'smart display' => 'tv',
            'console' => 'console',
            default => 'other',
        };
    }

    /** @return array{device_type:string,browser_name:?string,os_name:?string,is_bot:bool} */
    private function unknown(): array
    {
        return ['device_type' => 'unknown', 'browser_name' => null, 'os_name' => null, 'is_bot' => false];
    }
}
