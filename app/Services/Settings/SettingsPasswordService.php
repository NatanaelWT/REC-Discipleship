<?php

namespace App\Services\Settings;

use App\Models\User;
use App\Services\Auth\AuthCredentialService;
use Illuminate\Support\Facades\Hash;

class SettingsPasswordService
{
    public function __construct(private readonly AuthCredentialService $credentials)
    {
    }

    public function updatePassword(string $username, string $currentPassword, string $newPassword): ?string
    {
        $username = trim($username);
        if ($username === '') {
            return 'pw_wrong';
        }

        $user = User::query()->where('username', $username)->first();
        if (! $user instanceof User) {
            return 'pw_wrong';
        }

        if (! $this->credentials->passwordMatches((string) $user->password, $currentPassword)) {
            return 'pw_wrong';
        }

        $user->password = Hash::make($newPassword);
        if (! $user->save()) {
            return 'pw_save_failed';
        }

        return null;
    }
}
