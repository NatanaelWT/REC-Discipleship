<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Services\DiscipleshipGroups\DiscipleshipGroupIndexData;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(Request $request, DiscipleshipGroupIndexData $groupIndexData): View
    {
        RuntimeBootstrap::boot($request);

        $pageData = $groupIndexData->forCurrentContext();

        return view('discipleship.groups.index', $pageData);
    }
}
