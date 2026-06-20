<?php

namespace App\Services\DiscipleshipPeopleTree;

use App\Services\Branches\BranchCatalog;
use App\Services\Routing\AppPageRouteMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PeopleTreeWriter
{
    public function __construct(
        private readonly PeopleTreeModelStore $modelStore,
        private readonly BranchCatalog $branches,
    ) {}

    public function savePerson(Request $request): RedirectResponse
    {
        return $this->handle($request, 'save_person');
    }

    public function deletePerson(Request $request): RedirectResponse
    {
        return $this->handle($request, 'delete_person');
    }

    public function saveGroup(Request $request): RedirectResponse
    {
        return $this->handle($request, 'save_group');
    }

    public function leavePersonGroup(Request $request): RedirectResponse
    {
        return $this->handle($request, 'leave_person_group');
    }

    public function completeGroup(Request $request): RedirectResponse
    {
        return $this->handle($request, 'complete_group');
    }

    public function reactivateGroup(Request $request): RedirectResponse
    {
        return $this->handle($request, 'reactivate_group');
    }

    public function handleFormAction(Request $request): RedirectResponse
    {
        $action = trim((string) $request->input('action', ''));
        if (! in_array($action, $this->supportedActions(), true)) {
            return redirect()->route('discipleship.tree', ['error' => 'invalid_action']);
        }

        return $this->handle($request, $action);
    }

    public function exportDot(Request $request): RedirectResponse|Response
    {
        if (! $this->validPostRequest()) {
            abort(403, 'Permintaan ditolak demi keamanan.');
        }

        $targetBranch = normalize_public_branch_code(current_user_branch());
        $redirectParams = [];

        if (is_effective_central_discipleship_readonly()) {
            $selectedExportBranch = trim((string) $request->input('export_cabang', ''));
            if ($selectedExportBranch === '') {
                $selectedExportBranch = central_recap_selected_branch();
            }
            $selectedExportBranch = normalize_central_recap_branch($selectedExportBranch);
            if ($selectedExportBranch === 'all') {
                return redirect()->route('discipleship.tree', [
                    'branch_id' => 'all',
                    'error' => 'dot_export_branch_required',
                ]);
            }
            $targetBranch = normalize_public_branch_code($selectedExportBranch);
            $redirectParams['branch_id'] = $this->branches->idForSlug($targetBranch);
        }

        $dotContent = build_pohon_pemuridan_dot_content($targetBranch, $this->modelStore->modelForBranch($targetBranch));
        if ($dotContent === '') {
            $redirectParams['error'] = 'dot_export_failed';

            return redirect()->route('discipleship.tree', $redirectParams);
        }

        $branchLabel = sanitize_file_name_component($targetBranch, 'cabang');
        $downloadName = preg_replace('/[\x00-\x1F\x7F"\\\\]+/', '_', 'pohon_pemuridan_'.$branchLabel.'.dot') ?? 'pohon_pemuridan.dot';
        if ($downloadName === '') {
            $downloadName = 'pohon_pemuridan.dot';
        }
        $asciiDownloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?? 'pohon_pemuridan.dot';
        if ($asciiDownloadName === '') {
            $asciiDownloadName = 'pohon_pemuridan.dot';
        }

        return response($dotContent, 200, [
            'Content-Type' => 'text/vnd.graphviz; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Content-Disposition' => 'attachment; filename="'.$asciiDownloadName.'"; filename*=UTF-8\'\''.rawurlencode($downloadName),
            'Content-Length' => (string) strlen($dotContent),
        ]);
    }

    private function handle(Request $request, string $action): RedirectResponse
    {
        if (! $this->validPostRequest()) {
            abort(403, 'Permintaan ditolak demi keamanan.');
        }

        if (is_effective_central_discipleship_readonly()) {
            return redirect()->route('discipleship.tree', ['error' => 'access_denied']);
        }

        if (! branch_can_use_action(current_user_branch(), $action)) {
            return redirect(AppPageRouteMap::pageUrl(branch_home_page(current_user_branch()), ['error' => 'access_denied']));
        }

        $branchCode = normalize_public_branch_code(current_user_branch());
        $model = $this->modelStore->modelForBranch($branchCode);
        $members = [];
        $mskClasses = $this->modelStore->participantsForBranches([$branchCode], false);
        $result = ['ok' => false, 'error' => 'invalid_action'];

        $payload = $request->all();
        $projectionPeople = dgv2_people_projection($model, $members, $mskClasses);
        $projectionPeopleById = index_by_id($projectionPeople);

        if ($action === 'save_person') {
            $result = dgv2_save_person($model, $payload, $members, $mskClasses);
        } elseif ($action === 'delete_person') {
            $result = dgv2_archive_person($model, trim((string) $request->input('id', '')));
        } elseif ($action === 'save_group') {
            $result = dgv2_save_group($model, $payload, $projectionPeopleById);
        } elseif ($action === 'delete_group') {
            $result = dgv2_archive_group($model, trim((string) $request->input('id', '')));
        } elseif ($action === 'leave_person_group') {
            $result = dgv2_leave_group(
                $model,
                trim((string) $request->input('id', '')),
                trim((string) $request->input('group_id', '')),
            );
        } elseif ($action === 'complete_group') {
            $result = dgv2_complete_group($model, trim((string) $request->input('id', '')));
        } elseif ($action === 'reactivate_group') {
            $result = dgv2_reactivate_group($model, trim((string) $request->input('id', '')));
        }

        if (! empty($result['ok'])) {
            $this->modelStore->replaceBranchModel($branchCode, $model);

            return $this->successRedirect($request, $action);
        }

        return $this->errorRedirect($request, trim((string) ($result['error'] ?? 'save_failed')) ?: 'save_failed');
    }

    private function successRedirect(Request $request, string $action): RedirectResponse
    {
        $params = [];
        if ($action === 'delete_person') {
            $params['person_archived'] = 1;
        } elseif ($action === 'leave_person_group') {
            $params['left_group'] = 1;
        } elseif ($action === 'complete_group') {
            $params['group_completed'] = 1;
        } elseif ($action === 'reactivate_group') {
            $params['group_reactivated'] = 1;
        } else {
            $params['saved'] = 1;
        }

        return $this->redirectToReturnPage($request, $params);
    }

    private function errorRedirect(Request $request, string $errorCode): RedirectResponse
    {
        return $this->redirectToReturnPage($request, ['error' => $errorCode]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function redirectToReturnPage(Request $request, array $params): RedirectResponse
    {
        $returnPage = trim((string) $request->input('return_page', 'people_tree'));
        if ($returnPage === 'people_tree_v2') {
            $returnPage = 'people_tree';
        }

        if (! in_array($returnPage, ['people_tree', 'discipleship_dashboard', 'groups_list', 'people_list'], true)) {
            $returnPage = 'people_tree';
        }

        if ($returnPage === 'people_tree') {
            return redirect()->route('discipleship.tree', $params);
        }

        return redirect(AppPageRouteMap::pageUrl($returnPage, $params));
    }

    private function validPostRequest(): bool
    {
        return function_exists('is_valid_post_origin') ? is_valid_post_origin() : true;
    }

    /**
     * @return array<int, string>
     */
    private function supportedActions(): array
    {
        return [
            'save_person',
            'delete_person',
            'save_group',
            'delete_group',
            'leave_person_group',
            'complete_group',
            'reactivate_group',
        ];
    }
}
