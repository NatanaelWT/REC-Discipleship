<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Developer\DeveloperBranchService;
use App\Services\Developer\DeveloperUserService;
use App\Services\Routing\AppPageRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeveloperUserController extends Controller
{
    public function index(
        Request $request,
        DeveloperBranchService $branches,
        DeveloperUserService $users,
    ): RedirectResponse|View {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('developer.users', [
            'settings' => ['church_name' => app_church_name()],
            'currentPage' => 'developer_users',
            'users' => User::query()->orderBy('username')->get(),
            'branchOptions' => $branches->options(),
            'scopeOptions' => $users->scopeOptions(),
            'statusCode' => trim((string) $request->query('status', '')),
            'errorCode' => trim((string) $request->query('error', '')),
            'errorMessages' => $this->errorMessages(),
        ]);
    }

    public function store(Request $request, DeveloperUserService $users): RedirectResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $error = $users->create($request->all());
        if ($error !== null) {
            return redirect()->route('developer.users', ['error' => $error]);
        }

        return redirect()->route('developer.users', ['status' => 'created']);
    }

    public function update(Request $request, User $user, DeveloperUserService $users): RedirectResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $error = $users->update($user, $request->all(), current_username());
        if ($error !== null) {
            return redirect()->route('developer.users', ['error' => $error]);
        }

        if (! can_manage_users()) {
            return redirect(AppPageRouteMap::pageUrl(branch_home_page(current_user_branch())));
        }

        return redirect()->route('developer.users', ['status' => 'updated']);
    }

    public function resetPassword(Request $request, User $user, DeveloperUserService $users): RedirectResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $error = $users->resetPassword($user, (string) $request->input('password', ''), current_username());
        if ($error !== null) {
            return redirect()->route('developer.users', ['error' => $error]);
        }

        return redirect()->route('developer.users', ['status' => 'password_reset']);
    }

    private function guard(Request $request): ?RedirectResponse
    {
        RuntimeBootstrap::boot($request);
        if (! is_logged_in()) {
            return redirect()->route('auth.login');
        }
        if (! can_manage_users()) {
            abort(403, 'Akses developer diperlukan.');
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function errorMessages(): array
    {
        return [
            'missing_required' => 'Isi semua kolom wajib.',
            'username_invalid' => 'Username harus 3-120 karakter dan hanya huruf, angka, titik, garis bawah, atau strip.',
            'username_taken' => 'Username sudah dipakai.',
            'email_taken' => 'Email sudah dipakai.',
            'password_short' => 'Password minimal 6 karakter.',
            'scope_invalid' => 'Scope tidak valid.',
            'branch_invalid' => 'Cabang tidak valid.',
            'self_deactivate' => 'Developer tidak bisa menonaktifkan akunnya sendiri.',
            'last_active_developer' => 'Harus ada minimal satu developer aktif.',
            'self_password_reset' => 'Gunakan halaman pengaturan untuk mengganti password sendiri.',
        ];
    }
}
