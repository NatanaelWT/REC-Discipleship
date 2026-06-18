<?php

namespace App\Http\Requests\PublicMaterials;

use App\Models\PublicMaterialFile;
use App\Support\RuntimeBootstrap;
use Illuminate\Foundation\Http\FormRequest;

class StreamPublicMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        RuntimeBootstrap::load();

        $routeMenu = $this->route('menu');
        $menuKey = trim((string) ($routeMenu ?? $this->query('menu', '')));
        $routeFile = $this->route('churchFile');
        $publicFileId = $routeFile instanceof PublicMaterialFile
            ? (string) $routeFile->public_id
            : trim((string) ($routeFile ?? $this->query('id', '')));

        $this->merge([
            'menu' => normalize_public_material_menu($menuKey),
            'id' => $publicFileId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'menu' => ['nullable', 'string', 'max:120'],
            'id' => ['nullable', 'string', 'max:120'],
            'raw' => ['nullable'],
        ];
    }

    public function materialMenuKey(): string
    {
        return (string) $this->input('menu', '');
    }

    public function publicFileId(): string
    {
        return (string) $this->input('id', '');
    }

    public function rawPreview(): bool
    {
        return trim((string) $this->query('raw', '')) === '1';
    }
}
