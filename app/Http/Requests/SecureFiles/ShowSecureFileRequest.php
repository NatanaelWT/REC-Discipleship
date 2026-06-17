<?php

namespace App\Http\Requests\SecureFiles;

use App\Support\RuntimeBootstrap;
use Illuminate\Foundation\Http\FormRequest;

class ShowSecureFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        RuntimeBootstrap::boot($this);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function queryText(string $key): string
    {
        foreach ([$key, 'amp;' . $key, 'amp;amp;' . $key] as $candidateKey) {
            $value = $this->query($candidateKey);
            if (is_string($value)) {
                return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            }
        }

        return '';
    }

    public function filePath(): string
    {
        return $this->queryText('path');
    }

    public function requestedDownloadName(): string
    {
        return trim($this->queryText('name'));
    }

    public function downloadRequested(): bool
    {
        return $this->queryText('download') === '1';
    }

    public function rawRequested(): bool
    {
        return $this->queryText('raw') === '1';
    }
}
