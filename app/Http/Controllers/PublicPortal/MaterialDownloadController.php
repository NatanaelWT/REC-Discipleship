<?php

namespace App\Http\Controllers\PublicPortal;

use App\Enums\PublicMaterialMenuKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicMaterials\StreamPublicMaterialRequest;
use App\Models\PublicMaterialFile;
use App\Services\Activity\ActivityRecorder;
use App\Services\PublicMaterials\PublicMaterialCatalog;
use App\Services\PublicMaterials\PublicMaterialFileStreamer;
use App\Services\PublicMaterials\PublicMaterialRouteResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MaterialDownloadController extends Controller
{
    public function redirectToDownload(
        StreamPublicMaterialRequest $request,
        PublicMaterialRouteResolver $resolver,
    ): RedirectResponse|Response {
        $resolved = $resolver->resolve($request->materialMenuKey(), $request->fileId());
        if ($resolved === null) {
            return response('File tidak ditemukan.', 404);
        }

        [$menu, $file] = $resolved;

        return redirect()->route('materials.download', [
            'menu' => $menu->value,
            'churchFile' => $file->getKey(),
        ]);
    }

    public function download(
        string $menu,
        PublicMaterialFile $churchFile,
        PublicMaterialCatalog $catalog,
        PublicMaterialFileStreamer $streamer,
        ActivityRecorder $activity,
    ): BinaryFileResponse|Response {
        $menu = PublicMaterialMenuKey::fromKey($menu);
        if (! $menu instanceof PublicMaterialMenuKey) {
            return response('File tidak ditemukan.', 404);
        }

        if (! $catalog->fileBelongsToMenu($menu, $churchFile)) {
            return response('File tidak ditemukan.', 404);
        }

        $activity->record(
            'file',
            'material.downloaded',
            'materi_publik',
            $churchFile->getKey(),
            (string) $churchFile->title,
            'Materi diunduh.',
            metadata: $this->fileMetadata($churchFile),
        );

        return $streamer->stream($churchFile, 'attachment');
    }

    /** @return array<string, mixed> */
    private function fileMetadata(PublicMaterialFile $file): array
    {
        return [
            'name' => (string) $file->original_file_name,
            'size_bytes' => (int) $file->size_bytes,
            'mime_type' => (string) $file->mime_type,
            'sha256' => $this->storedChecksum($file),
        ];
    }

    private function storedChecksum(PublicMaterialFile $file): ?string
    {
        $checksum = strtolower(trim((string) $file->sha256));

        return preg_match('/\A[a-f0-9]{64}\z/', $checksum) === 1 ? $checksum : null;
    }
}
