<?php

function update_user_password(string $username, string $newPassword): bool {
    if ($username === '') {
        return false;
    }
    if (
        ! class_exists(\App\Models\User::class)
        || ! \Illuminate\Support\Facades\Schema::hasTable('users')
        || ! \Illuminate\Support\Facades\Schema::hasColumn('users', 'username')
    ) {
        return false;
    }

    $user = \App\Models\User::query()->where('username', $username)->first();
    if (! $user instanceof \App\Models\User) {
        return false;
    }

    $user->password = $newPassword;

    return (bool) $user->save();
}
