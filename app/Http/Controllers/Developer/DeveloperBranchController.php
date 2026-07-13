<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Developer\DeveloperBranchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeveloperBranchController extends Controller
{
    public function index(Request $request, DeveloperBranchService $branches): RedirectResponse|View
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $branchRows = $branches->adminRows();

        return view('developer.branches', [
            'settings' => ['church_name' => app_church_name()],
            'currentPage' => 'developer_branches',
            'branches' => $branchRows,
            'stats' => $branches->stats($branchRows),
            'targetFields' => $branches->targetFields(),
            'statusCode' => trim((string) $request->query('status', '')),
            'errorCode' => trim((string) $request->query('error', '')),
            'expandedBranchId' => max(0, (int) $request->query('branch', 0)),
            'errorMessages' => $this->errorMessages(),
        ]);
    }

    public function store(Request $request, DeveloperBranchService $branches): RedirectResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $error = $branches->create($request->all());
        if ($error !== null) {
            return redirect()->route('developer.branches', ['error' => $error]);
        }

        return redirect()->route('developer.branches', ['status' => 'created']);
    }

    public function update(Request $request, Branch $branch, DeveloperBranchService $branches): RedirectResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $error = $branches->update($branch, $request->all());
        if ($error !== null) {
            return redirect()->route('developer.branches', [
                'error' => $error,
                'branch' => (int) $branch->getKey(),
            ]);
        }

        return redirect()->route('developer.branches', [
            'status' => 'updated',
            'branch' => (int) $branch->getKey(),
        ]);
    }

    public function delete(Request $request, Branch $branch, DeveloperBranchService $branches): RedirectResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $branchId = (int) $branch->getKey();
        $error = $branches->delete($branch);
        if ($error !== null) {
            return redirect()->route('developer.branches', [
                'error' => $error,
                'branch' => $branchId,
            ]);
        }

        return redirect()->route('developer.branches', ['status' => 'deleted']);
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

    /**
     * @return array<string, string>
     */
    private function errorMessages(): array
    {
        return [
            'missing_required' => 'Isi semua kolom wajib.',
            'label_invalid' => 'Nama cabang tidak valid.',
            'label_taken' => 'Nama atau slug cabang sudah dipakai.',
            'slug_reserved' => 'Nama cabang memakai slug yang dicadangkan.',
            'target_invalid' => 'Target cabang harus berupa angka 0 sampai 1.000.000.',
            'branch_protected' => 'Cabang ini tidak bisa dikelola dari halaman Developer.',
            'branch_not_empty' => 'Cabang masih punya user atau data pemuridan. Nonaktifkan cabang jika ingin menjadikannya mode eksperimen.',
        ];
    }
}
