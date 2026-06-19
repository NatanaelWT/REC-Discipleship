<?php

namespace App\Http\Requests\MskParticipants;

use App\Services\Auth\CurrentUserContext;
use App\Services\Routing\AppPageRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class MskParticipantWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        RuntimeBootstrap::boot($this);
        $context = app(CurrentUserContext::class);

        return $context->isLoggedIn()
            && $context->canAccessPage('msk_classes')
            && $context->canUseAction($this->authorizationAction());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'public_id' => trim((string) $this->input('id', '')),
            'member_public_id' => trim((string) $this->input('member_id', '')),
            'full_name' => trim((string) $this->input('full_name', '')),
            'gender' => normalize_member_gender_value((string) $this->input('gender', '')),
            'birth_date_input' => trim((string) $this->input('birth_date', '')),
            'birth_date' => normalize_ymd_date((string) $this->input('birth_date', '')),
            'birth_place' => trim((string) $this->input('birth_place', '')),
            'address' => trim((string) $this->input('address', '')),
            'email' => strtolower(trim((string) $this->input('email', ''))),
            'whatsapp' => trim((string) $this->input('whatsapp', '')),
            'notes' => trim((string) $this->input('notes', '')),
            'batch_month_input' => trim((string) $this->input('batch_month', '')),
            'batch_month' => normalize_month_value((string) $this->input('batch_month', date('Y-m'))),
            'session_numbers' => normalize_msk_session_numbers($this->input('session_numbers', [])),
            'remove_photo_paths' => $this->normalizedPhotoPaths($this->input('remove_photo_paths', [])),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizedPhotoPaths(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $paths = [];
        foreach ($value as $path) {
            $safePath = sanitize_relative_upload_path((string) $path);
            if ($safePath !== '') {
                $paths[] = $safePath;
            }
        }

        return array_values(array_unique($paths));
    }

    protected function failedAuthorization(): void
    {
        $context = app(CurrentUserContext::class);
        if (! $context->isLoggedIn()) {
            throw new HttpResponseException(redirect()->route('auth.login'));
        }

        throw new HttpResponseException(
            redirect(AppPageRouteMap::pageUrl($context->homePage(), ['error' => 'access_denied'])),
        );
    }

    abstract protected function authorizationAction(): string;
}
