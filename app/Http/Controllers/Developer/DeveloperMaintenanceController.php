<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRun;
use App\Models\User;
use App\Services\Maintenance\MaintenanceRunner;
use App\Services\Media\MediaInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class DeveloperMaintenanceController extends Controller
{
    public function index(Request $request, MaintenanceRunner $runner, MediaInventoryService $media): RedirectResponse|View
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $active = MaintenanceRun::query()
            ->whereIn('status', ['pending', 'running'])
            ->orderByDesc('created_at')
            ->first();
        $confirmed = $active instanceof MaintenanceRun && $this->isConfirmed($request, $active);
        $lastCompletedMutation = MaintenanceRun::query()
            ->where('dry_run', false)
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->first();

        return view('developer.maintenance.index', [
            'settings' => ['church_name' => app_church_name()],
            'currentPage' => 'developer_config',
            'preview' => $runner->preview(),
            'runs' => MaintenanceRun::query()->orderByDesc('created_at')->limit(10)->get(),
            'activeRun' => $active,
            'activeRunConfirmed' => $confirmed,
            'lastCompletedMutation' => $lastCompletedMutation,
            'maintenanceOverdue' => ! ($lastCompletedMutation instanceof MaintenanceRun)
                || $lastCompletedMutation->completed_at?->lessThan(now('UTC')->subDays(7)) !== false,
            'idempotencyKey' => (string) Str::uuid(),
            'errorCode' => trim((string) $request->query('error', '')),
            'statusCode' => trim((string) $request->query('status', '')),
            'quarantineEntries' => $media->quarantineEntries(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'dry_run' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();
        if (! $user instanceof User || ! Hash::check((string) $validated['current_password'], (string) $user->password)) {
            return redirect()->route('developer.maintenance', ['error' => 'password_invalid']);
        }

        $dryRun = $request->boolean('dry_run');
        if (! $dryRun && ! app_maintenance_mode_enabled()) {
            return redirect()->route('developer.maintenance', ['error' => 'maintenance_required']);
        }

        /** @var array{run?:MaintenanceRun,error?:string}|null $selection */
        $selection = Cache::lock('rec:developer-maintenance-start', 15)->get(function () use ($dryRun, $user, $validated): array {
            $active = MaintenanceRun::query()
                ->whereIn('status', ['pending', 'running'])
                ->orderByDesc('created_at')
                ->first();
            if ($active instanceof MaintenanceRun) {
                return $active->dry_run === $dryRun
                    ? ['run' => $active]
                    : ['error' => 'run_mode_mismatch'];
            }

            $idempotencyKey = (string) $validated['idempotency_key'];
            $existing = MaintenanceRun::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing instanceof MaintenanceRun) {
                if ((int) $existing->requested_by_user_id !== (int) $user->getKey() || $existing->dry_run !== $dryRun) {
                    return ['error' => 'idempotency_conflict'];
                }

                return ['run' => $existing];
            }

            return ['run' => MaintenanceRun::query()->create([
                'idempotency_key' => $idempotencyKey,
                'requested_by_user_id' => (int) $user->getKey(),
                'requested_by_username' => (string) $user->username,
                'status' => 'pending',
                'dry_run' => $dryRun,
                'cursor' => ['task_index' => 0, 'task_cursor' => []],
                'summary' => [],
            ])];
        });
        if ($selection === null) {
            return redirect()->route('developer.maintenance', ['error' => 'start_busy']);
        }
        if (isset($selection['error'])) {
            return redirect()->route('developer.maintenance', ['error' => $selection['error']]);
        }
        $run = $selection['run'] ?? null;
        if (! $run instanceof MaintenanceRun) {
            return redirect()->route('developer.maintenance', ['error' => 'start_busy']);
        }

        $this->confirm($request, $run);

        return redirect()->route('developer.maintenance', ['status' => 'started']);
    }

    public function batch(Request $request, string $maintenanceRun, MaintenanceRunner $runner): JsonResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return response()->json(['message' => 'Autentikasi developer diperlukan.'], 401);
        }

        $run = MaintenanceRun::query()->findOrFail($maintenanceRun);
        if (! $this->isConfirmed($request, $run)) {
            return response()->json(['message' => 'Konfirmasi password telah kedaluwarsa.'], 403);
        }
        if (! $run->dry_run && ! app_maintenance_mode_enabled()) {
            return response()->json(['message' => 'Aktifkan maintenance mode sebelum melanjutkan.'], 409);
        }

        try {
            $run = $runner->runBatch($run);
        } catch (RuntimeException $exception) {
            $fresh = $run->fresh() ?? $run;

            return response()->json([
                'message' => $exception->getMessage(),
                'status' => $fresh->status,
                'summary' => $fresh->summary ?? [],
            ], str_contains($exception->getMessage(), 'request lain') ? 409 : 500);
        } catch (Throwable) {
            $fresh = $run->fresh() ?? $run;

            return response()->json([
                'message' => 'Batch maintenance gagal. Periksa detail run dan log aplikasi.',
                'status' => $fresh->status,
                'summary' => $fresh->summary ?? [],
            ], 500);
        }

        return response()->json([
            'id' => (string) $run->getKey(),
            'status' => (string) $run->status,
            'task' => data_get($run->cursor, 'task_key'),
            'summary' => $run->summary ?? [],
            'completed_at' => $run->completed_at?->toIso8601String(),
        ]);
    }

    public function restoreQuarantine(Request $request, MediaInventoryService $media): RedirectResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string', 'max:255'],
            'quarantine_path' => ['required', 'string', 'max:1024'],
        ]);
        $user = $request->user();
        if (! $user instanceof User || ! Hash::check((string) $validated['current_password'], (string) $user->password)) {
            return redirect()->route('developer.maintenance', ['error' => 'password_invalid']);
        }

        $restored = $media->restoreFromQuarantine((string) $validated['quarantine_path']);

        return redirect()->route('developer.maintenance', [
            $restored !== null ? 'status' : 'error' => $restored !== null ? 'quarantine_restored' : 'quarantine_restore_failed',
        ]);
    }

    public function deleteQuarantine(Request $request, MediaInventoryService $media): RedirectResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string', 'max:255'],
            'quarantine_path' => ['required', 'string', 'max:1024'],
            'confirmation' => ['required', 'string', 'in:HAPUS PERMANEN'],
        ]);
        $user = $request->user();
        if (! $user instanceof User || ! Hash::check((string) $validated['current_password'], (string) $user->password)) {
            return redirect()->route('developer.maintenance', ['error' => 'password_invalid']);
        }
        if (! app_maintenance_mode_enabled()) {
            return redirect()->route('developer.maintenance', ['error' => 'maintenance_required']);
        }

        $deleted = $media->deleteFromQuarantine((string) $validated['quarantine_path']);
        if ($deleted) {
            return redirect()->route('developer.maintenance', ['status' => 'quarantine_deleted']);
        }

        return redirect()->route('developer.maintenance', ['error' => 'quarantine_delete_failed']);
    }

    private function guard(Request $request): ?RedirectResponse
    {
        if (! is_logged_in()) {
            return redirect()->route('auth.login');
        }
        if (! can_manage_users()) {
            abort(403, 'Akses developer diperlukan.');
        }

        return null;
    }

    private function confirm(Request $request, MaintenanceRun $run): void
    {
        $confirmed = (array) $request->session()->get('developer.maintenance.confirmed', []);
        $now = now('UTC')->timestamp;
        $confirmed = array_filter($confirmed, static function (mixed $entry) use ($now): bool {
            return is_array($entry) && (int) ($entry['expires_at'] ?? 0) >= $now;
        });
        $confirmed[(string) $run->getKey()] = [
            'expires_at' => now('UTC')->addMinutes(30)->timestamp,
            'user_id' => (string) $request->user()?->getAuthIdentifier(),
        ];
        $request->session()->put('developer.maintenance.confirmed', $confirmed);
    }

    private function isConfirmed(Request $request, MaintenanceRun $run): bool
    {
        $confirmed = (array) $request->session()->get('developer.maintenance.confirmed', []);
        $entry = $confirmed[(string) $run->getKey()] ?? null;
        if (! is_array($entry)) {
            return false;
        }

        return (int) ($entry['expires_at'] ?? 0) >= now('UTC')->timestamp
            && hash_equals((string) ($entry['user_id'] ?? ''), (string) $request->user()?->getAuthIdentifier());
    }
}
