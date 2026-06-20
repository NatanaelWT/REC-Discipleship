<?php

namespace App\Services\Discipleship;

use Closure;
use Illuminate\Support\Facades\Cache;

class DiscipleshipReadCache
{
    private const VERSION_KEY = 'rec.discipleship-read.version';

    public function remember(string $segment, array $scope, Closure $callback): mixed
    {
        sort($scope);
        $store = Cache::store($this->cacheStore());
        $version = (string) $store->get(self::VERSION_KEY, '1');
        $key = 'rec.discipleship-read.v2.'.$version.'.'.$segment.'.'.sha1(json_encode($scope) ?: '[]');

        return $store->remember($key, now()->addMinutes(5), $callback);
    }

    public function invalidate(): void
    {
        Cache::store($this->cacheStore())->put(self::VERSION_KEY, (string) hrtime(true));
    }

    private function cacheStore(): string
    {
        return app()->environment('testing') ? 'array' : 'file';
    }
}
