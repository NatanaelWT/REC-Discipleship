<?php

namespace App\Services\Maintenance;

use App\Contracts\MaintenanceTask;
use App\Models\PublicMaterialFile;

class PublicMaterialChecksumTask implements MaintenanceTask
{
    public function key(): string
    {
        return 'public_material_checksums';
    }

    public function label(): string
    {
        return 'Checksum materi publik pending';
    }

    public function preview(): array
    {
        return ['pending' => $this->pendingQuery()->count()];
    }

    public function run(array $cursor, int $batchSize, float $deadline): array
    {
        $lastId = max(0, (int) ($cursor['last_id'] ?? 0));
        $summary = is_array($cursor['summary'] ?? null) ? $cursor['summary'] : [
            'checksummed' => 0,
            'missing' => 0,
            'failed' => 0,
        ];
        $row = $this->pendingQuery()
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->first();

        if ($row instanceof PublicMaterialFile && microtime(true) < $deadline) {
            $lastId = (int) $row->getKey();
            $path = $this->resolvePath((string) $row->relative_path);
            if ($path === null) {
                $summary['missing']++;
            } else {
                $checksum = @hash_file('sha256', $path);
                if (is_string($checksum) && preg_match('/\A[a-f0-9]{64}\z/', $checksum) === 1) {
                    $row->forceFill(['sha256' => $checksum])->save();
                    $summary['checksummed']++;
                } else {
                    $summary['failed']++;
                }
            }
        }

        // One file per browser batch keeps hashing large PDFs within the
        // maintenance request time budget.
        $remaining = $this->pendingQuery()->where('id', '>', $lastId)->count();

        return [
            'complete' => $remaining === 0,
            'cursor' => ['last_id' => $lastId, 'summary' => $summary],
            'summary' => $summary + ['remaining' => $remaining],
        ];
    }

    private function pendingQuery()
    {
        return PublicMaterialFile::query()->whereNull('sha256');
    }

    private function resolvePath(string $relativePath): ?string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        $base = trim((string) config('public_materials.base_path', 'msk-dg'), '/');
        if ($relativePath === '' || str_contains($relativePath, "\0")
            || preg_match('#(^|/)\.\.(/|$)#', $relativePath) === 1
            || ($relativePath !== $base && ! str_starts_with($relativePath, $base.'/'))) {
            return null;
        }

        $path = storage_path('app/public/'.$relativePath);

        return is_file($path) ? $path : null;
    }
}
