<?php

namespace App\Http\Requests\PublicMaterials;

use App\Models\PublicMaterialFile;
use Illuminate\Foundation\Http\FormRequest;

class StreamPublicMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $routeMenu = $this->route('menu');
        $menuKey = trim((string) ($routeMenu ?? $this->query('menu', '')));
        $routeFile = $this->route('churchFile');
        $fileId = $routeFile instanceof PublicMaterialFile
            ? (int) $routeFile->getKey()
            : trim((string) ($routeFile ?? $this->query('id', '')));

        $this->merge([
            'menu' => normalize_public_material_menu($menuKey),
            'id' => $fileId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'menu' => ['nullable', 'string', 'max:120'],
            'id' => ['nullable', 'integer', 'min:1'],
            'raw' => ['nullable'],
        ];
    }

    public function materialMenuKey(): string
    {
        return (string) $this->input('menu', '');
    }

    public function fileId(): int
    {
        return (int) $this->input('id', 0);
    }

    public function rawPreview(): bool
    {
        return trim((string) $this->query('raw', '')) === '1';
    }
}
