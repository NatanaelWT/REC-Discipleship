<?php

namespace App\Services\Media;

use App\Models\DiscipleshipMeetingReport;
use App\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MediaVariantManifestImporter
{
    /**
     * @return array{valid: int, changed: int, missing: int, invalid: int}
     */
    public function import(bool $apply = false): array
    {
        $validation = $this->validatedManifest();
        $summary = $validation['summary'];
        if ($validation['updates'] === []) {
            return $summary;
        }

        $cursor = [];
        do {
            $step = $this->applyBatch($validation['updates'], $cursor, 500, INF, $apply);
            $cursor = $step['cursor'];
            $summary['changed'] += $step['changed'];
        } while (! $step['complete']);

        return $summary;
    }

    /**
     * @return array{
     *   updates:array<string,array<string,mixed>>,
     *   summary:array{valid:int,changed:int,missing:int,invalid:int}
     * }
     */
    public function validatedManifest(): array
    {
        $manifest = $this->manifest();
        $updates = [];
        $summary = ['valid' => 0, 'changed' => 0, 'missing' => 0, 'invalid' => 0];

        foreach ($manifest as $entry) {
            $original = $this->safePath((string) ($entry['original'] ?? ''));
            $webPath = $this->safePath((string) ($entry['web_path'] ?? ''));
            $thumbnailPath = $this->safePath((string) ($entry['thumbnail_path'] ?? ''));
            $sha256 = strtolower(trim((string) ($entry['sha256'] ?? '')));
            $webSha256 = strtolower(trim((string) ($entry['web_sha256'] ?? '')));
            $thumbnailSha256 = strtolower(trim((string) ($entry['thumbnail_sha256'] ?? '')));
            if ($original === '' || $webPath === '' || $thumbnailPath === ''
                || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1
                || preg_match('/^[a-f0-9]{64}$/', $webSha256) !== 1
                || preg_match('/^[a-f0-9]{64}$/', $thumbnailSha256) !== 1
                || basename($webPath) !== 'web_'.$sha256.'.webp'
                || basename($thumbnailPath) !== 'thumb_'.$sha256.'.webp') {
                $summary['invalid']++;

                continue;
            }

            $originalAbsolute = $this->absolutePath($original);
            $webAbsolute = $this->absolutePath($webPath);
            $thumbnailAbsolute = $this->absolutePath($thumbnailPath);
            if (! is_file($originalAbsolute) || ! is_file($webAbsolute) || ! is_file($thumbnailAbsolute)) {
                $summary['missing']++;

                continue;
            }
            if (! hash_equals($sha256, (string) hash_file('sha256', $originalAbsolute))) {
                $summary['invalid']++;

                continue;
            }
            $webInfo = $this->validatedVariant($webAbsolute, $webSha256, 1920, $entry, 'web');
            $thumbnailInfo = $this->validatedVariant($thumbnailAbsolute, $thumbnailSha256, 480, $entry, 'thumbnail');
            if ($webInfo === null || $thumbnailInfo === null) {
                $summary['invalid']++;

                continue;
            }

            $updates[$original] = [
                'sha256' => $sha256,
                'size' => max(0, (int) ($entry['size'] ?? filesize($originalAbsolute))),
                'width' => max(0, (int) ($entry['width'] ?? 0)),
                'height' => max(0, (int) ($entry['height'] ?? 0)),
                'web_path' => $webPath,
                'web_sha256' => $webSha256,
                'web_size' => $webInfo['size'],
                'web_width' => $webInfo['width'],
                'web_height' => $webInfo['height'],
                'thumbnail_path' => $thumbnailPath,
                'thumbnail_sha256' => $thumbnailSha256,
                'thumbnail_size' => $thumbnailInfo['size'],
                'thumbnail_width' => $thumbnailInfo['width'],
                'thumbnail_height' => $thumbnailInfo['height'],
                'variant_status' => 'ready',
            ];
            $summary['valid']++;
        }

        return ['updates' => $updates, 'summary' => $summary];
    }

    /**
     * @param  array<string,array<string,mixed>>  $updates
     * @param  array{model_index?:int,last_id?:int}  $cursor
     * @return array{complete:bool,cursor:array{model_index:int,last_id:int},changed:int}
     */
    public function applyBatch(
        array $updates,
        array $cursor,
        int $batchSize,
        float $deadline,
        bool $apply = true,
    ): array {
        $models = [Person::class, DiscipleshipMeetingReport::class];
        $modelIndex = max(0, (int) ($cursor['model_index'] ?? 0));
        $lastId = max(0, (int) ($cursor['last_id'] ?? 0));
        $changedCount = 0;
        $limit = max(1, min(1000, $batchSize));
        if ($updates === []) {
            return [
                'complete' => true,
                'cursor' => ['model_index' => count($models), 'last_id' => 0],
                'changed' => 0,
            ];
        }

        while ($modelIndex < count($models) && microtime(true) < $deadline) {
            $modelClass = $models[$modelIndex];
            $rows = $modelClass::query()
                ->select(['id', 'photos'])
                ->whereNotNull('photos')
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($limit)
                ->get();
            if ($rows->isEmpty()) {
                $modelIndex++;
                $lastId = 0;

                continue;
            }

            $processed = 0;
            foreach ($rows as $model) {
                if (microtime(true) >= $deadline) {
                    break;
                }
                if (! $model instanceof Model) {
                    continue;
                }
                $lastId = (int) $model->getKey();
                $processed++;

                $photos = is_array($model->photos) ? $model->photos : [];
                $changed = false;
                foreach ($photos as $index => $photo) {
                    if (! is_array($photo)) {
                        continue;
                    }
                    $path = $this->safePath((string) ($photo['path'] ?? ''));
                    if ($path === '' || ! isset($updates[$path])) {
                        continue;
                    }

                    $merged = [...$photo, ...$updates[$path]];
                    if ($merged !== $photo) {
                        $photos[$index] = $merged;
                        $changed = true;
                    }
                }

                if (! $changed) {
                    continue;
                }
                $changedCount++;
                if ($apply) {
                    DB::transaction(static function () use ($model, $photos): void {
                        $model->forceFill(['photos' => array_values($photos)])->save();
                    });
                }
            }

            if ($processed < $rows->count() || $rows->count() >= $limit) {
                return [
                    'complete' => false,
                    'cursor' => ['model_index' => $modelIndex, 'last_id' => $lastId],
                    'changed' => $changedCount,
                ];
            }

            $modelIndex++;
            $lastId = 0;
        }

        return [
            'complete' => $modelIndex >= count($models),
            'cursor' => ['model_index' => $modelIndex, 'last_id' => $lastId],
            'changed' => $changedCount,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function manifest(): array
    {
        $path = $this->absolutePath('media-variants-manifest.json');
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded['files'] ?? null) ? $decoded['files'] : [];
    }

    private function safePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, "\0") || preg_match('#(^|/)\.\.(/|$)#', $path) === 1) {
            return '';
        }

        return $path;
    }

    private function absolutePath(string $path): string
    {
        return rtrim((string) config('media.private_root', storage_path('app/private')), '/\\')
            .'/'.$this->safePath($path);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{size:int,width:int,height:int}|null
     */
    private function validatedVariant(
        string $path,
        string $checksum,
        int $maximumSide,
        array $entry,
        string $prefix,
    ): ?array {
        $actualChecksum = hash_file('sha256', $path);
        $size = filesize($path);
        $dimensions = @getimagesize($path);
        if (! is_string($actualChecksum) || ! hash_equals($checksum, $actualChecksum)
            || ! is_int($size) || $size <= 0
            || ! is_array($dimensions)
            || (int) ($dimensions[0] ?? 0) <= 0
            || (int) ($dimensions[1] ?? 0) <= 0
            || (int) ($dimensions[0] ?? 0) > $maximumSide
            || (int) ($dimensions[1] ?? 0) > $maximumSide
            || strtolower((string) ($dimensions['mime'] ?? '')) !== 'image/webp'
            || strtolower(trim((string) ($entry[$prefix.'_format'] ?? ''))) !== 'webp'
            || (int) ($entry[$prefix.'_size'] ?? 0) !== $size
            || (int) ($entry[$prefix.'_width'] ?? 0) !== (int) $dimensions[0]
            || (int) ($entry[$prefix.'_height'] ?? 0) !== (int) $dimensions[1]) {
            return null;
        }

        return ['size' => $size, 'width' => (int) $dimensions[0], 'height' => (int) $dimensions[1]];
    }
}
