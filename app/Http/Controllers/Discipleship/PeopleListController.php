<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Services\DiscipleshipPeople\DiscipleshipPeopleListData;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PeopleListController extends Controller
{
    public function index(Request $request, DiscipleshipPeopleListData $peopleListData): View
    {
        RuntimeBootstrap::boot($request);

        return view('discipleship.people-list.index', $peopleListData->forCurrentContext($request));
    }
}
