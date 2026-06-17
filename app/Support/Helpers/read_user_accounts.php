<?php

function read_user_accounts(): array {
    if (
        ! class_exists(\App\Models\User::class)
        || ! \Illuminate\Support\Facades\Schema::hasTable('users')
        || ! \Illuminate\Support\Facades\Schema::hasColumn('users', 'username')
    ) {
        return [];
    }

    return \App\Models\User::query()
        ->whereNotNull('username')
        ->orderBy('username')
        ->get()
        ->map(static function (\App\Models\User $user): array {
            return [
                'username' => (string) $user->username,
                'password' => (string) $user->password,
                'cabang' => (string) ($user->branch_code ?? 'kutisari'),
                'access_scope' => (string) ($user->access_scope ?? 'branch'),
                'last_login_at' => $user->last_login_at?->format('Y-m-d\TH:i:sP') ?? '',
            ];
        })
        ->values()
        ->all();
}
