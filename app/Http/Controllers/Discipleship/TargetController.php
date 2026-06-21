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
        $activeBranch = $centralReadOnly
            ? central_recap_selected_single_branch()
            : current_user_branch();

        return view('discipleship.targets.index', [
            'settings' => ['church_name' => app_church_name()],
            'saved' => $request->query->has('saved'),
            'centralReadOnly' => $centralReadOnly,
            'targets' => $targetReader->formValuesForBranch($activeBranch),
            'activeBranchLabel' => $centralReadOnly
                ? central_recap_branch_label($activeBranch)
                : user_branch_label($activeBranch),
        ]);
    }

    public function update(
        UpdateDiscipleshipTargetRequest $request,
        DiscipleshipTargetReader $targetReader,
    ): RedirectResponse {
        $targetReader->saveBranch(current_user_branch(), $request->targetValues());

        return redirect()->route('discipleship.targets', ['saved' => 1]);
    }
}
