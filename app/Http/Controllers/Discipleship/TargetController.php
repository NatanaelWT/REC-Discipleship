<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscipleshipTargets\UpdateDiscipleshipTargetRequest;
use App\Services\DiscipleshipTargets\DiscipleshipTargetReader;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TargetController extends Controller
{
    public function index(Request $request, DiscipleshipTargetReader $targetReader): View
    {
        RuntimeBootstrap::boot($request);

        $centralReadOnly = is_effective_central_discipleship_readonly();
        $selectedCentralBranch = $centralReadOnly ? central_recap_selected_branch() : '';
        $showAllBranches = $centralReadOnly && $selectedCentralBranch === 'all';
        $activeBranch = $centralReadOnly
            ? $selectedCentralBranch
            : current_user_branch();

        return view('discipleship.targets.index', [
            'settings' => ['church_name' => app_church_name()],
            'saved' => $request->query->has('saved'),
            'centralReadOnly' => $centralReadOnly,
            'showAllBranches' => $showAllBranches,
            'targets' => $showAllBranches ? [] : $targetReader->formValuesForBranch($activeBranch),
            'activeBranchLabel' => $centralReadOnly
                ? central_recap_branch_label($activeBranch)
                : user_branch_label($activeBranch),
            'branchTargetRows' => $showAllBranches ? $this->branchTargetRows($targetReader) : [],
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
     * @return array<int, array{branch_code:string, branch_label:string, targets:array<string, int>}>
     */
    private function branchTargetRows(DiscipleshipTargetReader $targetReader): array
    {
        $branchOptions = public_dg_branch_options();
        $branchCodes = array_map(
            static fn (array $option): string => normalize_public_branch_code((string) ($option['code'] ?? '')),
            $branchOptions,
        );
        $targetsByBranch = $targetReader->formValuesForBranches($branchCodes);

        return array_map(static function (array $option) use ($targetsByBranch): array {
            $branchCode = normalize_public_branch_code((string) ($option['code'] ?? ''));

            return [
                'branch_code' => $branchCode,
                'branch_label' => trim((string) ($option['label'] ?? strtoupper($branchCode))),
                'targets' => $targetsByBranch[$branchCode] ?? [],
            ];
        }, $branchOptions);
    }
}
