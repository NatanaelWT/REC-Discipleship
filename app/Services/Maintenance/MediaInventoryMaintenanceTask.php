<?php

namespace App\Services\Maintenance;

use App\Contracts\MaintenanceTask;
use App\Services\Media\MediaInventoryService;
use App\Services\Media\MediaVariantManifestImporter;

class MediaInventoryMaintenanceTask implements MaintenanceTask
{
    public function __construct(
        private readonly MediaInventoryService $inventory,
        private readonly MediaVariantManifestImporter $manifestImporter,
    ) {}

    public function key(): string
    {
        return 'media_inventory';
    }

    public function label(): string
    {
        return 'Media: derivative, missing, dan orphan';
    }

    public function preview(): array
    {
        $scan = $this->inventory->scan();
        $manifest = $this->manifestImporter->validatedManifest();

        return [
            'files' => count($scan['files']),
            'bytes' => $scan['bytes'],
            'missing' => count($scan['missing']),
            'missing_sample' => array_slice($scan['missing'], 0, 20),
            'orphans' => count($scan['orphans']),
            'orphan_bytes' => $scan['orphan_bytes'],
            'orphan_sample' => array_slice($scan['orphans'], 0, 20),
            'duplicate_groups' => count($scan['duplicate_groups']),
            'duplicate_bytes' => $scan['duplicate_bytes'],
            'variant_manifest' => $manifest['summary'] + ['apply_candidates' => count($manifest['updates'])],
        ];
    }

    public function run(array $cursor, int $batchSize, float $deadline): array
    {
        $phase = (string) ($cursor['phase'] ?? 'manifest_validate');
        $summary = is_array($cursor['summary'] ?? null) ? $cursor['summary'] : [
            'manifest' => ['valid' => 0, 'changed' => 0, 'missing' => 0, 'invalid' => 0],
            'quarantined' => 0,
            'failed' => 0,
        ];
        $summary['manifest'] = is_array($summary['manifest'] ?? null)
            ? $summary['manifest']
            : ['valid' => 0, 'changed' => 0, 'missing' => 0, 'invalid' => 0];
        $summary['quarantined'] = (int) ($summary['quarantined'] ?? 0);
        $summary['failed'] = (int) ($summary['failed'] ?? 0);

        while (microtime(true) < $deadline) {
            if ($phase === 'manifest_validate') {
                $validation = $this->manifestImporter->validatedManifest();
                $summary['manifest'] = $validation['summary'];
                $cursor['manifest_updates'] = $validation['updates'];

                return $this->result(false, 'manifest_apply', $cursor, $summary);
            }

            if ($phase === 'manifest_apply') {
                $updates = is_array($cursor['manifest_updates'] ?? null) ? $cursor['manifest_updates'] : [];
                $step = $this->manifestImporter->applyBatch(
                    $updates,
                    is_array($cursor['manifest_cursor'] ?? null) ? $cursor['manifest_cursor'] : [],
                    $batchSize,
                    $deadline,
                );
                $summary['manifest']['changed'] = (int) ($summary['manifest']['changed'] ?? 0) + $step['changed'];
                $cursor['manifest_cursor'] = $step['cursor'];
                if (! $step['complete']) {
                    return $this->result(false, $phase, $cursor, $summary);
                }
                unset($cursor['manifest_updates'], $cursor['manifest_cursor']);

                return $this->result(false, 'inventory_scan', $cursor, $summary);
            }

            if ($phase === 'inventory_scan') {
                $scan = $this->inventory->scan();
                $orphans = array_values($scan['orphans']);
                $summary['inventory'] = [
                    'files' => count($scan['files']),
                    'bytes' => $scan['bytes'],
                    'missing' => count($scan['missing']),
                    'missing_sample' => array_slice($scan['missing'], 0, 20),
                    'orphans' => count($orphans),
                    'orphan_bytes' => $scan['orphan_bytes'],
                    'duplicate_groups' => count($scan['duplicate_groups']),
                    'duplicate_bytes' => $scan['duplicate_bytes'],
                ];
                $cursor['orphan_paths'] = $orphans;
                $cursor['orphan_index'] = 0;

                return $this->result(false, 'quarantine', $cursor, $summary);
            }

            if ($phase === 'quarantine') {
                $orphans = is_array($cursor['orphan_paths'] ?? null) ? array_values($cursor['orphan_paths']) : [];
                $index = max(0, (int) ($cursor['orphan_index'] ?? 0));
                $processed = 0;
                while ($index < count($orphans) && $processed < max(1, $batchSize) && microtime(true) < $deadline) {
                    $path = (string) $orphans[$index];
                    $index++;
                    $processed++;
                    if ($this->inventory->quarantine($path, true) !== null) {
                        $summary['quarantined']++;
                    } else {
                        $summary['failed']++;
                    }
                }
                $cursor['orphan_index'] = $index;
                $summary['remaining'] = max(0, count($orphans) - $index);
                $summary['permanent_deletion'] = 'requires_separate_confirmation_after_30_days';

                return $this->result($index >= count($orphans), $phase, $cursor, $summary);
            }

            throw new \RuntimeException('Cursor maintenance media tidak dikenal: '.$phase);
        }

        return $this->result(false, $phase, $cursor, $summary);
    }

    /** @return array{complete:bool,cursor:array<string,mixed>,summary:array<string,mixed>} */
    private function result(bool $complete, string $phase, array $cursor, array $summary): array
    {
        $cursor['phase'] = $phase;
        $cursor['summary'] = $summary;

        return ['complete' => $complete, 'cursor' => $complete ? [] : $cursor, 'summary' => $summary];
    }
}
