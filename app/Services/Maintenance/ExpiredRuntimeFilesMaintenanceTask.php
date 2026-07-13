<?php

namespace App\Services\Maintenance;

use App\Contracts\MaintenanceTask;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ExpiredRuntimeFilesMaintenanceTask implements MaintenanceTask
{
    public function key(): string
    {
        return 'expired_runtime_files';
    }

    public function label(): string
    {
        return 'Cache, session, dan compiled view kedaluwarsa';
    }

    /** @return array<string, mixed> */
    public function preview(): array
    {
        $candidates = $this->candidates();

        return [
            'expired_files' => count($candidates),
            'reclaimable_bytes' => array_sum(array_column($candidates, 'bytes')),
            'runtime_root_valid' => $this->runtimeRoot() !== null,
            'session_lifetime_minutes' => (int) config('session.lifetime', 120),
            'compiled_view_retention_days' => (int) config('maintenance.compiled_view_retention_days', 7),
        ];
    }

    /**
     * @param array<string, mixed> $cursor
     * @return array{complete:bool,cursor:array<string,mixed>,summary:array<string,mixed>}
     */
    public function run(array $cursor, int $limit, float $deadline): array
    {
        $after = (string) ($cursor['after'] ?? '');
        $deleted = (int) ($cursor['deleted'] ?? 0);
        $failed = (int) ($cursor['failed'] ?? 0);
        $reclaimed = (int) ($cursor['reclaimed_bytes'] ?? 0);
        $processed = 0;
        $last = $after;
        $remaining = false;

        foreach ($this->candidates() as $candidate) {
            $path = $candidate['path'];
            if ($after !== '' && strcmp($path, $after) <= 0) {
                continue;
            }
            if ($processed >= max(1, $limit) || microtime(true) >= $deadline) {
                $remaining = true;
                break;
            }

            $processed++;
            $last = $path;
            if (is_file($path) && @unlink($path)) {
                $deleted++;
                $reclaimed += $candidate['bytes'];
            } else {
                $failed++;
            }
        }

        return [
            'complete' => ! $remaining,
            'cursor' => $remaining ? [
                'after' => $last,
                'deleted' => $deleted,
                'failed' => $failed,
                'reclaimed_bytes' => $reclaimed,
            ] : [],
            'summary' => [
                'deleted_files' => $deleted,
                'failed_files' => $failed,
                'reclaimed_bytes' => $reclaimed,
            ],
        ];
    }

    /** @return array<int, array{path:string,bytes:int}> */
    private function candidates(): array
    {
        $now = time();
        $sessionCutoff = $now - max(60, (int) config('session.lifetime', 120) * 60);
        $viewCutoff = $now - max(1, (int) config('maintenance.compiled_view_retention_days', 7)) * 86400;
        $candidates = [];

        $runtimeRoot = $this->runtimeRoot();
        if ($runtimeRoot === null) {
            return [];
        }

        foreach ($this->files($runtimeRoot.DIRECTORY_SEPARATOR.'sessions') as $file) {
            if ($file->getFilename() !== '.gitignore' && $file->getMTime() < $sessionCutoff) {
                $candidates[] = $this->entry($file);
            }
        }

        foreach ($this->files($runtimeRoot.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'data') as $file) {
            if ($file->getFilename() === '.gitignore') {
                continue;
            }
            $handle = @fopen($file->getPathname(), 'rb');
            $expiresAt = $handle !== false ? trim((string) fread($handle, 10)) : '';
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($expiresAt !== '' && ctype_digit($expiresAt) && (int) $expiresAt <= $now) {
                $candidates[] = $this->entry($file);
            }
        }

        foreach ($this->files($runtimeRoot.DIRECTORY_SEPARATOR.'views') as $file) {
            if ($file->getFilename() !== '.gitignore' && $file->getMTime() < $viewCutoff) {
                $candidates[] = $this->entry($file);
            }
        }

        usort($candidates, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        return $candidates;
    }

    /** @return array<int, SplFileInfo> */
    private function files(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && ! $file->isLink()) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /** @return array{path:string,bytes:int} */
    private function entry(SplFileInfo $file): array
    {
        return [
            'path' => $file->getPathname(),
            'bytes' => max(0, $file->getSize()),
        ];
    }

    private function runtimeRoot(): ?string
    {
        $configured = rtrim((string) config('maintenance.runtime_root', storage_path('framework')), '/\\');
        $root = realpath($configured);
        $allowed = realpath(storage_path('framework'));
        if (! is_string($root) || ! is_string($allowed)) {
            return null;
        }

        $root = str_replace('\\', '/', rtrim($root, '/\\'));
        $allowed = str_replace('\\', '/', rtrim($allowed, '/\\'));
        if (DIRECTORY_SEPARATOR === '\\') {
            $root = strtolower($root);
            $allowed = strtolower($allowed);
        }

        return $root === $allowed || str_starts_with($root, $allowed.'/') ? $root : null;
    }
}
