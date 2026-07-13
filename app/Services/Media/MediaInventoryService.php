<?php

namespace App\Services\Media;

use App\Models\DiscipleshipMeetingReport;
use App\Models\Person;
use App\Services\Activity\ActivityContext;
use Carbon\CarbonImmutable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class MediaInventoryService
{
    private const ROOTS = [
        'uploads/peserta',
        'uploads/jemaat',
        'uploads/dg_reports',
    ];

    /**
     * @return array{
     *   referenced:array<int,string>,files:array<int,string>,missing:array<int,string>,orphans:array<int,string>,
     *   duplicate_groups:array<int,array<int,string>>,bytes:int,orphan_bytes:int,duplicate_bytes:int
     * }
     */
    public function scan(): array
    {
        $referenced = $this->referencedPaths();
        $files = $this->storedFiles();
        $referencedMap = array_fill_keys($referenced, true);
        $fileMap = array_fill_keys($files, true);
        $missing = array_values(array_filter($referenced, static fn (string $path): bool => ! isset($fileMap[$path])));
        $orphans = array_values(array_filter(
            $files,
            fn (string $path): bool => ! isset($referencedMap[$path]) && $this->isOldEnoughForQuarantine($path),
        ));
        $duplicateGroups = $this->duplicateGroups($files);

        return [
            'referenced' => $referenced,
            'files' => $files,
            'missing' => $missing,
            'orphans' => $orphans,
            'duplicate_groups' => $duplicateGroups,
            'bytes' => $this->totalBytes($files),
            'orphan_bytes' => $this->totalBytes($orphans),
            'duplicate_bytes' => $this->duplicateWasteBytes($duplicateGroups),
        ];
    }

    public function quarantine(string $relativePath, bool $verifiedOrphan = false): ?string
    {
        $relativePath = $this->safePath($relativePath);
        if (
            $relativePath === ''
            || ! $this->isOldEnoughForQuarantine($relativePath)
            || in_array($relativePath, $this->referencedPaths(), true)
            || (! $verifiedOrphan && ! in_array($relativePath, $this->scan()['orphans'], true))
        ) {
            return null;
        }

        $source = $this->absolutePath($relativePath);
        if (! is_file($source)) {
            return null;
        }

        $quarantineRelative = 'quarantine/media/'.now('UTC')->format('Ymd').'/'.$relativePath;
        $target = $this->absolutePath($quarantineRelative);
        $directory = dirname($target);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return null;
        }
        if (is_file($target)) {
            $quarantineRelative = 'quarantine/media/'.now('UTC')->format('Ymd').'/'.pathinfo($relativePath, PATHINFO_DIRNAME)
                .'/'.pathinfo($relativePath, PATHINFO_FILENAME).'_'.bin2hex(random_bytes(4)).'.'.pathinfo($relativePath, PATHINFO_EXTENSION);
            $target = $this->absolutePath($quarantineRelative);
        }
        // Recheck immediately before the filesystem mutation. A developer may
        // have attached the file to a record while the inventory scan ran.
        if (in_array($relativePath, $this->referencedPaths(), true) || ! rename($source, $target)) {
            return null;
        }

        try {
            if (in_array($relativePath, $this->referencedPaths(), true)) {
                rename($target, $source);

                return null;
            }
            $this->recordQuarantine($relativePath, $quarantineRelative, $target);
        } catch (\Throwable) {
            // A moved file without a manifest cannot be restored from the UI.
            // Roll the move back rather than leaving an invisible quarantine.
            @rename($target, $source);

            return null;
        }

        return $quarantineRelative;
    }

    /** @return array<int, array<string, mixed>> */
    public function quarantineEntries(): array
    {
        $manifest = $this->absolutePath('quarantine/media/manifest.jsonl');
        if (! is_file($manifest)) {
            return [];
        }

        $entries = [];
        foreach (file($manifest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $row = json_decode($line, true);
            if (! is_array($row)) {
                continue;
            }
            $target = $this->safePath((string) ($row['target'] ?? ''));
            if ($target === '' || ! str_starts_with($target, 'quarantine/media/')) {
                continue;
            }
            if (in_array(($row['action'] ?? 'quarantine'), ['restore', 'delete'], true)) {
                unset($entries[$target]);

                continue;
            }
            $source = $this->safePath((string) ($row['source'] ?? ''));
            if ($source === '') {
                continue;
            }
            $deleteAfter = (string) ($row['delete_after'] ?? '');
            try {
                $deletable = $deleteAfter !== '' && ! CarbonImmutable::parse($deleteAfter, 'UTC')->isFuture();
            } catch (\Throwable) {
                $deletable = false;
            }
            $entries[$target] = [
                'source' => $source,
                'target' => $target,
                'sha256' => (string) ($row['sha256'] ?? ''),
                'size' => max(0, (int) ($row['size'] ?? 0)),
                'quarantined_at' => (string) ($row['quarantined_at'] ?? ''),
                'delete_after' => $deleteAfter,
                'deletable' => $deletable,
                'available' => is_file($this->absolutePath($target)),
            ];
        }

        return array_values(array_filter($entries, static fn (array $entry): bool => $entry['available']));
    }

    public function restoreFromQuarantine(string $quarantineRelativePath): ?string
    {
        $quarantineRelativePath = $this->safePath($quarantineRelativePath);
        if ($quarantineRelativePath === '' || ! str_starts_with($quarantineRelativePath, 'quarantine/media/')) {
            return null;
        }

        $entry = collect($this->quarantineEntries())->firstWhere('target', $quarantineRelativePath);
        if (! is_array($entry)) {
            return null;
        }
        $sourceRelative = $this->safePath((string) ($entry['source'] ?? ''));
        $target = $this->absolutePath($quarantineRelativePath);
        $source = $this->absolutePath($sourceRelative);
        if (
            $sourceRelative === ''
            || ! is_file($target)
            || file_exists($source)
            || in_array($quarantineRelativePath, $this->referencedPaths(), true)
        ) {
            return null;
        }
        $expectedHash = strtolower(trim((string) ($entry['sha256'] ?? '')));
        $actualHash = @hash_file('sha256', $target);
        if (
            preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1
            || ! is_string($actualHash)
            || ! hash_equals($expectedHash, strtolower($actualHash))
        ) {
            return null;
        }

        $restore = function () use ($source, $target, $sourceRelative, $quarantineRelativePath, $expectedHash): bool {
            if (
                ! is_file($target)
                || file_exists($source)
                || in_array($quarantineRelativePath, $this->referencedPaths(), true)
            ) {
                return false;
            }
            $currentHash = @hash_file('sha256', $target);
            if (! is_string($currentHash) || ! hash_equals($expectedHash, strtolower($currentHash))) {
                return false;
            }
            $directory = dirname($source);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                return false;
            }
            if (! rename($target, $source)) {
                return false;
            }
            try {
                $this->appendQuarantineManifest([
                    'action' => 'restore',
                    'source' => $sourceRelative,
                    'target' => $quarantineRelativePath,
                    'sha256' => $currentHash,
                    'restored_at' => now('UTC')->toIso8601String(),
                ]);
            } catch (\Throwable) {
                @rename($source, $target);

                return false;
            }

            return true;
        };
        $activity = app(ActivityContext::class);
        if ($activity->active()) {
            $activity->onCommit($restore);

            return $sourceRelative;
        }
        if (! $restore()) {
            return null;
        }

        return $sourceRelative;
    }

    public function deleteFromQuarantine(string $quarantineRelativePath): bool
    {
        $quarantineRelativePath = $this->safePath($quarantineRelativePath);
        if ($quarantineRelativePath === '' || ! str_starts_with($quarantineRelativePath, 'quarantine/media/')) {
            return false;
        }

        $entry = collect($this->quarantineEntries())->firstWhere('target', $quarantineRelativePath);
        if (! is_array($entry)) {
            return false;
        }
        try {
            $deleteAfter = CarbonImmutable::parse((string) ($entry['delete_after'] ?? ''), 'UTC');
        } catch (\Throwable) {
            return false;
        }
        $sourceRelative = $this->safePath((string) ($entry['source'] ?? ''));
        $referenced = $this->referencedPaths();
        if (
            $deleteAfter->isFuture()
            || $sourceRelative === ''
            || in_array($sourceRelative, $referenced, true)
            || in_array($quarantineRelativePath, $referenced, true)
        ) {
            return false;
        }

        $target = $this->absolutePath($quarantineRelativePath);
        if (! is_file($target)) {
            return false;
        }
        $expectedHash = strtolower(trim((string) ($entry['sha256'] ?? '')));
        $actualHash = @hash_file('sha256', $target);
        if (
            preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1
            || ! is_string($actualHash)
            || ! hash_equals($expectedHash, strtolower($actualHash))
        ) {
            return false;
        }

        // Persist the authorization/checksum decision before unlinking. If a
        // later manifest append fails, the destructive action still has an
        // auditable intent record and will not be reported as unrecorded.
        try {
            $this->appendQuarantineManifest([
                'action' => 'delete_authorized',
                'source' => $sourceRelative,
                'target' => $quarantineRelativePath,
                'sha256' => $actualHash,
                'size' => max(0, (int) ($entry['size'] ?? 0)),
                'quarantined_at' => (string) ($entry['quarantined_at'] ?? ''),
                'delete_after' => (string) ($entry['delete_after'] ?? ''),
                'authorized_at' => now('UTC')->toIso8601String(),
            ]);
        } catch (\Throwable) {
            return false;
        }
        $delete = function () use ($target, $sourceRelative, $quarantineRelativePath, $expectedHash): bool {
            $referenced = $this->referencedPaths();
            if (
                in_array($sourceRelative, $referenced, true)
                || in_array($quarantineRelativePath, $referenced, true)
                || ! is_file($target)
            ) {
                return false;
            }
            $currentHash = @hash_file('sha256', $target);
            if (! is_string($currentHash) || ! hash_equals($expectedHash, strtolower($currentHash)) || ! @unlink($target)) {
                return false;
            }
            try {
                $this->appendQuarantineManifest([
                    'action' => 'delete',
                    'source' => $sourceRelative,
                    'target' => $quarantineRelativePath,
                    'sha256' => $currentHash,
                    'deleted_at' => now('UTC')->toIso8601String(),
                ]);
            } catch (\Throwable) {
                // The pre-delete authorization record above remains the durable
                // audit trail. The file is already gone.
            }

            return true;
        };
        $activity = app(ActivityContext::class);
        if ($activity->active()) {
            $activity->onCommit($delete);

            return true;
        }

        return $delete();
    }

    /** @return array<int, string> */
    private function referencedPaths(): array
    {
        $paths = [];
        foreach ([Person::class, DiscipleshipMeetingReport::class] as $modelClass) {
            foreach ($modelClass::query()->select(['id', 'photos'])->whereNotNull('photos')->cursor() as $model) {
                $photos = is_array($model->photos) ? $model->photos : [];
                foreach ($photos as $photo) {
                    if (! is_array($photo)) {
                        continue;
                    }
                    foreach (['path', 'web_path', 'thumbnail_path'] as $key) {
                        $path = $this->safePath((string) ($photo[$key] ?? ''));
                        if ($path !== '') {
                            $paths[$path] = true;
                        }
                    }
                }
            }
        }

        $paths = array_keys($paths);
        sort($paths);

        return $paths;
    }

    /** @return array<int, string> */
    private function storedFiles(): array
    {
        $files = [];
        foreach (self::ROOTS as $root) {
            $absoluteRoot = $this->absolutePath($root);
            if (! is_dir($absoluteRoot)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absoluteRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }
                $relativePath = $this->relativePath($file->getPathname());
                if ($relativePath !== '') {
                    $files[] = $relativePath;
                }
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    /** @param array<int,string> $files @return array<int,array<int,string>> */
    private function duplicateGroups(array $files): array
    {
        $bySize = [];
        foreach ($files as $path) {
            $size = @filesize($this->absolutePath($path));
            if (is_int($size) && $size > 0) {
                $bySize[$size][] = $path;
            }
        }

        $byHash = [];
        foreach ($bySize as $sameSize) {
            if (count($sameSize) < 2) {
                continue;
            }
            foreach ($sameSize as $path) {
                $hash = @hash_file('sha256', $this->absolutePath($path));
                if (is_string($hash) && $hash !== '') {
                    $byHash[$hash][] = $path;
                }
            }
        }

        return array_values(array_filter($byHash, static fn (array $paths): bool => count($paths) > 1));
    }

    /** @param array<int,string> $paths */
    private function totalBytes(array $paths): int
    {
        $total = 0;
        foreach ($paths as $path) {
            $size = @filesize($this->absolutePath($path));
            $total += is_int($size) ? $size : 0;
        }

        return $total;
    }

    /** @param array<int,array<int,string>> $groups */
    private function duplicateWasteBytes(array $groups): int
    {
        $waste = 0;
        foreach ($groups as $paths) {
            $size = isset($paths[0]) ? @filesize($this->absolutePath($paths[0])) : 0;
            if (is_int($size)) {
                $waste += max(0, count($paths) - 1) * $size;
            }
        }

        return $waste;
    }

    private function recordQuarantine(string $source, string $target, string $absoluteTarget): void
    {
        $hash = @hash_file('sha256', $absoluteTarget);
        $size = @filesize($absoluteTarget);
        if (! is_string($hash) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1 || ! is_int($size)) {
            throw new \RuntimeException('Checksum media quarantine tidak dapat dibuat.');
        }
        $this->appendQuarantineManifest([
            'action' => 'quarantine',
            'source' => $source,
            'target' => $target,
            'sha256' => $hash,
            'size' => $size,
            'quarantined_at' => now('UTC')->toIso8601String(),
            'delete_after' => now('UTC')->addDays((int) config('media.quarantine_days', 30))->toIso8601String(),
        ]);
    }

    /** @param array<string, mixed> $entry */
    private function appendQuarantineManifest(array $entry): void
    {
        $manifest = $this->absolutePath('quarantine/media/manifest.jsonl');
        $directory = dirname($manifest);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Direktori manifest quarantine tidak dapat dibuat.');
        }
        $row = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $line = $row.PHP_EOL;
        if (file_put_contents($manifest, $line, FILE_APPEND | LOCK_EX) !== strlen($line)) {
            throw new \RuntimeException('Manifest quarantine tidak dapat ditulis.');
        }
    }

    private function isOldEnoughForQuarantine(string $path): bool
    {
        $modifiedAt = @filemtime($this->absolutePath($path));

        return is_int($modifiedAt)
            && $modifiedAt <= now('UTC')->subHours((int) config('media.orphan_grace_hours', 24))->getTimestamp();
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

    private function relativePath(string $absolutePath): string
    {
        $root = str_replace('\\', '/', rtrim((string) config('media.private_root', storage_path('app/private')), '/\\')).'/';
        $absolutePath = str_replace('\\', '/', $absolutePath);

        return str_starts_with($absolutePath, $root) ? $this->safePath(substr($absolutePath, strlen($root))) : '';
    }
}
