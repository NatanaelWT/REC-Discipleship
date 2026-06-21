<?php

namespace App\Http\Controllers\PublicPortal;

use App\Enums\PublicMaterialMenuKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicMaterials\ShowPublicMaterialRequest;
use App\Models\PublicMaterialFile;
use App\Services\Activity\ActivityRecorder;
use App\Services\PublicMaterials\PublicMaterialCatalog;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
        if (! $menu instanceof PublicMaterialMenuKey) {
            return redirect()->route('home');
        }

        $materialRows = $catalog->filesForMenu($menu);

        return view('public.materials.index', [
            'settings' => ['church_name' => app_church_name()],
            'menu' => $menu->value,
            'menuLabel' => $menu->label(),
            'menuSubtitle' => $menu->subtitle(),
            'menuFolder' => $menu->folder(),
            'materialRows' => $materialRows,
            'canManageMaterials' => can_manage_public_materials(),
            'materialStatus' => trim((string) $request->query('material_status', '')),
            'materialError' => trim((string) $request->query('material_error', '')),
        ]);
    }

    public function upload(Request $request, string $menu, ActivityRecorder $activity): RedirectResponse
    {
        RuntimeBootstrap::boot($request);

        if (! can_manage_public_materials()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $menu = PublicMaterialMenuKey::fromKey($menu);
        if (! $menu instanceof PublicMaterialMenuKey) {
            abort(404, 'Menu tidak ditemukan.');
        }

        $folderPath = $menu->folder();

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

        $targetDir = public_material_folder_full_path($folderPath);
        File::ensureDirectoryExists($targetDir);

        $targetFileName = Str::uuid()->toString().'_'.date('YmdHis').'.'.$extension;
        $file->move($targetDir, $targetFileName);
        $fullPath = $targetDir.'/'.$targetFileName;
        @chmod($fullPath, 0644);
        $relativePath = public_material_file_relative_path($folderPath, $targetFileName);
        if ($relativePath === '' || ! is_file($fullPath)) {
            return $this->redirectToMaterialMenu($menu, ['material_error' => 'upload_failed']);
        }
        $activity->onRollback(static function () use ($fullPath): void {
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        });

        $title = $this->normalizeMaterialTitle(
            (string) $request->input('title', ''),
            pathinfo($originalName, PATHINFO_FILENAME) ?: 'Materi',
            $extension,
        );
        $downloadName = $this->downloadNameForTitle($title, $extension);

        PublicMaterialFile::query()->create([
            'menu' => $menu->value,
            'title' => $title,
            'category_name' => null,
            'description' => null,
            'relative_path' => $relativePath,
            'original_file_name' => $downloadName,
            'size_bytes' => max(0, (int) @filesize($fullPath)),
            'mime_type' => detect_file_mime_type($fullPath),
        ]);

        return $this->redirectToMaterialMenu($menu, ['material_status' => 'uploaded']);
    }

    public function rename(Request $request, string $menu, PublicMaterialFile $churchFile): RedirectResponse
    {
        RuntimeBootstrap::boot($request);

        if (! can_manage_public_materials()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $menu = PublicMaterialMenuKey::fromKey($menu);
        if (! $menu instanceof PublicMaterialMenuKey || (string) ($churchFile->menu ?? '') !== $menu->value) {
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
     * @param  array<string, string>  $query
     */
    private function redirectToMaterialMenu(PublicMaterialMenuKey $menu, array $query = []): RedirectResponse
    {
        return redirect()->route('materials.show', array_merge(['menu' => $menu->value], $query));
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
        if ($extension !== '' && preg_match('/\.'.preg_quote($extension, '/').'$/i', $title) === 1) {
            $title = trim((string) preg_replace('/\.'.preg_quote($extension, '/').'$/i', '', $title));
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
        if ($extension !== '' && preg_match('/\.'.preg_quote($extension, '/').'$/i', $downloadName) !== 1) {
            $downloadName .= '.'.$extension;
        }

        return function_exists('mb_substr') ? mb_substr($downloadName, 0, 240) : substr($downloadName, 0, 240);
    }
}
