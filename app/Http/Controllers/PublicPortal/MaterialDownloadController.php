<?php

namespace App\Http\Controllers\PublicPortal;

use App\Enums\PublicMaterialMenuKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicMaterials\StreamPublicMaterialRequest;
use App\Models\PublicMaterialFile;
use App\Services\PublicMaterials\PublicMaterialCatalog;
use App\Services\PublicMaterials\PublicMaterialFileStreamer;
use App\Services\PublicMaterials\PublicMaterialRouteResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialDownloadController extends Controller
{
    public function redirectToDownload(
        StreamPublicMaterialRequest $request,
        PublicMaterialRouteResolver $resolver,
    ): RedirectResponse|Response {
        $resolved = $resolver->resolve($request->materialMenuKey(), $request->publicFileId());
        if ($resolved === null) {
            return response('File tidak ditemukan.', 404);
        }

        [$menu, $file] = $resolved;

        return redirect()->route('materials.download', [
            'menu' => $menu->value,
            'churchFile' => $file->public_id,
        ]);
    }

    public function download(
        string $menu,
        PublicMaterialFile $churchFile,
        PublicMaterialCatalog $catalog,
        PublicMaterialFileStreamer $streamer,
    ): Response|StreamedResponse {
        $menu = PublicMaterialMenuKey::fromKey($menu);
        if (! $menu instanceof PublicMaterialMenuKey) {
            return response('File tidak ditemukan.', 404);
        }

        if (! $catalog->fileBelongsToMenu($menu, $churchFile)) {
            return response('File tidak ditemukan.', 404);
        }

        return $streamer->stream($churchFile, 'attachment');
    }
}
