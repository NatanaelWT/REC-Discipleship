<?php

namespace App\Http\Controllers\PublicPortal;

use App\Enums\PublicMaterialMenuKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicMaterials\StreamPublicMaterialRequest;
use App\Models\PublicMaterialFile;
use App\Services\PublicMaterials\PublicMaterialCatalog;
use App\Services\PublicMaterials\PublicMaterialFileStreamer;
use App\Services\PublicMaterials\PublicMaterialRouteResolver;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialPreviewController extends Controller
{
    public function redirectToPreview(
        StreamPublicMaterialRequest $request,
        PublicMaterialRouteResolver $resolver,
    ): RedirectResponse|Response {
        $resolved = $resolver->resolve($request->materialMenuKey(), $request->publicFileId());
        if ($resolved === null) {
            return response('File tidak ditemukan.', 404);
        }

        [$menu, $file] = $resolved;

        return redirect()->route('materials.preview', array_filter([
            'menu' => $menu->value,
            'churchFile' => $file->public_id,
            'raw' => $request->rawPreview() ? '1' : null,
        ]));
    }

    public function show(
        StreamPublicMaterialRequest $request,
        string $menu,
        PublicMaterialFile $churchFile,
        PublicMaterialCatalog $catalog,
        PublicMaterialFileStreamer $streamer,
    ): RedirectResponse|Response|StreamedResponse|View {
        RuntimeBootstrap::load();

        $menu = PublicMaterialMenuKey::fromKey($menu);
        if (! $menu instanceof PublicMaterialMenuKey) {
            return response('File tidak ditemukan.', 404);
        }

        if (! $catalog->fileBelongsToMenu($menu, $churchFile)) {
            return response('File tidak ditemukan.', 404);
        }

        $row = $catalog->fileRow($churchFile);
        $path = sanitize_relative_upload_path((string) ($row['path'] ?? ''));
        if ($path === '' || ! is_public_material_path($path)) {
            return response('File tidak ditemukan.', 404);
        }

        if (public_material_resolve_path($path) === null) {
            return response('File tidak ditemukan.', 404);
        }

        if (! is_public_material_previewable_path($path)) {
            return redirect()->route('materials.download', [
                'menu' => $menu->value,
                'churchFile' => $churchFile->public_id,
            ]);
        }

        $ext = secure_file_extension($path);
        $showDgJournalButton = is_public_material_dg_session_menu($menu->value);
        $showFeedbackButton = is_public_material_feedback_session($menu->value, $row);

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
                'settings' => ['church_name' => app_church_name()],
                'previewTitle' => $previewTitle,
                'rawUrl' => route('materials.preview', [
                    'menu' => $menu->value,
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
