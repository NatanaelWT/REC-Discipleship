<?php

namespace App\Services\Activity;

use App\Models\ActivityRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ActivitySpool
{
    /** @param array<string, mixed> $attributes */
    public function append(array $attributes, ?Throwable $reason = null): bool
    {
        $payload = json_encode([
            'version' => 1,
            'spooled_at' => now('UTC')->toIso8601String(),
            'reason' => $reason?->getMessage(),
            'attributes' => $attributes,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (! is_string($payload)) {
            return false;
        }

        $line = $payload.PHP_EOL;
        $directory = $this->directory();
        $this->ensureDirectory($directory);
        if (! $this->reserveBytes(strlen($line))) {
            return false;
        }

        $path = $directory.DIRECTORY_SEPARATOR.now('UTC')->format('Y-m-d-H').'.jsonl';
        $handle = fopen($path, 'ab');
        if ($handle === false) {
            return false;
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                return false;
            }

            return fwrite($handle, $line) === strlen($line);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return array{processed:int,remaining:int,failed:int,quarantined:int,retryable_failed:int} */
    public function replayBatch(?int $limit = null): array
    {
        $limit = max(1, $limit ?? (int) config('activity.spool.replay_batch', 250));
        $files = $this->files();
        if ($files === []) {
            return ['processed' => 0, 'remaining' => 0, 'failed' => 0, 'quarantined' => 0, 'retryable_failed' => 0];
        }

        $path = $files[0];
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (! is_array($lines)) {
            return ['processed' => 0, 'remaining' => $this->lineCount(), 'failed' => 1, 'quarantined' => 0, 'retryable_failed' => 1];
        }

        $processed = 0;
        $attempted = 0;
        $failed = 0;
        $quarantined = 0;
        $retryableFailed = 0;
        $remainingLines = [];
        foreach ($lines as $index => $line) {
            if ($attempted >= $limit) {
                $remainingLines[] = $line;

                continue;
            }
            $attempted++;

            $decoded = json_decode($line, true);
            $attributes = is_array($decoded) ? ($decoded['attributes'] ?? null) : null;
            if (! is_array($attributes)) {
                $failed++;
                if ($this->quarantineInvalidLine($line, 'Payload JSONL tidak valid.')) {
                    $quarantined++;
                } else {
                    $remainingLines[] = $line;
                    $retryableFailed++;
                }

                continue;
            }

            try {
                $model = new ActivityRequest;
                $model->forceFill($attributes);
                DB::table($model->getTable())->insertOrIgnore($model->getAttributes());
                $processed++;
            } catch (Throwable) {
                $remainingLines[] = $line;
                $remainingLines = array_merge($remainingLines, array_slice($lines, $index + 1));
                $failed++;
                $retryableFailed++;
                break;
            }
        }

        $this->replaceFile($path, $remainingLines);

        return [
            'processed' => $processed,
            'remaining' => $this->lineCount(),
            'failed' => $failed,
            'quarantined' => $quarantined,
            'retryable_failed' => $retryableFailed,
        ];
    }

    public function lineCount(): int
    {
        $count = 0;
        foreach ($this->files() as $file) {
            $handle = fopen($file, 'rb');
            if ($handle === false) {
                continue;
            }
            while (fgets($handle) !== false) {
                $count++;
            }
            fclose($handle);
        }

        return $count;
    }

    public function size(): int
    {
        return array_sum(array_map(static fn (string $file): int => (int) filesize($file), $this->files()));
    }

    private function directory(): string
    {
        $relative = trim((string) config('activity.spool.directory', 'activity-spool'), '/\\');
        if ($relative === '' || str_contains($relative, '..')) {
            throw new RuntimeException('Direktori spool aktivitas tidak valid.');
        }

        return storage_path('app/private/'.$relative);
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Direktori spool aktivitas tidak dapat dibuat.');
        }
    }

    /** @return array<int, string> */
    private function files(): array
    {
        $directory = $this->directory();
        if (! is_dir($directory)) {
            return [];
        }

        $files = glob($directory.DIRECTORY_SEPARATOR.'*.jsonl') ?: [];
        sort($files, SORT_STRING);

        return array_values(array_filter($files, 'is_file'));
    }

    private function reserveBytes(int $incoming): bool
    {
        $maximum = (int) config('activity.spool.max_bytes', 536_870_912);
        $files = $this->files();
        $size = array_sum(array_map(static fn (string $file): int => (int) filesize($file), $files));

        // Never evict raw requests that are still inside the retention window.
        // Under disk pressure preserving already-recorded data is preferable to
        // silently deleting the oldest valid requests.
        if ($size + $incoming > $maximum) {
            $cutoff = CarbonImmutable::now('UTC')
                ->subDays(max(1, (int) config('activity.retention_days', 90)));
            foreach ($files as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                try {
                    $hour = CarbonImmutable::createFromFormat('!Y-m-d-H', $name, 'UTC');
                } catch (Throwable) {
                    $hour = false;
                }
                if (! $hour instanceof CarbonImmutable || $hour->addHour()->isAfter($cutoff)) {
                    continue;
                }

                $bytes = is_file($file) ? (int) filesize($file) : 0;
                if (@unlink($file)) {
                    $size -= $bytes;
                }
                if ($size + $incoming <= $maximum) {
                    break;
                }
            }
        }

        return $size + $incoming <= $maximum;
    }

    /** @param array<int, string> $lines */
    private function replaceFile(string $path, array $lines): void
    {
        if ($lines === []) {
            if (is_file($path) && ! @unlink($path)) {
                throw new RuntimeException('Spool aktivitas yang selesai diproses tidak dapat dihapus.');
            }

            return;
        }

        $temporary = $path.'.tmp';
        $payload = implode(PHP_EOL, $lines).PHP_EOL;
        if (file_put_contents($temporary, $payload, LOCK_EX) !== strlen($payload)) {
            @unlink($temporary);
            throw new RuntimeException('Spool aktivitas tidak dapat ditulis ulang.');
        }
        if (! @rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Spool aktivitas tidak dapat diganti secara atomik.');
        }
    }

    private function quarantineInvalidLine(string $line, string $reason): bool
    {
        $directory = $this->directory().DIRECTORY_SEPARATOR.'invalid';
        try {
            $this->ensureDirectory($directory);
            $payload = json_encode([
                'quarantined_at' => now('UTC')->toIso8601String(),
                'reason' => $reason,
                'line' => $line,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if (! is_string($payload)) {
                return false;
            }
            $record = $payload.PHP_EOL;
            $maximum = max(1_048_576, (int) config('activity.spool.max_bytes', 10_485_760));
            $existingBytes = array_sum(array_map(
                static fn (string $file): int => is_file($file) ? (int) filesize($file) : 0,
                glob($directory.DIRECTORY_SEPARATOR.'*.jsonl') ?: [],
            ));
            if ($existingBytes + strlen($record) > $maximum) {
                return false;
            }
            $path = $directory.DIRECTORY_SEPARATOR.now('UTC')->format('Y-m-d').'.jsonl';

            return file_put_contents($path, $record, FILE_APPEND | LOCK_EX) === strlen($record);
        } catch (Throwable) {
            return false;
        }
    }
}
