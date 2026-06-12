<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicMaterials\ShowPublicMaterialRequest;
use App\Models\PublicMaterialMenu;
use App\Services\PublicMaterials\PublicMaterialCatalog;
use App\Support\LegacyRuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MaterialController extends Controller
{
    public function legacyIndex(ShowPublicMaterialRequest $request): RedirectResponse
    {
        $menuKey = $request->materialMenuKey();
        if ($menuKey === '') {
            return redirect()->route('home');
        }

        return redirect()->route('materials.show', ['menu' => $menuKey]);
    }

    public function show(
        ShowPublicMaterialRequest $request,
        PublicMaterialCatalog $catalog,
    ): RedirectResponse|View {
        LegacyRuntimeBootstrap::load();

        $menu = $catalog->menu($request->materialMenuKey());
        if (! $menu instanceof PublicMaterialMenu) {
            return redirect()->route('home');
        }

        $materialRows = $catalog->filesForMenu($menu);

        return view('public.materials.index', [
            'settings' => ['church_name' => CHURCH_NAME],
            'menu' => $menu->menu_key,
            'menuLabel' => trim((string) ($menu->label ?? 'Materi')),
            'menuSubtitle' => trim((string) ($menu->subtitle ?? 'Daftar file materi yang bisa diunduh.')),
            'menuFolder' => trim((string) ($menu->folder_path ?? '')),
            'materialRows' => $materialRows,
        ]);
    }
}
