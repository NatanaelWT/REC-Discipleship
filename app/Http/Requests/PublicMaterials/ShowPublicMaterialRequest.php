<?php

namespace App\Http\Requests\PublicMaterials;

use App\Support\RuntimeBootstrap;
use Illuminate\Foundation\Http\FormRequest;

class ShowPublicMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        RuntimeBootstrap::load();

        $this->merge([
            'menu' => normalize_public_material_menu((string) ($this->route('menu') ?? $this->query('menu', ''))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'menu' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function materialMenuKey(): string
    {
        return (string) $this->input('menu', '');
    }
}
