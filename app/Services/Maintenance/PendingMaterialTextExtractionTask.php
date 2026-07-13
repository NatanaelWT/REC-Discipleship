<?php

namespace App\Services\Maintenance;

use App\Contracts\MaintenanceTask;
use App\Enums\PublicMaterialMenuKey;
use App\Models\PublicMaterialFile;
use App\Services\PublicMaterials\PublicMaterialTextExtractor;
use Throwable;

class PendingMaterialTextExtractionTask implements MaintenanceTask
{
    public function __construct(private readonly PublicMaterialTextExtractor $extractor) {}

    public function key(): string
    {
        return 'public_material_text';
    }

    public function label(): string
    {
        return 'Ekstraksi teks materi PDF pending';
    }

    public function preview(): array
    {
        return [
            'pending' => $this->pendingQuery()->count(),
            'max_bytes' => (int) config('media.pdf_text_max_bytes', 15 * 1024 * 1024),
        ];
    }

    public function run(array $cursor, int $batchSize, float $deadline): array
    {
        $lastId = max(0, (int) ($cursor['last_id'] ?? 0));
        $summary = is_array($cursor['summary'] ?? null) ? $cursor['summary'] : [
            'extracted' => 0,
            'failed' => 0,
            'too_large' => 0,
        ];
        $rows = $this->pendingQuery()
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit(max(1, min($batchSize, 5)))
            ->get();

        foreach ($rows as $row) {
            if (microtime(true) >= $deadline) {
                break;
            }
            $lastId = (int) $row->getKey();
            $menu = PublicMaterialMenuKey::fromKey((string) $row->menu);
            $path = $this->resolvePath((string) $row->relative_path);
            if (! $menu instanceof PublicMaterialMenuKey || $path === null) {
                $this->markFailed($row, 'File PDF tidak ditemukan.');
                $summary['failed']++;
                continue;
            }

            $size = (int) (@filesize($path) ?: 0);
            if ($size > (int) config('media.pdf_text_max_bytes', 15 * 1024 * 1024)) {
                $this->markFailed($row, 'PDF terlalu besar untuk ekstraksi pada hosting ini; file tetap dapat diunduh.');
                $summary['too_large']++;
                continue;
            }

            try {
                $payload = $this->extractor->extractForStorage($menu, $path);
            } catch (Throwable) {
                $this->markFailed($row, 'Ekstraksi teks gagal; file tetap dapat diunduh.');
                $summary['failed']++;

                // An attempted PDF parse is the maximum heavy work per request.
                break;
            }
            if ($payload === []) {
                $this->markFailed($row, 'Format materi tidak mendukung ekstraksi teks.');
                $summary['failed']++;
                continue;
            }
            $row->forceFill($payload)->save();
            if (trim((string) ($payload['text_extraction_error'] ?? '')) !== '') {
                $summary['failed']++;
            } else {
                $summary['extracted']++;
            }

            // Parsing one PDF is deliberately the maximum work per request.
            break;
        }

        $remaining = $this->pendingQuery()->where('id', '>', $lastId)->count();

        return [
            'complete' => $remaining === 0,
            'cursor' => ['last_id' => $lastId, 'summary' => $summary],
            'summary' => $summary + ['remaining' => $remaining],
        ];
    }

    private function pendingQuery()
    {
        $menus = array_map(
            static fn (PublicMaterialMenuKey $menu): string => $menu->value,
            array_values(array_filter(
                PublicMaterialMenuKey::cases(),
                static fn (PublicMaterialMenuKey $menu): bool => $menu->isDgSessionMenu(),
            )),
        );

        return PublicMaterialFile::query()
            ->whereIn('menu', $menus)
            ->whereRaw('LOWER(relative_path) LIKE ?', ['%.pdf'])
            ->whereNull('text_extracted_at');
    }

    private function resolvePath(string $relativePath): ?string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        $base = trim((string) config('public_materials.base_path', 'msk-dg'), '/');
        if (
            $relativePath === ''
            || str_contains($relativePath, "\0")
            || preg_match('#(^|/)\.\.(/|$)#', $relativePath) === 1
            || ($relativePath !== $base && ! str_starts_with($relativePath, $base.'/'))
        ) {
            return null;
        }

        $path = storage_path('app/public/'.$relativePath);

        return is_file($path) ? $path : null;
    }

    private function markFailed(PublicMaterialFile $row, string $message): void
    {
        $row->forceFill([
            'text_content' => null,
            'text_extracted_at' => now('UTC'),
            'text_extraction_error' => $message,
        ])->save();
    }
}
