<?php

function update_user_last_login(string $username, string $loginAt = ''): bool {
    $username = trim($username);
    if ($username === '') {
        return false;
    }
    $loginAt = normalize_iso_datetime_to_jakarta($loginAt);
    if ($loginAt === '') {
        $loginAt = now_iso();
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

    $user->last_login_at = \Carbon\CarbonImmutable::parse($loginAt);

    return (bool) $user->save();
}
