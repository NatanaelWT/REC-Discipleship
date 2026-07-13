<?php

namespace App\Services\Maintenance;

use App\Contracts\MaintenanceTask;
use App\Models\MaintenanceRun;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class MaintenanceRunner
{
    /** @return array<int, array{key:string,label:string,details:array<string, mixed>}> */
    public function preview(): array
    {
        return array_map(
            static fn (MaintenanceTask $task): array => [
                'key' => $task->key(),
                'label' => $task->label(),
                'details' => $task->preview(),
            ],
            $this->tasks(),
        );
    }

    public function runBatch(MaintenanceRun $run): MaintenanceRun
    {
        // Keep browser-driven requests short even when a cached or environment
        // configuration contains an unsafe value. The lock must always outlive
        // the work deadline so two batches cannot overlap after a short TTL.
        $seconds = max(1, min(10, (int) config('activity.maintenance.batch_seconds', 8)));
        $lockSeconds = max($seconds + 5, min(300, (int) config('activity.maintenance.lock_seconds', 30)));
        $result = Cache::lock('rec:developer-maintenance', $lockSeconds)->get(
            fn (): MaintenanceRun => $this->runUnlocked($run->fresh() ?? $run, microtime(true) + $seconds),
        );

        if (! $result instanceof MaintenanceRun) {
            throw new RuntimeException('Maintenance sedang dijalankan oleh request lain.');
        }

        return $result;
    }

    private function runUnlocked(MaintenanceRun $run, float $deadline): MaintenanceRun
    {
        if (in_array($run->status, ['completed', 'failed'], true)) {
            return $run;
        }

        try {
            if ($run->dry_run) {
                $run->forceFill([
                    'status' => 'completed',
                    'started_at' => $run->started_at ?? now('UTC'),
                    'heartbeat_at' => now('UTC'),
                    'completed_at' => now('UTC'),
                    'summary' => ['preview' => $this->preview()],
                    'cursor' => ['task_index' => count($this->tasks())],
                ])->save();

                return $run;
            }

            $tasks = $this->tasks();
            if ($tasks === []) {
                throw new RuntimeException('Tidak ada task maintenance mutasi yang terdaftar.');
            }
            $cursor = is_array($run->cursor) ? $run->cursor : [];
            $index = max(0, (int) ($cursor['task_index'] ?? 0));
            $taskCursor = is_array($cursor['task_cursor'] ?? null) ? $cursor['task_cursor'] : [];
            $summaries = is_array($run->summary) ? $run->summary : [];
            $storedTaskKey = trim((string) ($cursor['task_key'] ?? ''));
            $currentTaskKey = ($tasks[$index] ?? null)?->key();
            if ($storedTaskKey !== '' && $currentTaskKey !== $storedTaskKey) {
                throw new RuntimeException('Daftar task maintenance berubah saat run masih aktif. Mulai run baru setelah konfigurasi diperbaiki.');
            }

            $batchSize = max(1, min(5000, (int) config('activity.maintenance.batch_size', 500)));

            $run->forceFill([
                'status' => 'running',
                'started_at' => $run->started_at ?? now('UTC'),
                'heartbeat_at' => now('UTC'),
            ])->save();

            while ($index < count($tasks) && microtime(true) < $deadline) {
                $task = $tasks[$index];
                $step = $task->run(
                    $taskCursor,
                    $batchSize,
                    $deadline,
                );
                $summaries[$task->key()] = $step['summary'];
                $taskCursor = $step['cursor'];
                if ($step['complete']) {
                    $index++;
                    $taskCursor = [];
                }

                $run->forceFill([
                    'cursor' => [
                        'task_index' => $index,
                        'task_key' => ($tasks[$index] ?? null)?->key(),
                        'task_cursor' => $taskCursor,
                    ],
                    'summary' => $summaries,
                    'heartbeat_at' => now('UTC'),
                ])->save();

                if (! $step['complete']) {
                    break;
                }
            }

            if ($index >= count($tasks)) {
                $run->forceFill([
                    'status' => 'completed',
                    'completed_at' => now('UTC'),
                    'heartbeat_at' => now('UTC'),
                ])->save();
            }

            return $run;
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'error_message' => function_exists('mb_substr')
                    ? mb_substr($exception->getMessage(), 0, 2000)
                    : substr($exception->getMessage(), 0, 2000),
                'heartbeat_at' => now('UTC'),
                'completed_at' => now('UTC'),
            ])->save();

            throw $exception;
        }
    }

    /** @return array<int, MaintenanceTask> */
    private function tasks(): array
    {
        $tasks = [];
        $keys = [];
        foreach ((array) config('maintenance.tasks', []) as $class) {
            $task = app($class);
            if (! $task instanceof MaintenanceTask) {
                throw new RuntimeException((string) $class.' harus mengimplementasikan MaintenanceTask.');
            }
            $key = trim($task->key());
            if ($key === '' || preg_match('/^[a-z0-9][a-z0-9_.:-]*$/', $key) !== 1) {
                throw new RuntimeException((string) $class.' memiliki key maintenance yang tidak valid.');
            }
            if (isset($keys[$key])) {
                throw new RuntimeException('Key task maintenance duplikat: '.$key.'.');
            }
            $keys[$key] = true;
            $tasks[] = $task;
        }

        return $tasks;
    }
}
