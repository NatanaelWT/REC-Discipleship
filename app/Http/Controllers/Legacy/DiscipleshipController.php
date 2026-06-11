<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Legacy\Concerns\RendersLegacyPages;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DiscipleshipController extends Controller
{
    use RendersLegacyPages;

    public function dashboard(Request $request): Response
    {
        return $this->legacy($request, 'discipleship_dashboard');
    }

    public function groups(Request $request): Response
    {
        return $this->legacy($request, 'groups_list');
    }

    public function people(Request $request): Response
    {
        return $this->legacy($request, 'people');
    }

    public function peopleList(Request $request): Response
    {
        return $this->legacy($request, 'people_list');
    }

    public function tree(Request $request): Response
    {
        return $this->legacy($request, 'people_tree');
    }

    public function treeV2(Request $request): Response
    {
        return $this->legacy($request, 'people_tree_v2');
    }

    public function spiritualJourney(Request $request): Response
    {
        return $this->legacy($request, 'spiritual_journey');
    }

    public function reportsRecap(Request $request): Response
    {
        return $this->legacy($request, 'dg_reports_recap');
    }

    public function mskClasses(Request $request): Response
    {
        return $this->legacy($request, 'msk_classes');
    }

    public function targets(Request $request): Response
    {
        return $this->legacy($request, 'discipleship_targets');
    }

    public function difficultQuestions(Request $request): Response
    {
        return $this->legacy($request, 'difficult_questions_admin');
    }
}
