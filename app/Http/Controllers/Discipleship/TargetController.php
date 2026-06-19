<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscipleshipTargets\UpdateDiscipleshipTargetRequest;
use App\Services\DiscipleshipTargets\DiscipleshipTargetReader;
use App\Services\Routing\AppPageRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TargetController extends Controller
{
    public function index(Request $request, DiscipleshipTargetReader $targetReader): RedirectResponse|View
    {
        RuntimeBootstrap::boot($request);

        if (! is_logged_in()) {
            return redirect()->route('auth.login');
        }

        if (! branch_can_access_page(current_user_branch(), 'discipleship_targets')) {
            return redirect(AppPageRouteMap::pageUrl(branch_home_page(current_user_branch()), ['error' => 'access_denied']));
        }

        $centralReadOnly = is_effective_central_discipleship_readonly();
        $currentBranch = current_user_branch();

        return view('discipleship.targets.index', [
            'settings' => ['church_name' => app_church_name()],
            'saved' => $request->query->has('saved'),
            'centralReadOnly' => $centralReadOnly,
            'targets' => $targetReader->formValuesForBranch($currentBranch),
            'activeBranchLabel' => user_branch_label($currentBranch),
            'branchTargetRows' => $this->branchTargetRows($targetReader),
        ]);
    }

    public function update(
        UpdateDiscipleshipTargetRequest $request,
        DiscipleshipTargetReader $targetReader,
    ): RedirectResponse {
        $targetReader->saveBranch(current_user_branch(), $request->targetValues());

        return redirect()->route('discipleship.targets', ['saved' => 1]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function branchTargetRows(DiscipleshipTargetReader $targetReader): array
    {
        $rows = [];
        foreach (public_dg_branch_options() as $branchOption) {
            $branchCode = normalize_public_branch_code((string) ($branchOption['code'] ?? 'kutisari'));
            $branchLabel = trim((string) ($branchOption['label'] ?? strtoupper($branchCode)));
            if ($branchLabel === '') {
                $branchLabel = strtoupper($branchCode);
            }

            $targets = $targetReader->formValuesForBranch($branchCode);
            $rows[] = [
                'branch_code' => $branchCode,
                'branch_label' => $branchLabel,
                'targets' => $targets,
            ];
        }

        return $rows;
    }
}
