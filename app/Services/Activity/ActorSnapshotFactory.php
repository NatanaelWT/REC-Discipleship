<?php

namespace App\Services\Activity;

use App\Enums\UserAccessRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActorSnapshotFactory
{
    /** @return array<string, mixed> */
    public function current(Request $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return [
                'actor_type' => 'anonymous',
                'user_id' => null,
                'username' => null,
                'role' => null,
                'branch_id' => null,
                'branch_label' => null,
                'visitor_hash' => $this->visitorHash($request),
            ];
        }

        $branchId = is_numeric($user->branch_id) ? (int) $user->branch_id : null;
        $branchLabel = null;
        if ($branchId !== null && $branchId > 0) {
            $branchLabel = trim((string) $user->branch()->value('label')) ?: null;
        }

        return [
            'actor_type' => 'user',
            'user_id' => (int) $user->getKey(),
            'username' => trim((string) $user->username) ?: null,
            'role' => UserAccessRole::fromStoredValue((string) $user->access_scope)->value,
            'branch_id' => $branchId,
            'branch_label' => $branchLabel,
            'visitor_hash' => $this->visitorHash($request),
        ];
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    public function preferAuthenticated(array $before, array $after): array
    {
        return ($after['actor_type'] ?? 'anonymous') === 'user' ? $after : $before;
    }

    private function visitorHash(Request $request): ?string
    {
        $sessionId = $request->hasSession() ? trim((string) $request->session()->getId()) : '';
        $source = $sessionId !== ''
            ? 'session:'.$sessionId
            : 'client:'.trim((string) $request->ip()).'|'.trim((string) $request->userAgent());
        if ($source === 'client:|') {
            return null;
        }

        return hash_hmac('sha256', $source, (string) config('app.key', 'activity-audit'));
    }
}
