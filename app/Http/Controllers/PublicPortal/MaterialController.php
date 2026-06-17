<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicMaterials\ShowPublicMaterialRequest;
use App\Models\ChurchFile;
use App\Models\PublicMaterialMenu;
use App\Services\PublicMaterials\PublicMaterialCatalog;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class MaterialController extends Controller
{
    public function redirectToMenu(ShowPublicMaterialRequest $request): RedirectResponse
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
        RuntimeBootstrap::boot($request);

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
            'canManageMaterials' => can_manage_public_materials(),
            'materialStatus' => trim((string) $request->query('material_status', '')),
            'materialError' => trim((string) $request->query('material_error', '')),
        ]);
    }

    public function upload(Request $request, PublicMaterialMenu $menu): RedirectResponse
    {
        RuntimeBootstrap::boot($request);

        if (! can_manage_public_materials()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $folderPath = normalize_church_folder_path((string) $menu->folder_path);
        if ($folderPath === '') {
            return $this->redirectToMaterialMenu($menu, ['material_error' => 'invalid_folder']);
        }

        $file = $request->file('material_file');
        if ($file === null || ! $file->isValid()) {
            return $this->redirectToMaterialMenu($menu, ['material_error' => 'missing_file']);
        }

        $size = (int) ($file->getSize() ?? 0);
        $maxBytes = 50 * 1024 * 1024;
        if ($size <= 0 || $size > $maxBytes) {
            return $this->redirectToMaterialMenu($menu, ['material_error' => 'file_too_large']);
        }

        $originalName = $this->cleanOriginalFileName((string) $file->getClientOriginalName());
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        }

        $allowedExtensions = secure_file_allowed_extensions();
        if ($extension === '' || ! isset($allowedExtensions[$extension])) {
            return $this->redirectToMaterialMenu($menu, ['material_error' => 'invalid_file_type']);
        }

        $relativeDir = 'uploads/files/' . $folderPath;
        $targetDir = rec_runtime_path($relativeDir);
        File::ensureDirectoryExists($targetDir);

        $targetFileName = generate_id('file') . '_' . date('YmdHis') . '.' . $extension;
        $file->move($targetDir, $targetFileName);
        $fullPath = $targetDir . '/' . $targetFileName;
        $relativePath = sanitize_relative_upload_path($relativeDir . '/' . $targetFileName);
        if ($relativePath === '' || ! is_file($fullPath)) {
            return $this->redirectToMaterialMenu($menu, ['material_error' => 'upload_failed']);
        }

        $title = $this->normalizeMaterialTitle(
            (string) $request->input('title', ''),
            pathinfo($originalName, PATHINFO_FILENAME) ?: 'Materi',
            $extension,
        );
        $downloadName = $this->downloadNameForTitle($title, $extension);

        DB::table('public_material_files')->insert([
            'public_material_menu_id' => $menu->id,
            'public_id' => $this->newPublicMaterialId(),
            'title' => $title,
            'category_name' => null,
            'description' => null,
            'relative_path' => $relativePath,
            'original_file_name' => $downloadName,
            'size_bytes' => max(0, (int) @filesize($fullPath)),
            'mime_type' => detect_file_mime_type($fullPath),
            'branch_id' => null,
            'branch_code' => 'pusat',
            'sort_order' => $this->nextSortOrder((int) $menu->id),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->redirectToMaterialMenu($menu, ['material_status' => 'uploaded']);
    }

    public function rename(Request $request, PublicMaterialMenu $menu, ChurchFile $churchFile): RedirectResponse
    {
        RuntimeBootstrap::boot($request);

        if (! can_manage_public_materials()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        if ((int) ($churchFile->public_material_menu_id ?? 0) !== (int) $menu->id) {
            abort(404, 'File tidak ditemukan.');
        }

        $path = sanitize_relative_upload_path((string) $churchFile->relative_path);
        $extension = secure_file_extension($path);
        $title = $this->normalizeMaterialTitle((string) $request->input('title', ''), '', $extension);
        if ($title === '') {
            return $this->redirectToMaterialMenu($menu, ['material_error' => 'missing_title']);
        }

        $churchFile->forceFill([
            'title' => $title,
            'original_file_name' => $this->downloadNameForTitle($title, $extension),
            'updated_at' => now(),
        ])->save();

        return $this->redirectToMaterialMenu($menu, ['material_status' => 'renamed']);
    }

    /**
     * @param array<string, string> $query
     */
    private function redirectToMaterialMenu(PublicMaterialMenu $menu, array $query = []): RedirectResponse
    {
        return redirect()->route('materials.show', array_merge(['menu' => $menu->menu_key], $query));
    }

    private function nextSortOrder(int $menuId): int
    {
        return ((int) DB::table('public_material_files')
            ->where('public_material_menu_id', $menuId)
            ->max('sort_order')) + 1;
    }

    private function newPublicMaterialId(): string
    {
        do {
            $id = 'church_file_' . bin2hex(random_bytes(4));
        } while (DB::table('public_material_files')->where('public_id', $id)->exists());

        return $id;
    }

    private function cleanOriginalFileName(string $name): string
    {
        $name = basename(str_replace('\\', '/', trim($name)));
        $name = preg_replace('/[\x00-\x1F\x7F"\\\\]+/', '_', $name) ?? '';

        return trim($name) !== '' ? trim($name) : 'materi';
    }

    private function normalizeMaterialTitle(string $value, string $fallback, string $extension): string
    {
        $title = trim($value);
        if ($title === '') {
            $title = trim($fallback);
        }
        if ($extension !== '' && preg_match('/\.' . preg_quote($extension, '/') . '$/i', $title) === 1) {
            $title = trim((string) preg_replace('/\.' . preg_quote($extension, '/') . '$/i', '', $title));
        }

        $title = preg_replace('/[\x00-\x1F\x7F"\\\\]+/', ' ', $title) ?? '';
        $title = trim((string) preg_replace('/\s+/', ' ', $title));
        if ($title === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($title, 0, 180) : substr($title, 0, 180);
    }

    private function downloadNameForTitle(string $title, string $extension): string
    {
        $downloadName = trim($title);
        if ($downloadName === '') {
            $downloadName = 'materi';
        }
        if ($extension !== '' && preg_match('/\.' . preg_quote($extension, '/') . '$/i', $downloadName) !== 1) {
            $downloadName .= '.' . $extension;
        }

        return function_exists('mb_substr') ? mb_substr($downloadName, 0, 240) : substr($downloadName, 0, 240);
    }
}
