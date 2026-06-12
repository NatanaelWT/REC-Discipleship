<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicMaterials\StreamPublicMaterialRequest;
use App\Models\ChurchFile;
use App\Models\PublicMaterialMenu;
use App\Services\PublicMaterials\PublicMaterialCatalog;
use App\Services\PublicMaterials\PublicMaterialFileStreamer;
use App\Services\PublicMaterials\PublicMaterialRouteResolver;
use App\Support\LegacyRuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialPreviewController extends Controller
{
    public function legacy(
        StreamPublicMaterialRequest $request,
        PublicMaterialRouteResolver $resolver,
    ): RedirectResponse|Response {
        $resolved = $resolver->resolve($request->materialMenuKey(), $request->publicFileId());
        if ($resolved === null) {
            return response('File tidak ditemukan.', 404);
        }

        [$menu, $file] = $resolved;

        return redirect()->route('materials.preview', array_filter([
            'menu' => $menu->menu_key,
            'churchFile' => $file->public_id,
            'raw' => $request->rawPreview() ? '1' : null,
        ]));
    }

    public function show(
        StreamPublicMaterialRequest $request,
        PublicMaterialMenu $menu,
        ChurchFile $churchFile,
        PublicMaterialCatalog $catalog,
        PublicMaterialFileStreamer $streamer,
    ): RedirectResponse|Response|StreamedResponse|View {
        LegacyRuntimeBootstrap::load();

        if (! $catalog->fileBelongsToMenu($menu, $churchFile)) {
            return response('File tidak ditemukan.', 404);
        }

        $row = $catalog->legacyFileRow($churchFile);
        $path = sanitize_relative_upload_path((string) ($row['path'] ?? ''));
        if ($path === '' || ! is_upload_path($path)) {
            return response('File tidak ditemukan.', 404);
        }

        $fullPath = legacy_runtime_path($path);
        if (! is_file($fullPath)) {
            return response('File tidak ditemukan.', 404);
        }

        if (! is_public_material_previewable_path($path)) {
            return redirect()->route('materials.download', [
                'menu' => $menu->menu_key,
                'churchFile' => $churchFile->public_id,
            ]);
        }

        $ext = secure_file_extension($path);
        $showDgJournalButton = is_public_material_dg_session_menu((string) $menu->menu_key);
        $showFeedbackButton = is_public_material_feedback_session((string) $menu->menu_key, $row);

        if ($ext === 'pdf' && ! $request->rawPreview() && $showDgJournalButton) {
            $previewTitle = trim((string) ($row['title'] ?? ''));
            $downloadName = trim((string) ($row['file_name'] ?? basename($path)));
            if ($previewTitle === '') {
                $previewTitle = $downloadName;
            }
            if ($previewTitle === '') {
                $previewTitle = 'Materi DG';
            }

            return view('public.materials.preview', [
                'settings' => ['church_name' => CHURCH_NAME],
                'previewTitle' => $previewTitle,
                'rawUrl' => route('materials.preview', [
                    'menu' => $menu->menu_key,
                    'churchFile' => $churchFile->public_id,
                    'raw' => '1',
                ]),
                'showFeedbackButton' => $showFeedbackButton,
                'feedbackSessionNumber' => public_material_session_number($row),
            ]);
        }

        return $streamer->stream($churchFile, 'inline');
    }
}
