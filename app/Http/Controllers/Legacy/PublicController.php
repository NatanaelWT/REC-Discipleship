<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Legacy\Concerns\RendersLegacyPages;
use App\Services\Legacy\LegacyRouteMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicController extends Controller
{
    use RendersLegacyPages;

    public function home(Request $request): RedirectResponse|Response
    {
        $legacyPage = trim((string) $request->query('page', ''));
        if ($legacyPage !== '' && LegacyRouteMap::hasPage($legacyPage)) {
            if (! $request->isMethod('GET')) {
                return $this->legacy($request, $legacyPage);
            }

            return redirect()->away($request->getSchemeAndHttpHost() . LegacyRouteMap::pageUrl($legacyPage, $request->query()));
        }

        return $this->legacy($request, 'kutisari');
    }

    public function dgBranch(Request $request): Response
    {
        return $this->legacy($request, 'public_dg_branch');
    }

    public function dgReport(Request $request): Response
    {
        return $this->legacy($request, 'public_dg_report');
    }

    public function memberFeedbackBranch(Request $request): Response
    {
        return $this->legacy($request, 'public_member_feedback_branch');
    }

    public function memberFeedback(Request $request): Response
    {
        return $this->legacy($request, 'public_member_feedback');
    }

    public function materials(Request $request): Response
    {
        return $this->legacy($request, 'public_materials');
    }

    public function materialPreview(Request $request): Response
    {
        return $this->legacy($request, 'public_material_preview');
    }

    public function materialDownload(Request $request): Response
    {
        return $this->legacy($request, 'public_material_download');
    }

    public function difficultQuestionSubmit(Request $request): Response
    {
        return $this->legacy($request, 'public_difficult_question_submit');
    }

    public function difficultAnswerLookup(Request $request): Response
    {
        return $this->legacy($request, 'public_difficult_answer_lookup');
    }

    public function menuEmpty(Request $request): Response
    {
        return $this->legacy($request, 'public_menu_empty');
    }
}
