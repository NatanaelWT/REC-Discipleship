<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicMaterials\StreamPublicMaterialRequest;
use App\Models\ChurchFile;
use App\Models\PublicMaterialMenu;
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
            'menu' => $menu->menu_key,
            'churchFile' => $file->public_id,
        ]);
    }

    public function download(
        PublicMaterialMenu $menu,
        ChurchFile $churchFile,
        PublicMaterialCatalog $catalog,
        PublicMaterialFileStreamer $streamer,
    ): Response|StreamedResponse {
        if (! $catalog->fileBelongsToMenu($menu, $churchFile)) {
            return response('File tidak ditemukan.', 404);
        }

        return $streamer->stream($churchFile, 'attachment');
    }
}
